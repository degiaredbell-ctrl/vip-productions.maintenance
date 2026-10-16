import axios from 'axios';

/**
 * Penyimpanan checklist PM di perangkat (localStorage) untuk teknisi yang
 * bekerja di area tanpa jaringan ke server.
 *
 * - Draft: isian form yang tersimpan otomatis selama diketik, per akun +
 *   mesin + periode. Tidak hilang walau halaman di-refresh/browser ditutup.
 * - Antrean (outbox): checklist yang sudah di-submit tetapi belum sampai ke
 *   server. Dikirim otomatis begitu server bisa dijangkau lagi.
 *
 * Semua kunci memakai id akun, jadi perangkat yang dipakai bergantian tidak
 * mengirim checklist milik akun lain dengan sesi akun yang sedang login.
 *
 * Catatan: aplikasi diakses lewat HTTP di jaringan lokal, jadi fitur yang
 * butuh "secure context" (Service Worker, crypto.randomUUID) tidak tersedia.
 * Modul ini sengaja hanya memakai localStorage, fetch/axios, dan
 * crypto.getRandomValues yang tetap jalan di HTTP.
 */

const PREFIX = 'vip-pm:v1';
export const CHANGE_EVENT = 'vip-pm-offline:changed';

const draftKey = (userId, machineId, year, period) => `${PREFIX}:u${userId}:draft:${machineId}:${year}:${period}`;
const outboxKey = (userId) => `${PREFIX}:u${userId}:outbox`;

function readJson(key, fallback) {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

/** @returns {boolean} false kalau penyimpanan perangkat penuh/ditolak. */
function writeJson(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch {
        return false;
    }
}

/** `detail.sent` berisi client_id yang benar-benar sampai ke server. */
function notify(detail = {}) {
    window.dispatchEvent(new CustomEvent(CHANGE_EVENT, { detail }));
}

export function newClientId() {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/* ------------------------------- Draft ------------------------------- */

export function loadDraft(userId, machineId, year, period) {
    return readJson(draftKey(userId, machineId, year, period), null);
}

export function saveDraft(userId, machineId, year, period, draft) {
    return writeJson(draftKey(userId, machineId, year, period), { ...draft, saved_at: new Date().toISOString() });
}

export function clearDraft(userId, machineId, year, period) {
    try {
        window.localStorage.removeItem(draftKey(userId, machineId, year, period));
    } catch {
        // abaikan
    }
}

/* ------------------------------ Outbox ------------------------------- */

export function getOutbox(userId) {
    const items = readJson(outboxKey(userId), []);
    return Array.isArray(items) ? items : [];
}

function setOutbox(userId, items, detail = {}) {
    const ok = writeJson(outboxKey(userId), items);
    notify(detail);
    return ok;
}

export function findQueued(userId, machineId, year, period) {
    return getOutbox(userId).find(
        (item) => String(item.machine_id) === String(machineId)
            && String(item.year) === String(year)
            && item.period === period,
    ) ?? null;
}

/**
 * Masukkan checklist ke antrean. Satu mesin+periode hanya punya satu entri;
 * submit ulang menimpa entri lama (dengan client_id yang sama, supaya tetap
 * aman bila entri lama ternyata sudah sempat sampai ke server).
 */
export function enqueue(userId, entry) {
    const items = getOutbox(userId);
    const index = items.findIndex(
        (item) => String(item.machine_id) === String(entry.machine_id)
            && String(item.year) === String(entry.year)
            && item.period === entry.period,
    );

    const next = {
        status: 'pending',
        error: null,
        attempts: 0,
        queued_at: new Date().toISOString(),
        ...(index >= 0 ? { client_id: items[index].client_id, queued_at: items[index].queued_at } : {}),
        ...entry,
    };

    if (index >= 0) items[index] = next;
    else items.push(next);

    return setOutbox(userId, items) ? next : null;
}

export function removeFromOutbox(userId, clientId, { sent = false } = {}) {
    setOutbox(
        userId,
        getOutbox(userId).filter((item) => item.client_id !== clientId),
        sent ? { sent: [clientId] } : {},
    );
}

function updateOutboxItem(userId, clientId, patch) {
    setOutbox(userId, getOutbox(userId).map((item) => (item.client_id === clientId ? { ...item, ...patch } : item)));
}

/**
 * Keluarkan entri dari antrean dan kembalikan isinya ke draft supaya bisa
 * dibuka dan diperbaiki di form.
 */
export function returnToDraft(userId, clientId) {
    const item = getOutbox(userId).find((entry) => entry.client_id === clientId);
    if (!item) return null;

    saveDraft(userId, item.machine_id, item.year, item.period, { ...item.payload, client_id: item.client_id });
    removeFromOutbox(userId, clientId);

    return item;
}

/* ---------------------------- Konektivitas --------------------------- */

/**
 * `navigator.onLine` hanya tahu perangkat tersambung ke Wi-Fi, bukan apakah
 * server di jaringan lokal bisa dijangkau. Jadi server di-ping langsung.
 */
export async function pingServer(timeoutMs = 5000) {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return false;

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(`/up?t=${Date.now()}`, {
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controller.signal,
        });
        return response.ok;
    } catch {
        return false;
    } finally {
        clearTimeout(timer);
    }
}

/* ------------------------------ Kirim -------------------------------- */

/**
 * Hasil:
 *  - { result: 'sent', data }
 *  - { result: 'offline' }        server tak terjangkau, tetap di antrean
 *  - { result: 'auth' }           perlu login ulang
 *  - { result: 'invalid', message, errors }   ditolak validasi (422)
 *  - { result: 'forbidden', message }         record terkunci/tanpa izin (403)
 *  - { result: 'error', message }             galat server (5xx), coba lagi nanti
 */
export async function sendSubmission(entry, { foreground = false, expectedUserId = null } = {}) {
    const body = {
        ...entry.payload,
        client_id: entry.client_id,
        queued_at: entry.queued_at,
        foreground: foreground ? 1 : 0,
    };

    const attempt = () => axios.post(route('pm.sync'), body, {
        timeout: 30000,
        headers: { Accept: 'application/json' },
    });

    try {
        const response = await attempt();
        return { result: 'sent', data: response.data };
    } catch (error) {
        const status = error.response?.status;

        if (!error.response) return { result: 'offline' };

        if (status === 419 || status === 401) {
            // Sesi lama sudah kedaluwarsa (mis. lama di area tanpa jaringan).
            // Ping halaman ber-auth untuk mendapat cookie CSRF baru; kalau
            // akun masih login lewat "Ingat saya", kiriman diulang sekali.
            try {
                const ping = await axios.get(route('pm.sync.ping'), { headers: { Accept: 'application/json' } });
                if (expectedUserId !== null && String(ping.data?.user_id) !== String(expectedUserId)) {
                    return { result: 'auth' };
                }
                const response = await attempt();
                return { result: 'sent', data: response.data };
            } catch (retryError) {
                if (!retryError.response) return { result: 'offline' };
                const retryStatus = retryError.response.status;
                if (retryStatus === 401 || retryStatus === 419) return { result: 'auth' };
                return classify(retryError);
            }
        }

        return classify(error);
    }
}

function classify(error) {
    const status = error.response?.status;
    const data = error.response?.data ?? {};

    if (status === 422) {
        const errors = data.errors ?? {};
        const first = Object.values(errors)[0];
        return {
            result: 'invalid',
            errors,
            message: (Array.isArray(first) ? first[0] : first) || data.message || 'Data checklist tidak valid.',
        };
    }

    if (status === 403) {
        return {
            result: 'forbidden',
            message: 'Checklist ini tidak bisa dikirim: mungkin sudah dikirim dari perangkat lain atau sedang dalam proses approval.',
        };
    }

    return { result: 'error', message: data.message || `Server error (${status}).` };
}

/* ------------------------- Sinkronisasi antrean ----------------------- */

let syncing = false;

/**
 * Kirim semua entri berstatus pending/auth milik akun ini secara berurutan.
 * Dipanggil otomatis dari layout (saat online kembali, saat tab aktif, dan
 * berkala), atau manual dari tombol "Kirim sekarang".
 *
 * @returns {Promise<{sent: Array, failed: Array, offline: boolean, auth: boolean}>}
 */
export async function syncOutbox(userId) {
    const summary = { sent: [], failed: [], offline: false, auth: false };

    if (syncing) return summary;

    const pending = getOutbox(userId).filter((item) => item.status === 'pending' || item.status === 'auth');
    if (pending.length === 0) return summary;

    syncing = true;

    try {
        if (!(await pingServer())) {
            summary.offline = true;
            return summary;
        }

        for (const item of pending) {
            const outcome = await sendSubmission(item, { expectedUserId: userId });
            const now = new Date().toISOString();

            if (outcome.result === 'sent') {
                removeFromOutbox(userId, item.client_id, { sent: true });
                clearDraft(userId, item.machine_id, item.year, item.period);
                summary.sent.push(item);
            } else if (outcome.result === 'offline') {
                updateOutboxItem(userId, item.client_id, { attempts: (item.attempts ?? 0) + 1, last_attempt_at: now });
                summary.offline = true;
                break;
            } else if (outcome.result === 'auth') {
                updateOutboxItem(userId, item.client_id, {
                    status: 'auth',
                    error: 'Sesi login berakhir. Login ulang untuk mengirim.',
                    last_attempt_at: now,
                });
                summary.auth = true;
                break;
            } else if (outcome.result === 'error') {
                updateOutboxItem(userId, item.client_id, {
                    attempts: (item.attempts ?? 0) + 1,
                    error: outcome.message,
                    last_attempt_at: now,
                });
            } else {
                // invalid / forbidden: tidak akan berhasil dengan dikirim ulang.
                updateOutboxItem(userId, item.client_id, {
                    status: 'failed',
                    error: outcome.message,
                    last_attempt_at: now,
                });
                summary.failed.push({ ...item, error: outcome.message });
            }
        }
    } finally {
        syncing = false;
    }

    return summary;
}
