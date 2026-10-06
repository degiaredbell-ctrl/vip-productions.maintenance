import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';
import NeuTrack from '@/Components/NeuTrack';
import NeuSignaturePad from '@/Components/NeuSignaturePad';
import SignatureActions from '@/Components/SignatureActions';
import SignatureChain from '@/Components/SignatureChain';
import { useRef, useState } from 'react';

export default function PmForm({ auth, machine, items, period, year, dashboardUrl, existing, isFuturePeriod, canFill, canSignTechnician, chain }) {
    const [formItems, setFormItems] = useState(items.map(item => ({ ...item })));
    const [error, setError] = useState('');
    const [submitError, setSubmitError] = useState('');
    const [hasSignature, setHasSignature] = useState(false);
    const padRef = useRef(null);

    const { data, setData, post, processing } = useForm({
        machine_id: machine.id,
        year: year,
        period: period,
        technician_name: '',
        general_note: existing?.general_note || '',
        revision_reason: '',
        note: '',
        items: formItems,
    });

    const updateItem = (index, field, value) => {
        const newItems = [...formItems];
        newItems[index] = { ...newItems[index], [field]: value };
        setFormItems(newItems);
        setData('items', newItems);
    };

    const toggleAction = (index, action) => {
        const newItems = [...formItems];
        newItems[index] = { ...newItems[index], [action]: !newItems[index][action] };
        setFormItems(newItems);
        setData('items', newItems);
    };

    const progress = Math.round(formItems.filter(item => item.actual).length / formItems.length * 100);

    const nameFilled = data.technician_name.trim() !== '';

    const handleSubmit = (e) => {
        e.preventDefault();
        const incomplete = formItems.some(item => !item.actual);
        if (incomplete) {
            setError('Lengkapi semua nilai Aktual');
            return;
        }

        if (!nameFilled) {
            setError('Nama teknisi wajib diisi.');
            return;
        }

        const signature = padRef.current?.getDataUrl();
        if (!signature) {
            setError('Tanda tangan wajib digambar sebelum melanjutkan.');
            return;
        }

        setError('');
        setSubmitError('');
        post(route('machines.pm.store', { machine: machine.id }), {
            preserveScroll: true,
            data: { signature },
            onError: (formErrors) => {
                setSubmitError(Object.values(formErrors)[0] ?? 'Gagal menyimpan checklist.');
            },
        });
    };

    const actions = [
        { key: 'act_clean', label: 'Bersihkan' },
        { key: 'act_repair', label: 'Perbaiki' },
        { key: 'act_lubricate', label: 'Lumasi' },
        { key: 'act_replace', label: 'Ganti' },
    ];

    const readOnly = !canFill || isFuturePeriod;

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
                    <NeuButton href={dashboardUrl ?? route('dashboard')} className="!px-3">
                        ‹ Kembali
                    </NeuButton>
                    <div>
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
                            {isFuturePeriod ? 'Periode ini belum tiba.' : 'Anda tidak memiliki izin mengisi checklist.'}
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
                                onChange={(e) => setData('general_note', e.target.value)}
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
                                onChange={(e) => setData('revision_reason', e.target.value)}
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
                                Checklist ditutup dengan satu submit: isi nama dan tanda tangan, lalu kirim untuk approval.
                            </p>

                            {!canSignTechnician ? (
                                <p className="text-sm text-neu-sub">
                                    Akun Anda belum punya izin menandatangani tahap teknisi, jadi checklist ini belum bisa
                                    dikirim untuk approval. Hubungi admin untuk membuka izin tersebut.
                                </p>
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
                                            setData('technician_name', e.target.value);
                                            if (error) setError('');
                                        }}
                                        placeholder="Tulis nama lengkap teknisi/pemeriksa"
                                        maxLength={100}
                                        autoComplete="off"
                                        className={`neu-input mb-4 ${error && !nameFilled ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]' : ''}`}
                                    />

                                    <NeuSignaturePad
                                        ref={padRef}
                                        disabled={processing}
                                        error={Boolean(error) && !hasSignature}
                                        onChange={(value) => setHasSignature(Boolean(value))}
                                    />

                                    <label htmlFor="pm-note" className="block text-xs text-neu-sub mt-4 mb-1.5">
                                        Catatan (opsional)
                                    </label>
                                    <textarea
                                        id="pm-note"
                                        value={data.note}
                                        onChange={(e) => setData('note', e.target.value)}
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
                                disabled={processing || !nameFilled || !hasSignature}
                            >
                                {processing
                                    ? 'Menyimpan...'
                                    : existing ? 'Submit & Tanda tangan' : 'Submit Checklist'}
                            </NeuButton>

                            {/*
                                Tombol sengaja mati selama nama atau tanda tangan
                                kosong supaya tidak ada PM terkirim tanpa bukti
                                siapa yang mengerjakan dan menyetujuinya.
                            */}
                            <p className="text-xs text-neu-sub text-center mt-2.5">
                                {!nameFilled && !hasSignature
                                    ? 'Nama teknisi dan tanda tangan wajib diisi sebelum submit.'
                                    : !nameFilled
                                        ? 'Nama teknisi wajib diisi sebelum submit.'
                                        : !hasSignature
                                            ? 'Tanda tangan wajib digambar sebelum submit.'
                                            : 'Setelah submit, checklist langsung diteruskan ke User PIC.'}
                            </p>
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
                        />
                    )}
            </div>
        </AuthenticatedLayout>
    );
}
