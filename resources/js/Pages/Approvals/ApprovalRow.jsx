import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';
import NeuSignaturePad from '@/Components/NeuSignaturePad';
import NeuInput from '@/Components/NeuInput';
import useSignatureActions from '@/Components/useSignatureActions';

const STATUS_VARIANTS = { todo: 'todo', progress: 'progress', done: 'done', issue: 'issue' };

/**
 * Satu baris antrean persetujuan.
 *
 * Nama penanda tangan dan gambar tanda tangan sengaja dipisah jadi dua kolom,
 * bukan ditumpuk di dalam kartu: kolom "Nama Penanda Tangan" menyatakan siapa
 * yang akan menandatangani, kolom "Tanda Tangan" menyatakan bukti tanda
 * tangannya, dan akibatnya yang sudah lewat tahap bisa dibandingkan berdampingan
 * tanpa harus menggambar ulang.
 *
 * Hooknya dipanggil di komponen ini, bukan di `Index` — state nama, canvas, dan
 * processing harus milik satu baris saja. Kalau state-nya diletakkan di
 * `Index`, mengetik di satu baris akan mengisi nama di semua baris.
 */
export default function ApprovalRow({ record }) {
    const actionable = record.can_sign || record.can_reject;

    const {
        padRef,
        name,
        note,
        hasSignature,
        busy,
        processing,
        message,
        nameFilled,
        onNameChange,
        onNoteChange,
        onSignatureChange,
        handleSign,
        handleReject,
    } = useSignatureActions({
        recordId: record.id,
        canSign: record.can_sign,
        canReject: record.can_reject,
    });

    // Satu baris bisa memuat beberapa canvas; tanpa prefiks, `htmlFor` pada
    // semua input nama akan menunjuk ke input baris pertama.
    const prefix = `pm-${record.id}-`;
    const nameId = `${prefix}signer-name`;
    const noteId = `${prefix}sign-note`;
    const padId = `${prefix}signature-pad`;

    return (
        <tr>
            <td>
                <div className="flex items-start gap-3">
                    <span className="neu-inset w-11 h-11 flex-none grid place-items-center font-bold text-[11px] text-neu-accent">
                        {record.sub_category ?? '—'}
                    </span>
                    <div className="min-w-0">
                        <b className="block text-sm truncate">{record.name}</b>
                        <span className="block text-xs text-neu-sub truncate">
                            {record.code}
                            {record.location && ` · ${record.location}`}
                        </span>
                    </div>
                </div>
            </td>

            <td>
                <span className="block text-xs whitespace-nowrap">
                    {record.period_label} {record.year}
                </span>
                <span className="block text-xs text-neu-sub truncate max-w-[14rem]">
                    {record.technician_name ?? 'Tanpa teknisi'}
                </span>
                {/*
                    Technician menunjuk User PIC sebelum submit. Ditampilkan di
                    bawah nama teknisi karena dua-duanya pertanyaan yang sama:
                    "siapa yang sudah menangani, siapa yang menunggu".
                */}
                <span className="block text-xs text-neu-sub truncate max-w-[14rem]">
                    PIC:{' '}
                    <span className={record.assigned_pic ? 'text-neu-accent font-semibold' : ''}>
                        {record.assigned_pic?.name ?? 'belum ditunjuk'}
                    </span>
                </span>
            </td>

            <td>
                <NeuPill variant={STATUS_VARIANTS[record.status] ?? 'todo'}>
                    {record.status_label}
                </NeuPill>
                <span className="block text-xs text-neu-sub mt-1.5 whitespace-nowrap">
                    Menunggu {record.awaiting_label}
                </span>
                <span className="block text-xs text-neu-sub whitespace-nowrap">
                    {record.filled_percent}% terisi
                    {record.revision_count > 0 && ` · Revisi ${record.revision_count}×`}
                </span>
            </td>

            {!actionable ? (
                <td colSpan={3}>
                    <span className="text-xs text-neu-sub">
                        Tidak perlu tindakan Anda pada tahap ini.
                    </span>
                </td>
            ) : (
                <>
                    <td>
                        <label htmlFor={nameId} className="block text-xs text-neu-sub mb-1.5">
                            Nama Penanda Tangan <span className="text-neu-bad">*</span>
                        </label>
                        <NeuInput
                            id={nameId}
                            type="text"
                            value={name}
                            onChange={(e) => onNameChange(e.target.value)}
                            placeholder={record.awaiting_stage?.short_label ?? 'Nama lengkap'}
                            maxLength={100}
                            autoComplete="off"
                            disabled={busy}
                            aria-required="true"
                            aria-invalid={message?.field === 'name'}
                            aria-describedby={message ? `${nameId}-error` : undefined}
                            className={`min-w-[13rem] ${
                                message?.field === 'name'
                                    ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]'
                                    : ''
                            }`}
                        />
                    </td>

                    <td>
                        {/*
                            Kanvas `w-full` di dalam sel tabel akan ikut menyusut
                            ke lebar kolom, jadi lebar minimum-nya dipasang di sini
                            supaya ada ruang yang cukup untuk menandatangani.
                        */}
                        <div className="min-w-[17rem]">
                            <NeuSignaturePad
                                ref={padRef}
                                id={padId}
                                label="Tanda Tangan"
                                disabled={busy}
                                error={message?.field === 'signature'}
                                onChange={onSignatureChange}
                            />
                        </div>
                    </td>

                    <td>
                        <label htmlFor={noteId} className="block text-xs text-neu-sub mb-1.5">
                            {record.can_reject ? 'Catatan' : 'Catatan (opsional)'}
                        </label>
                        <textarea
                            id={noteId}
                            value={note}
                            onChange={(e) => onNoteChange(e.target.value)}
                            rows={2}
                            maxLength={500}
                            disabled={busy}
                            placeholder={record.can_reject ? 'Alasan penolakan' : 'Opsional'}
                            className="neu-input min-h-[64px] resize-y min-w-[12rem]"
                        />
                    </td>
                </>
            )}

            <td>
                <div className="flex flex-col gap-2.5 min-w-[12rem]">
                    {record.can_sign && (
                        <NeuButton
                            variant="primary"
                            disabled={busy || !nameFilled || !hasSignature}
                            onClick={handleSign}
                        >
                            {processing === 'sign' ? 'Menyimpan…' : 'Setujui'}
                        </NeuButton>
                    )}

                    {record.can_reject && (
                        <NeuButton
                            className="text-neu-bad"
                            disabled={busy}
                            onClick={handleReject}
                        >
                            {processing === 'reject' ? 'Mengirim…' : 'Tolak'}
                        </NeuButton>
                    )}

                    <NeuButton
                        href={route('machines.pm.create', {
                            machine: record.machine_id,
                            year: record.year,
                            period: record.period,
                        })}
                    >
                        Buka Checklist
                    </NeuButton>

                    {message && (
                        <p id={`${nameId}-error`} role="alert" className="text-xs text-neu-bad font-semibold">
                            {message.text}
                        </p>
                    )}

                    {/* Penjelasan kenapa tombol masih mati, karena pada tabel
                        nama dan tanda tangan jauh dari tombolnya. */}
                    {!busy && record.can_sign && (!nameFilled || !hasSignature) && (
                        <p className="text-[11px] text-neu-sub">
                            {!nameFilled && !hasSignature
                                ? 'Nama dan tanda tangan wajib diisi.'
                                : !nameFilled
                                    ? 'Nama wajib diisi.'
                                    : 'Tanda tangan wajib digambar.'}
                        </p>
                    )}
                </div>
            </td>
        </tr>
    );
}