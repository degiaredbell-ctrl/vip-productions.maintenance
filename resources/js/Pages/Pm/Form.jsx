import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';
import NeuTrack from '@/Components/NeuTrack';
import NeuSignaturePad from '@/Components/NeuSignaturePad';
import SignatureActions from '@/Components/SignatureActions';
import SignatureChain from '@/Components/SignatureChain';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    CHANGE_EVENT,
    clearDraft,
    enqueue,
    findQueued,
    loadDraft,
    newClientId,
    removeFromOutbox,
    returnToDraft,
    saveDraft,
    sendSubmission,
    syncOutbox,
} from '@/offline/pmOffline';

/**
 * Gabungkan isian item dari draft perangkat ke daftar item dari server.
 * Dicocokkan lewat nama + kategori, bukan indeks, supaya perubahan template
 * setelah draft dibuat tidak menggeser nilai ke item yang salah.
 */
function mergeDraftItems(items, draftItems) {
    if (!Array.isArray(draftItems)) return items.map((item) => ({ ...item }));

    return items.map((item) => {
        const saved = draftItems.find((d) => d.item_name === item.item_name && d.category === item.category);
        if (!saved) return { ...item };

        return {
            ...item,
            actual: saved.actual ?? item.actual,
            act_clean: Boolean(saved.act_clean),
            act_repair: Boolean(saved.act_repair),
            act_lubricate: Boolean(saved.act_lubricate),
            act_replace: Boolean(saved.act_replace),
            replace_note: saved.replace_note ?? item.replace_note ?? '',
        };
    });
}

const formatSavedAt = (iso) => {
    try {
        return new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    } catch {
        return '';
    }
};


export default function PmForm({ auth, machine, items, period, year, dashboardUrl, approvalReturnUrl = null, existing, isFuturePeriod, canFill, canSignTechnician, picCandidates = [], chain, savedSignature = null }) {
    const userId = auth.user.id;

    /*
        Setelah submit, checklist dianggap selesai bagi teknisi: yang tersisa
        adalah perjalanannya di rantai approval. Jadi tombol "Kembali" sengaja
        mengarah ke tempat yang sama dengan redirect setelah submit — halaman
        Persetujuan tab "Status PM Saya" — bukan kembali ke daftar "Kembali"
        yang membuka form ini.
    */
    const backUrl = approvalReturnUrl ?? dashboardUrl ?? route('dashboard');

    // Checklist hanya bisa diisi (dan disimpan sebagai draft di perangkat)
    // selama masih di tahap teknisi.
    const editableHere = canFill && !isFuturePeriod && canSignTechnician
        && (!existing || chain?.awaiting === 'technician');

    // Draft & antrean dibaca sekali saat halaman dibuka. Draft yang tertinggal
    // untuk record yang sudah lewat tahap teknisi dibuang karena basi.
    const [initialDraft] = useState(() => {
        const draft = loadDraft(userId, machine.id, year, period);
        if (draft && !editableHere) {
            clearDraft(userId, machine.id, year, period);
            return null;
        }
        return draft;
    });
    const [queued, setQueued] = useState(() => findQueued(userId, machine.id, year, period));
    const [restoredAt, setRestoredAt] = useState(initialDraft?.saved_at ?? null);
    const [signatureSeed, setSignatureSeed] = useState(initialDraft?.signature ?? null);
    const [sending, setSending] = useState(false);
    const [queueNotice, setQueueNotice] = useState('');
    const clientIdRef = useRef(initialDraft?.client_id ?? queued?.client_id ?? newClientId());
    const signatureRef = useRef(initialDraft?.signature ?? null);
    const dirtyRef = useRef(false);
    // Selama submit dari tombol berjalan, perubahan antrean ditangani langsung
    // oleh handleSubmit, bukan oleh listener antrean.
    const foregroundRef = useRef(false);

    const [formItems, setFormItems] = useState(() => mergeDraftItems(items, initialDraft?.items));
    const [error, setError] = useState('');
    const [submitError, setSubmitError] = useState('');
    const [hasSignature, setHasSignature] = useState(false);

    /*
        Dua cara mengisi identitas penanda tangan tahap teknisi:
        - "self"   (Data Saya): nama = nama akun, tanda tangan otomatis dari
                    Profile bila sudah tersimpan (atau digambar bila belum).
        - "manual" (Isi Manual): nama diketik dan tanda tangan digambar,
                    untuk pemeriksa yang berbeda dari pemilik akun.
        Revisi yang sebelumnya memakai nama lain otomatis dibuka di mode manual.
    */
    const accountName = auth.user.name;
    const [signerMode, setSignerMode] = useState(() => initialDraft?.signer_mode
        ?? (existing?.technician_name && existing.technician_name.trim() !== accountName.trim() ? 'manual' : 'self'));
    const [useSaved, setUseSaved] = useState(() => initialDraft?.use_saved_signature ?? Boolean(savedSignature));
    const [saveToProfile, setSaveToProfile] = useState(() => Boolean(initialDraft?.save_signature_to_profile));
    const [savedPreviewFailed, setSavedPreviewFailed] = useState(false);
    // Detail stempel waktu dokumen: tertutup sebagai default supaya checklist
    // tetap yang pertama terlihat, dan hanya bisa dibuka dari barisnya.
    const [showHistory, setShowHistory] = useState(true);

    const padRef = useRef(null);

    const serverDefaults = {
        machine_id: machine.id,
        year: year,
        period: period,
        // Nama teknisi dari submit sebelumnya ikut terbawa. Revisi karena PIC
        // menolak seharusnya tidak memaksa teknisi mengetik ulang namanya.
        technician_name: existing?.technician_name || '',
        general_note: existing?.general_note || '',
        revision_reason: '',
        note: '',
        signature: '',
        // User PIC yang menunggu tahap 2. Saat revisi, penugasan sebelumnya
        // sudah terpilih supaya tidak diam-diam berganti orang; teknisi tetap
        // bisa mengubahnya sebelum submit ulang.
        pic_user_id: existing?.pic_user_id ? String(existing.pic_user_id) : '',
    };

    const fieldsFromDraft = (draft) => ({
        technician_name: draft?.technician_name ?? serverDefaults.technician_name,
        general_note: draft?.general_note ?? serverDefaults.general_note,
        revision_reason: draft?.revision_reason ?? '',
        note: draft?.note ?? '',
        pic_user_id: draft?.pic_user_id ?? serverDefaults.pic_user_id,
    });

    const { data, setData } = useForm({
        ...serverDefaults,
        ...(initialDraft ? fieldsFromDraft(initialDraft) : {}),
        items: formItems,
    });

    const setField = (field, value) => {
        dirtyRef.current = true;
        setData(field, value);
    };

    /*
        Simpan otomatis ke perangkat setiap kali isian berubah, supaya checklist
        yang diisi di area tanpa jaringan tidak hilang walau halaman di-refresh,
        baterai habis, atau browser ditutup.
    */
    const persistDraft = useCallback(() => {
        if (!editableHere || queued) return;

        saveDraft(userId, machine.id, year, period, {
            client_id: clientIdRef.current,
            technician_name: data.technician_name,
            general_note: data.general_note,
            revision_reason: data.revision_reason,
            note: data.note,
            pic_user_id: data.pic_user_id,
            items: formItems,
            signature: signatureRef.current,
            signer_mode: signerMode,
            use_saved_signature: useSaved,
            save_signature_to_profile: saveToProfile,
        });
    }, [editableHere, queued, userId, machine.id, year, period, data, formItems, signerMode, useSaved, saveToProfile]);

    useEffect(() => {
        if (!dirtyRef.current) return undefined;
        const timer = setTimeout(persistDraft, 400);
        return () => clearTimeout(timer);
    }, [persistDraft]);

    // Pantau antrean: begitu checklist yang menunggu terkirim di latar
    // belakang, muat ulang data form dari server.
    useEffect(() => {
        const onChange = (event) => {
            if (foregroundRef.current) return;
            const current = findQueued(userId, machine.id, year, period);
            setQueued((previous) => {
                if (previous && !current && event?.detail?.sent?.includes(previous.client_id)) {
                    router.reload();
                }
                return current;
            });
        };

        window.addEventListener(CHANGE_EVENT, onChange);
        return () => window.removeEventListener(CHANGE_EVENT, onChange);
    }, [userId, machine.id, year, period]);

    const restoreIntoForm = (draft) => {
        const merged = mergeDraftItems(items, draft?.items);
        setFormItems(merged);
        setData((current) => ({ ...current, ...fieldsFromDraft(draft), items: merged }));
        signatureRef.current = draft?.signature ?? null;
        setSignatureSeed(draft?.signature ?? null);
        if (!draft?.signature) padRef.current?.clear();
        if (draft?.signer_mode) setSignerMode(draft.signer_mode);
        if (draft && 'use_saved_signature' in draft) setUseSaved(Boolean(draft.use_saved_signature));
        setSaveToProfile(Boolean(draft?.save_signature_to_profile));
        clientIdRef.current = draft?.client_id ?? newClientId();
    };

    // Ganti orang penanda tangan = tanda tangan yang sudah digambar dibuang,
    // supaya coretan orang lain tidak ikut terkirim atas nama berbeda.
    const changeSignerMode = (mode) => {
        if (mode === signerMode) return;
        dirtyRef.current = true;
        signatureRef.current = null;
        setSignatureSeed(null);
        setHasSignature(false);
        setSignerMode(mode);
        if (error) setError('');
    };

    const changeUseSaved = (value) => {
        dirtyRef.current = true;
        // Coretan yang sempat dibuat di mode gambar digambar ulang saat kembali.
        setSignatureSeed(signatureRef.current);
        setHasSignature(false);
        setUseSaved(value);
        if (error) setError('');
    };

    const discardDraft = () => {
        if (!window.confirm('Buang draft di perangkat ini dan kembali ke isian dari server?')) return;
        clearDraft(userId, machine.id, year, period);
        dirtyRef.current = false;
        restoreIntoForm(null);
        setRestoredAt(null);
    };

    const editQueued = () => {
        if (!queued) return;
        const item = returnToDraft(userId, queued.client_id);
        setQueued(null);
        setQueueNotice('');
        if (item) {
            restoreIntoForm({ ...item.payload, client_id: item.client_id });
            setRestoredAt(new Date().toISOString());
        }
    };

    const sendQueuedNow = async () => {
        setSending(true);
        const summary = await syncOutbox(userId);
        setSending(false);
        if (summary.offline) setQueueNotice('Server belum bisa dijangkau. Checklist tetap aman di perangkat dan akan dikirim otomatis.');
        else if (summary.auth) setQueueNotice('Sesi login berakhir. Login ulang lalu buka halaman ini lagi untuk mengirim.');
    };

    const updateItem = (index, field, value) => {
        const newItems = [...formItems];
        newItems[index] = { ...newItems[index], [field]: value };
        dirtyRef.current = true;
        setFormItems(newItems);
        setData('items', newItems);
    };

    const toggleAction = (index, action) => {
        const newItems = [...formItems];
        const updated = { ...newItems[index], [action]: !newItems[index][action] };

        // Saat "Perlu Pergantian" dimatikan, catatan detailnya ikut dikosongkan
        // supaya tidak tersimpan catatan pergantian pada item yang tidak diganti.
        if (action === 'act_replace' && !updated.act_replace) {
            updated.replace_note = '';
        }

        newItems[index] = updated;
        dirtyRef.current = true;
        setFormItems(newItems);
        setData('items', newItems);
    };

    const progress = Math.round(formItems.filter(item => item.actual).length / formItems.length * 100);

    const signerName = signerMode === 'self' ? accountName : data.technician_name;
    const nameFilled = signerName.trim() !== '';
    const usingSavedSignature = signerMode === 'self' && Boolean(savedSignature) && useSaved;
    const signatureReady = usingSavedSignature || hasSignature;
    // Value dari <select> selalu string, jadi id "0" tidak mungkin terjadi di
    // sini; kosong berarti belum ada yang dipilih.
    const picChosen = data.pic_user_id !== '';
    const picChosenName = (picCandidates ?? []).find(
        (pic) => String(pic.id) === String(data.pic_user_id),
    )?.name;

    /*
        Satu kalimat yang menjelaskan apa yang masih kurang. Disusun berurutan dari
        field paling atas supaya teknisi tahu harus mengisi apa berikutnya, bukan
        hanya melihat tombol yang tiba-tiba mati.
    */
    const submitHint = !nameFilled && !signatureReady && !picChosen
        ? 'Nama teknisi, User PIC, dan tanda tangan wajib diisi sebelum submit.'
        : !nameFilled
            ? 'Nama teknisi wajib diisi sebelum submit.'
            : !picChosen
                ? 'Pilih User PIC yang akan menyetujui sebelum submit.'
                : !signatureReady
                    ? 'Tanda tangan wajib digambar sebelum submit.'
                    : picChosenName
                        ? `Setelah submit, checklist menunggu persetujuan ${picChosenName}.`
                        : 'Setelah submit, checklist langsung diteruskan ke User PIC.';

    const handleSubmit = async (e) => {
        e.preventDefault();
        const incomplete = formItems.some(item => !item.actual);
        if (incomplete) {
            setError('Lengkapi semua nilai Aktual');
            return;
        }

        // Aksi "Perlu Pergantian" wajib disertai keterangan bagian yang diganti,
        // supaya riwayat PM tidak sekadar menampilkan centang tanpa detail komponen.
        const missingReplaceNote = formItems.some(
            (item) => item.act_replace && !String(item.replace_note ?? '').trim(),
        );
        if (missingReplaceNote) {
            setError('Isi detail bagian yang diganti untuk setiap item dengan aksi "Perlu Pergantian".');
            return;
        }

        if (!nameFilled) {
            setError('Nama teknisi wajib diisi.');
            return;
        }

        if (!picChosen) {
            setError('Pilih User PIC yang akan menyetujui berikutnya.');
            return;
        }

        const signature = usingSavedSignature ? null : padRef.current?.getDataUrl();
        if (!usingSavedSignature && !signature) {
            setError('Tanda tangan wajib digambar sebelum melanjutkan.');
            return;
        }

        setError('');
        setSubmitError('');
        setQueueNotice('');

        /*
            Checklist selalu masuk antrean perangkat dulu, baru dikirim. Kalau
            jaringan ke server putus di tengah jalan, isian sudah aman dan akan
            dikirim ulang otomatis; client_id yang sama mencegah PM tercatat
            dua kali.
        */
        foregroundRef.current = true;
        const entry = enqueue(userId, {
            client_id: clientIdRef.current,
            machine_id: machine.id,
            machine_name: machine.name,
            machine_code: machine.code,
            year,
            period,
            payload: {
                ...data,
                items: formItems,
                technician_name: signerName.trim(),
                signature,
                signer_mode: signerMode,
                use_saved_signature: usingSavedSignature,
                save_signature_to_profile: signerMode === 'self' && !usingSavedSignature && saveToProfile,
            },
        });

        if (!entry) {
            foregroundRef.current = false;
            setSubmitError('Penyimpanan perangkat penuh. Hapus data browser yang tidak dipakai lalu coba lagi.');
            return;
        }

        setSending(true);
        const outcome = await sendSubmission(entry, { foreground: true, expectedUserId: userId });
        setSending(false);
        foregroundRef.current = false;

        if (outcome.result === 'sent') {
            removeFromOutbox(userId, entry.client_id);
            clearDraft(userId, machine.id, year, period);
            router.visit(outcome.data?.redirect ?? dashboardUrl ?? route('dashboard'));
            return;
        }

        if (outcome.result === 'offline' || outcome.result === 'auth' || outcome.result === 'error') {
            // Tetap di antrean; draft tidak diperlukan lagi karena isinya
            // sudah tersimpan utuh di entri antrean.
            clearDraft(userId, machine.id, year, period);
            setQueued(findQueued(userId, machine.id, year, period));
            setQueueNotice(
                outcome.result === 'auth'
                    ? 'Sesi login berakhir. Checklist tersimpan di perangkat; login ulang supaya bisa dikirim.'
                    : outcome.result === 'error'
                        ? `Server sedang bermasalah (${outcome.message}). Checklist tersimpan di perangkat dan akan dikirim ulang otomatis.`
                        : '',
            );
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        // Ditolak validasi/otorisasi: kembalikan ke draft supaya bisa diperbaiki.
        removeFromOutbox(userId, entry.client_id);
        signatureRef.current = signature;
        persistDraft();
        setSubmitError(outcome.message);
    };

    const actions = [
        { key: 'act_clean', label: 'Bersihkan' },
        { key: 'act_repair', label: 'Perbaiki' },
        { key: 'act_lubricate', label: 'Lumasi' },
        { key: 'act_replace', label: 'Perlu Pergantian' },
    ];

    const lockedByQueue = Boolean(queued) && queued.status !== 'failed';
    const readOnly = !canFill || isFuturePeriod || lockedByQueue;

    // Tahap yang sedang menunggu ditandatangani, untuk judul panel aksi.
    const activeStage = chain?.stages?.find((stage) => stage.value === chain.awaiting) ?? null;
    const approvalDone = chain?.status === 'approved';

    /*
        Technician menutup checklistnya sendiri di akhir form ini, jadi panel
        tanda tangan tahap 1 ikut masuk ke dalam <form>. Record yang belum pernah
        dibuat belum punya tahap aktif dari server, jadi tahap teknisi dianggap
        aktif selama form masih boleh diisi.
    */
    const atTechnicianStage = existing ? activeStage?.value === 'technician' : true;
    const showTechnicianPanel = !readOnly && atTechnicianStage;

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                {/* Header */}
                <div className="flex items-center gap-3 mb-4">
                    <NeuButton href={backUrl} className="!px-3">
                        ‹ Kembali
                    </NeuButton>
                    <div className="flex-1">
                        <h1 className="text-lg sm:text-xl font-bold">{machine.name}</h1>
                        <p className="text-xs text-neu-sub">
                            {machine.code}
                            {machine.sub_category && ` · ${machine.sub_category}`}
                            {machine.location && ` · ${machine.location}`}
                            {` · Minggu ${machine.week_group}`}
                        </p>
                        <p className="text-xs text-neu-sub">Periode {period} {year}</p>
                    </div>

                </div>

                {/*
                    Catatan penolakan PIC/Atasan. Letakkan tepat di bawah header
                    supaya teknisi tahu apa yang harus diperbaiki sebelum mulai
                    menyentuh checklist, dan supaya jelas bahwa form yang terbuka
                    adalah revisi, bukan checklist baru.
                */}
                {existing?.reject_reason && (
                    <NeuCard className="mb-4 !bg-[#FCEFEC]">
                        <div className="flex items-start gap-3">
                            <span className="neu-alert-icon neu-inset w-9 h-9 flex-none grid place-items-center text-neu-bad font-bold" aria-hidden="true">
                                <span className="neu-alert-mark">!</span>
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-bold text-neu-bad">
                                    Checklist ditolak, mohon diperbaiki
                                </p>
                                <p className="text-xs text-neu-sub mt-0.5">
                                    Ditolak oleh {existing.rejected_by ?? 'reviewer'}
                                    {existing.rejected_at ? ` · ${existing.rejected_at}` : ''}
                                </p>
                                <p className="text-sm mt-2 whitespace-pre-wrap">
                                    {existing.reject_reason}
                                </p>
                                <p className="text-xs text-neu-sub mt-2.5">
                                    Semua isian di bawah tetap ada dan bisa diubah, termasuk User PIC.
                                    Setelah diubah, submit ulang untuk mengulang tahap persetujuan.
                                </p>
                            </div>
                        </div>
                    </NeuCard>
                )}

                {queued && (
                    <NeuCard className={`mb-4 ${queued.status === 'failed' ? '!bg-[#FCEFEC]' : '!bg-[#FFF5E6]'}`}>
                        <div className="flex items-start gap-3">
                            <span
                                className={`neu-inset w-9 h-9 flex-none grid place-items-center font-bold ${queued.status === 'failed' ? 'neu-alert-icon text-neu-bad' : 'text-neu-warn'}`}
                                aria-hidden="true"
                            >
                                {queued.status === 'failed' ? <span className="neu-alert-mark">!</span> : '⇅'}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className={`text-sm font-bold ${queued.status === 'failed' ? 'text-neu-bad' : 'text-neu-warn'}`}>
                                    {queued.status === 'failed'
                                        ? 'Checklist gagal dikirim ke server'
                                        : 'Checklist tersimpan di perangkat, menunggu terkirim'}
                                </p>
                                <p className="text-xs text-neu-sub mt-0.5">
                                    Disubmit {formatSavedAt(queued.queued_at)}.{' '}
                                    {queued.status === 'failed'
                                        ? queued.error
                                        : 'Akan dikirim otomatis begitu perangkat terhubung ke server, lalu lanjut ke approval User PIC. Jangan hapus data browser.'}
                                </p>
                                {queueNotice && <p className="text-xs text-neu-warn mt-1.5">{queueNotice}</p>}
                                <div className="flex gap-2.5 flex-wrap mt-3">
                                    {queued.status !== 'failed' && (
                                        <NeuButton onClick={sendQueuedNow} disabled={sending} className="!min-h-[36px] !py-1.5 !px-3 !text-xs">
                                            {sending ? 'Mengirim…' : 'Kirim sekarang'}
                                        </NeuButton>
                                    )}
                                    <NeuButton onClick={editQueued} disabled={sending} className="!min-h-[36px] !py-1.5 !px-3 !text-xs">
                                        {queued.status === 'failed' ? 'Perbaiki checklist' : 'Batalkan kiriman & ubah'}
                                    </NeuButton>
                                </div>
                            </div>
                        </div>
                    </NeuCard>
                )}

                {!queued && restoredAt && editableHere && (
                    <NeuCard className="mb-4 !shadow-neu-in">
                        <div className="flex items-start gap-3">
                            <span className="neu-inset w-9 h-9 flex-none grid place-items-center text-neu-info font-bold" aria-hidden="true">
                                ✎
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-bold">Draft dari perangkat ini dipulihkan</p>
                                <p className="text-xs text-neu-sub mt-0.5">
                                    Isian terakhir disimpan {formatSavedAt(restoredAt)}. Lanjutkan mengisi lalu Submit.
                                </p>
                                <button type="button" onClick={discardDraft} className="text-xs font-semibold text-neu-bad mt-1.5">
                                    Buang draft
                                </button>
                            </div>
                        </div>
                    </NeuCard>
                )}

                {/*
                    Riwayat dokumen: timeline ascending (terlama di atas).
                    Menampilkan pembuatan dokumen, penolakan, tanda tangan, dan update.
                    Bentuknya toggle supaya tidak menggeser checklist turun.
                    Card bergaya notifikasi (inset + ikon).
                */}
                {existing?.timeline?.length > 0 && (
                    <NeuCard className="mb-4 !shadow-neu-in">
                        <button
                            type="button"
                            onClick={() => setShowHistory((open) => !open)}
                            aria-expanded={showHistory}
                            aria-controls="pm-document-history"
                            className="flex w-full items-center gap-3 text-left rounded-neu-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-neu-accent/40"
                        >
                            <span
                                className="neu-inset w-9 h-9 flex-none grid place-items-center text-neu-accent font-bold"
                                aria-hidden="true"
                            >
                                i
                            </span>
                            <span className="min-w-0 flex-1">
                                <b className="block text-sm">Riwayat dokumen</b>
                                <span className="block text-xs text-neu-sub truncate">
                                    {existing.timeline.length} event · {existing.timeline[0]?.time} – {existing.timeline[existing.timeline.length - 1]?.time}
                                </span>
                            </span>
                            <svg
                                className={`w-4 h-4 flex-none text-neu-sub transition-transform duration-150 ${
                                    showHistory ? 'rotate-180' : ''
                                }`}
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                    clipRule="evenodd"
                                />
                            </svg>
                        </button>

                        {showHistory && (
                            <div id="pm-document-history" className="mt-3 pt-3 border-t border-neu-sub/15 space-y-3">
                                {existing.timeline.map((event, idx) => (
                                    <div key={idx} className="flex flex-col gap-1.5">
                                        <div className="flex items-start gap-3">
                                            <span className="neu-inset w-7 h-7 flex-none grid place-items-center text-[10px] font-bold text-neu-sub">
                                                {idx + 1}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <span className="font-semibold text-sm">[{event.time}]</span>
                                                    <span className={`text-sm ${
                                                        event.type === 'created' ? 'text-neu-accent' :
                                                        event.type === 'rejected' ? 'text-neu-bad' :
                                                        event.type === 'signed' ? 'text-neu-info' :
                                                        'text-neu-sub'
                                                    }`}>
                                                        {event.label}
                                                    </span>
                                                    {event.by && (
                                                        <span className="text-xs text-neu-sub">oleh {event.by}</span>
                                                    )}
                                                </div>
                                                {event.detail && (
                                                    <p className="text-xs text-neu-sub mt-1 whitespace-pre-wrap">{event.detail}</p>
                                                )}
                                                {event.note && (
                                                    <p className="text-xs text-neu-sub mt-1 italic">Catatan: {event.note}</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </NeuCard>
                )}

                {/* Progress */}
                <NeuCard className="mb-4">
                    <NeuTrack percentage={progress} />
                    <div className="flex items-center justify-center gap-2 mt-2">
                        <p className="text-xs text-neu-sub">{progress}% terisi</p>
                        {chain?.status && (
                            <NeuPill variant={
                                chain.status === 'approved' ? 'done'
                                    : chain.status === 'rejected' ? 'issue'
                                        : chain.awaiting ? 'progress' : 'todo'
                            }>
                                {chain.status_label}
                            </NeuPill>
                        )}
                    </div>
                </NeuCard>

                {readOnly && (
                    <NeuCard className="mb-4 !shadow-neu-in">
                        <p className="text-sm text-neu-sub">
                            {lockedByQueue
                                ? 'Checklist sudah disubmit dari perangkat ini dan dikunci sampai terkirim ke server.'
                                : isFuturePeriod
                                ? 'Periode ini belum tiba.'
                                : (chain?.canSign || chain?.canReject)
                                    ? 'Anda membuka checklist ini dalam mode tinjau. Isinya tidak dapat diubah; gunakan panel Persetujuan di bawah untuk menyetujui atau menolak.'
                                    : 'Anda tidak memiliki izin mengisi checklist.'}
                        </p>
                    </NeuCard>
                )}

                {/* Items */}
                <form onSubmit={handleSubmit}>
                    {formItems.map((item, index) => {
                        const showCategory = index === 0 || formItems[index - 1].category !== item.category;
                        return (
                            <div key={index}>
                                {showCategory && (
                                    <div className="text-[13px] font-bold text-neu-sub mt-5 mb-3 px-1">
                                        {item.category}
                                    </div>
                                )}
                                <NeuCard className="mb-3">
                                    <div className="flex items-center justify-between gap-2 mb-3">
                                        <b className="text-sm">{item.item_name}</b>
                                        {item.spec && (
                                            <span className="neu-inset px-2.5 py-1 text-[11px] text-neu-sub flex-none">
                                                {item.spec}
                                            </span>
                                        )}
                                    </div>
                                    <label htmlFor={`actual-${index}`} className="block text-xs text-neu-sub mb-1.5">
                                        Aktual *
                                    </label>
                                    <input
                                        id={`actual-${index}`}
                                        type="text"
                                        value={item.actual}
                                        onChange={(e) => updateItem(index, 'actual', e.target.value)}
                                        placeholder="Nilai aktual"
                                        className={`neu-input ${error && !item.actual ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]' : ''}`}
                                        disabled={readOnly}
                                    />
                                    <div className="flex gap-2.5 flex-wrap mt-3.5">
                                        {actions.map((action) => (
                                            <NeuChip
                                                key={action.key}
                                                active={item[action.key]}
                                                onClick={() => !readOnly && toggleAction(index, action.key)}
                                                disabled={readOnly}
                                            >
                                                {action.label}
                                            </NeuChip>
                                        ))}
                                    </div>

                                    {/*
                                        Catatan pergantian hanya muncul ketika aksi
                                        "Perlu Pergantian" dicentang, dan wajib diisi:
                                        inilah yang menjelaskan bagian mana yang
                                        diganti, bukan sekadar tandai "Perlu
                                        Pergantian" tanpa keterangan.
                                    */}
                                    {item.act_replace && (
                                        <div className="mt-3.5">
                                            <label
                                                htmlFor={`replace-note-${index}`}
                                                className="block text-xs text-neu-sub mb-1.5"
                                            >
                                                Detail Bagian yang Diganti{' '}
                                                <span className="text-neu-bad">*</span>
                                            </label>
                                            <textarea
                                                id={`replace-note-${index}`}
                                                value={item.replace_note ?? ''}
                                                onChange={(e) => {
                                                    updateItem(index, 'replace_note', e.target.value);
                                                    if (error) setError('');
                                                }}
                                                placeholder="Contoh: Bearing 6204, seal pompa"
                                                rows={2}
                                                maxLength={255}
                                                disabled={readOnly}
                                                className={`neu-input min-h-[64px] resize-y ${
                                                    error && !String(item.replace_note ?? '').trim()
                                                        ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]'
                                                        : ''
                                                }`}
                                            />
                                            <p className="text-xs text-neu-sub mt-1">
                                                Sebutkan komponen yang diganti supaya riwayat PM jelas.
                                            </p>
                                        </div>
                                    )}
                                </NeuCard>
                            </div>
                        );
                    })}

                    {/* General note */}
                    {!readOnly && (
                        <NeuCard className="mb-4">
                            <label htmlFor="general_note" className="block text-xs text-neu-sub mb-1.5">
                                Catatan Umum
                            </label>
                            <textarea
                                id="general_note"
                                value={data.general_note}
                                onChange={(e) => setField('general_note', e.target.value)}
                                placeholder="Catatan tambahan (opsional)"
                                className="neu-input min-h-[80px] resize-y"
                                rows={3}
                            />
                        </NeuCard>
                    )}

                    {/* Revision reason */}
                    {!readOnly && existing && (
                        <NeuCard className="mb-4">
                            <label htmlFor="revision_reason" className="block text-xs text-neu-sub mb-1.5">
                                Alasan Revisi
                            </label>
                            <input
                                id="revision_reason"
                                type="text"
                                value={data.revision_reason}
                                onChange={(e) => setField('revision_reason', e.target.value)}
                                placeholder="Jika revisi, jelaskan alasan"
                                className="neu-input"
                            />
                        </NeuCard>
                    )}

                    {(error || submitError) && (
                        <p role="alert" className="text-sm text-neu-bad font-semibold mb-3 text-center">
                            {error || submitError}
                        </p>
                    )}

                    {showTechnicianPanel && (
                        <NeuCard className="mb-4">
                            <b className="text-sm block mb-0.5">Tanda Tangan Teknisi/Pemeriksa</b>
                            <p className="text-xs text-neu-sub mb-4">
                                Pilih <b>Data Saya</b> untuk memakai nama &amp; tanda tangan akun Anda, atau <b>Isi Manual</b>{' '}
                                untuk mengetik nama dan menggambar tanda tangan pemeriksa.
                            </p>

                            {!canSignTechnician ? (
                                <p className="text-sm text-neu-sub">
                                    Akun Anda belum punya izin menandatangani tahap teknisi, jadi checklist ini belum bisa
                                    dikirim untuk approval. Hubungi admin untuk membuka izin tersebut.
                                </p>
                            ) : (
                                <>
                                    <div className="flex flex-wrap gap-2 mb-4" role="radiogroup" aria-label="Identitas penanda tangan">
                                        <NeuChip
                                            size="sm"
                                            role="radio"
                                            aria-checked={signerMode === 'self'}
                                            active={signerMode === 'self'}
                                            onClick={() => !sending && changeSignerMode('self')}
                                        >
                                            Data Saya
                                        </NeuChip>
                                        <NeuChip
                                            size="sm"
                                            role="radio"
                                            aria-checked={signerMode === 'manual'}
                                            active={signerMode === 'manual'}
                                            onClick={() => !sending && changeSignerMode('manual')}
                                        >
                                            Isi Manual
                                        </NeuChip>
                                    </div>

                                    {signerMode === 'self' ? (
                                        <>
                                            <p className="text-xs text-neu-sub mb-4">
                                                Nama Teknisi <span className="text-neu-bad">*</span>
                                                <b className="block text-sm text-neu-text mt-0.5">{accountName}</b>
                                                <span className="block text-[11px] mt-0.5">Diambil otomatis dari akun Anda.</span>
                                            </p>

                                            {savedSignature && (
                                                <div className="flex flex-wrap gap-2 mb-3">
                                                    <NeuChip size="sm" active={useSaved} onClick={() => !sending && changeUseSaved(true)}>
                                                        Tanda tangan tersimpan
                                                    </NeuChip>
                                                    <NeuChip size="sm" active={!useSaved} onClick={() => !sending && changeUseSaved(false)}>
                                                        Gambar baru
                                                    </NeuChip>
                                                </div>
                                            )}

                                            {usingSavedSignature ? (
                                                <>
                                                    <p className="text-xs text-neu-sub mb-1.5">
                                                        Tanda tangan dari halaman Profile akan dipakai otomatis.
                                                    </p>
                                                    <div className="neu-inset rounded-neu-sm p-3 flex items-center justify-center min-h-[90px]">
                                                        {savedPreviewFailed ? (
                                                            <span className="text-xs text-neu-sub text-center">
                                                                Pratinjau tidak tersedia tanpa jaringan. Tanda tangan tersimpan
                                                                tetap dipakai saat checklist terkirim.
                                                            </span>
                                                        ) : (
                                                            <img
                                                                src={`${savedSignature.url}?${savedSignature.version}`}
                                                                alt="Tanda tangan tersimpan"
                                                                className="max-h-28"
                                                                onError={() => setSavedPreviewFailed(true)}
                                                            />
                                                        )}
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    {!savedSignature && (
                                                        <p className="text-xs text-neu-sub mb-2">
                                                            Belum ada tanda tangan tersimpan di Profile. Gambar di bawah ini.
                                                        </p>
                                                    )}
                                                    <NeuSignaturePad
                                                        ref={padRef}
                                                        disabled={sending}
                                                        error={Boolean(error) && !hasSignature}
                                                        onChange={(value) => setHasSignature(Boolean(value))}
                                                        initialDataUrl={signatureSeed}
                                                        onStrokeEnd={(value) => {
                                                            signatureRef.current = value;
                                                            dirtyRef.current = true;
                                                            persistDraft();
                                                        }}
                                                    />
                                                    <label className="flex items-start gap-2 mt-3 text-xs text-neu-sub cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            checked={saveToProfile}
                                                            onChange={(e) => {
                                                                dirtyRef.current = true;
                                                                setSaveToProfile(e.target.checked);
                                                            }}
                                                            disabled={sending}
                                                            className="mt-1 accent-neu-accent"
                                                        />
                                                        <span>
                                                            Simpan tanda tangan ini di Profile saya agar berikutnya
                                                            terisi otomatis.
                                                        </span>
                                                    </label>
                                                </>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            <label htmlFor="technician_name" className="block text-xs text-neu-sub mb-1.5">
                                                Nama Teknisi <span className="text-neu-bad">*</span>
                                            </label>
                                            <input
                                                id="technician_name"
                                                type="text"
                                                value={data.technician_name}
                                                onChange={(e) => {
                                                    setField('technician_name', e.target.value);
                                                    if (error) setError('');
                                                }}
                                                placeholder="Tulis nama lengkap teknisi/pemeriksa"
                                                maxLength={100}
                                                autoComplete="off"
                                                className={`neu-input mb-4 ${error && !nameFilled ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]' : ''}`}
                                            />

                                            <NeuSignaturePad
                                                ref={padRef}
                                                disabled={sending}
                                                error={Boolean(error) && !hasSignature}
                                                onChange={(value) => setHasSignature(Boolean(value))}
                                                initialDataUrl={signatureSeed}
                                                onStrokeEnd={(value) => {
                                                    signatureRef.current = value;
                                                    dirtyRef.current = true;
                                                    persistDraft();
                                                }}
                                            />
                                        </>
                                    )}

                                    {/*
                                        Teknisi menunjuk siapa yang menyetujui
                                        berikutnya, jadi tahap 2 tidak terbuka
                                        untuk semua orang dan antrean tidak
                                        menumpuk di satu akun PIC saja.
                                    */}
                                    <label htmlFor="pic_user_id" className="block text-xs text-neu-sub mt-4 mb-1.5">
                                        User PIC yang akan menyetujui{' '}
                                        <span className="text-neu-bad">*</span>
                                    </label>
                                    <select
                                        id="pic_user_id"
                                        value={data.pic_user_id}
                                        onChange={(e) => {
                                            setField('pic_user_id', e.target.value);
                                            if (error) setError('');
                                        }}
                                        disabled={sending}
                                        aria-describedby="pic_user_id-hint"
                                        aria-invalid={Boolean(error) && !picChosen}
                                        className={`neu-input mb-1.5 ${error && !picChosen ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]' : ''}`}
                                    >
                                        <option value="">— Pilih User PIC —</option>
                                        {(picCandidates ?? []).map((pic) => (
                                            <option key={pic.id} value={pic.id}>
                                                {pic.name}
                                            </option>
                                        ))}
                                    </select>
                                    <p id="pic_user_id-hint" className="text-xs text-neu-sub">
                                        {picCandidates.length === 0
                                            ? 'Belum ada akun User PIC. Minta admin menambahkannya sebelum checklist dikirim.'
                                            : 'Hanya User PIC yang dipilih di sini yang bisa menyetujui tahap ini.'}
                                    </p>

                                    <label htmlFor="pm-note" className="block text-xs text-neu-sub mt-4 mb-1.5">
                                        Catatan (opsional)
                                    </label>
                                    <textarea
                                        id="pm-note"
                                        value={data.note}
                                        onChange={(e) => setField('note', e.target.value)}
                                        rows={2}
                                        maxLength={500}
                                        placeholder="Catatan tambahan (opsional)"
                                        className="neu-input min-h-[64px] resize-y"
                                    />
                                </>
                            )}
                        </NeuCard>
                    )}

                    {showTechnicianPanel && canSignTechnician && (
                        <>
                            <NeuButton
                                type="submit"
                                variant="primary"
                                className="w-full !py-4 text-base"
                                disabled={sending || !nameFilled || !signatureReady || !picChosen}
                            >
                                {sending
                                    ? 'Mengirim...'
                                    : existing ? 'Submit & Tanda tangan' : 'Submit Checklist'}
                            </NeuButton>

                            {/*
                                Tombol sengaja mati selama nama atau tanda tangan
                                kosong supaya tidak ada PM terkirim tanpa bukti
                                siapa yang mengerjakan dan menyetujuinya.
                            */}
                            <p className="text-xs text-neu-sub text-center mt-2.5">{submitHint}</p>
                        </>
                    )}

                    {/*
                        Checklist tidak boleh diubah setelah ditandatangani: isi yang
                        disetujui approver harus sama persis dengan isi yang direview.
                    */}
                    {!readOnly && activeStage && activeStage.value !== 'technician' && (
                        <NeuCard className="mb-4 !shadow-neu-in mt-4">
                            <p className="text-xs text-neu-sub">
                                Checklist sudah ditandatangani dan sedang berjalan di rantai approval, jadi isinya
                                dikunci. Anda masih bisa membuka halaman ini untuk melihat tahap persetujuan.
                            </p>
                        </NeuCard>
                    )}
                </form>

                {chain?.stages?.length > 0 && (
                    <NeuCard className="mb-4">
                        <b className="text-sm block mb-3">Rantai Persetujuan</b>
                        <SignatureChain
                            stages={chain.stages}
                            awaiting={chain.awaiting}
                            statusLabel={chain.status_label}
                            awaitingLabel={chain.awaiting_label}
                        />
                    </NeuCard>
                )}

                {/*
                    Panel tahap PIC/Atasan tetap di luar <form> checklist, supaya
                    Inertia tidak ikut mengirim ulang payload checklist yang
                    sudah terkunci. Tahap teknisi tidak muncul di sini karena
                    panelnya sudah menyatu dengan form di atas.
                */}
                {activeStage
                    && activeStage.value !== 'technician'
                    && !approvalDone
                    && !isFuturePeriod
                    && (
                        <SignatureActions
                            recordId={existing?.id}
                            stage={activeStage}
                            canSign={chain.canSign}
                            canReject={chain.canReject}
                            signerName={auth.user.name}
                            savedSignature={savedSignature}
                        />
                    )}



            </div>
        </AuthenticatedLayout>
    );
}
