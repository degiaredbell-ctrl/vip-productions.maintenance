import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    CHANGE_EVENT,
    getOutbox,
    pingServer,
    removeFromOutbox,
    returnToDraft,
    syncOutbox,
} from '@/offline/pmOffline';

const RETRY_MS = 30000;

const formatTime = (iso) => {
    try {
        return new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    } catch {
        return iso;
    }
};

/**
 * Status koneksi ke server + antrean checklist PM yang belum terkirim.
 *
 * Dipasang di layout supaya antrean terkirim otomatis dari halaman mana pun
 * begitu server bisa dijangkau lagi, bukan hanya dari form PM.
 */
export default function OfflineOutbox({ userId, onToast }) {
    const [items, setItems] = useState(() => (userId ? getOutbox(userId) : []));
    const [online, setOnline] = useState(true);
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const onToastRef = useRef(onToast);
    onToastRef.current = onToast;

    const refresh = useCallback(() => {
        if (userId) setItems(getOutbox(userId));
    }, [userId]);

    const runSync = useCallback(async () => {
        if (!userId) return;

        setBusy(true);
        const summary = await syncOutbox(userId);
        setBusy(false);
        refresh();

        if (summary.offline) {
            setOnline(false);
            return;
        }

        setOnline(true);

        if (summary.sent.length > 0) {
            const names = summary.sent.map((item) => item.machine_name).join(', ');
            onToastRef.current?.({
                message: `${summary.sent.length} checklist PM terkirim ke server: ${names}. Lanjut ke approval User PIC.`,
                variant: 'done',
            });

            // Halaman yang sedang terbuka (Beranda, Persetujuan, dll.) memuat
            // status lama; muat ulang datanya tanpa berpindah halaman. Form PM
            // dilewati supaya isian yang sedang diketik tidak tertimpa.
            if (!window.location.pathname.match(/^\/machines\/\d+\/pm$/)) {
                router.reload({ preserveScroll: true, preserveState: true });
            }
        }

        if (summary.failed.length > 0) {
            onToastRef.current?.({
                message: `${summary.failed.length} checklist gagal dikirim dan perlu diperbaiki. Buka antrean untuk detail.`,
                variant: 'issue',
            });
            setOpen(true);
        }

        if (summary.auth) {
            onToastRef.current?.({
                message: 'Sesi login berakhir. Login ulang supaya checklist di perangkat bisa dikirim.',
                variant: 'issue',
            });
        }
    }, [userId, refresh]);

    // Sinkron saat halaman dibuka, saat jaringan kembali, saat tab aktif lagi,
    // dan saat ada perubahan antrean dari form PM.
    useEffect(() => {
        if (!userId) return undefined;

        refresh();
        runSync();

        const onChange = () => refresh();
        const onOnline = () => runSync();
        const onOffline = () => setOnline(false);
        const onVisible = () => {
            if (document.visibilityState === 'visible') runSync();
        };

        window.addEventListener(CHANGE_EVENT, onChange);
        window.addEventListener('storage', onChange);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            window.removeEventListener(CHANGE_EVENT, onChange);
            window.removeEventListener('storage', onChange);
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [userId, refresh, runSync]);

    // Selama ada antrean atau server tak terjangkau, coba lagi berkala.
    const hasPending = items.some((item) => item.status === 'pending' || item.status === 'auth');

    useEffect(() => {
        if (!userId || (!hasPending && online)) return undefined;

        const timer = setInterval(async () => {
            if (hasPending) {
                runSync();
            } else {
                setOnline(await pingServer());
            }
        }, RETRY_MS);

        return () => clearInterval(timer);
    }, [userId, hasPending, online, runSync]);

    if (!userId || (online && items.length === 0)) return null;

    const failedCount = items.filter((item) => item.status === 'failed').length;
    const pendingCount = items.length - failedCount;

    const openForm = (item) => {
        returnToDraft(userId, item.client_id);
        refresh();
        setOpen(false);
        router.visit(route('machines.pm.create', { machine: item.machine_id, year: item.year, period: item.period }));
    };

    const discard = (item) => {
        if (!window.confirm(`Hapus checklist ${item.machine_name} (${item.period} ${item.year}) dari perangkat? Isian akan hilang.`)) return;
        removeFromOutbox(userId, item.client_id);
        refresh();
    };

    return (
        <div className="fixed left-1/2 -translate-x-1/2 bottom-[84px] lg:bottom-5 lg:left-auto lg:right-5 lg:translate-x-0 z-40 w-[calc(100%-24px)] max-w-md">
            {open && items.length > 0 && (
                <div className="neu-card !p-3.5 mb-2 max-h-[55vh] overflow-y-auto" role="dialog" aria-label="Antrean checklist PM">
                    <div className="flex items-center justify-between mb-2">
                        <b className="text-sm">Checklist di perangkat</b>
                        <button type="button" className="text-xs text-neu-sub px-1" onClick={() => setOpen(false)}>
                            Tutup
                        </button>
                    </div>
                    <ul className="space-y-2.5">
                        {items.map((item) => (
                            <li key={item.client_id} className="neu-inset px-3 py-2.5">
                                <b className="block text-sm break-words">{item.machine_name}</b>
                                <span className="block text-xs text-neu-sub">
                                    {item.machine_code} · {item.period} {item.year} · disimpan {formatTime(item.queued_at)}
                                </span>
                                {item.status === 'failed' ? (
                                    <span className="block text-xs text-neu-bad mt-1 break-words">Gagal: {item.error}</span>
                                ) : item.status === 'auth' ? (
                                    <span className="block text-xs text-neu-warn mt-1">{item.error}</span>
                                ) : (
                                    <span className="block text-xs text-neu-info mt-1">
                                        Menunggu terkirim{item.error ? ` · ${item.error}` : ''}
                                    </span>
                                )}
                                <div className="flex gap-3 mt-1.5 text-xs font-semibold">
                                    {item.status === 'failed' && (
                                        <button type="button" className="text-neu-accent" onClick={() => openForm(item)}>
                                            Buka &amp; perbaiki
                                        </button>
                                    )}
                                    <button type="button" className="text-neu-bad" onClick={() => discard(item)}>
                                        Hapus
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div
                className={`neu-card !py-2.5 !px-3.5 flex items-center gap-3 ${online ? '' : '!bg-[#FFF5E6]'}`}
                role="status"
                aria-live="polite"
            >
                <span
                    className={`w-2.5 h-2.5 rounded-full flex-none ${online ? (busy ? 'bg-neu-info animate-pulse' : 'bg-neu-accent') : 'bg-neu-warn'}`}
                    aria-hidden="true"
                />
                <div className="min-w-0 flex-1">
                    <b className="block text-xs">
                        {online ? (busy ? 'Mengirim ke server…' : 'Terhubung ke server') : 'Tidak terhubung ke server'}
                    </b>
                    <span className="block text-[11px] text-neu-sub">
                        {items.length === 0
                            ? 'Isian form PM tetap tersimpan di perangkat.'
                            : `${pendingCount} menunggu terkirim${failedCount ? ` · ${failedCount} gagal` : ''}`}
                    </span>
                </div>
                {items.length > 0 && (
                    <button type="button" className="text-xs font-semibold text-neu-accent px-1" onClick={() => setOpen((v) => !v)}>
                        {open ? 'Sembunyikan' : 'Lihat'}
                    </button>
                )}
                {items.length > 0 && hasPending && (
                    <button
                        type="button"
                        className="text-xs font-semibold text-neu-accent px-1 disabled:opacity-50"
                        onClick={runSync}
                        disabled={busy}
                    >
                        Kirim
                    </button>
                )}
            </div>
        </div>
    );
}
