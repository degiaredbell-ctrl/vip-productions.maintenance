import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuSignaturePad from '@/Components/NeuSignaturePad';
import useSignatureActions from '@/Components/useSignatureActions';

/**
 * Panel aksi tanda tangan sebagai satu kartu utuh, untuk tahap User PIC dan
 * Atasan di halaman form PM.
 *
 * Tahap 1 (teknisi) tidak memakai komponen ini: penandatanganannya ada di akhir
 * form checklist supaya teknisi cukup satu kali submit. Backend tetap memvalidasi
 * tahap mana yang boleh dikerjakan.
 *
 * Antrean Persetujuan tidak memakai komponen ini — `Approvals/ApprovalRow`
 * memakai `useSignatureActions` yang sama supaya nama dan tanda tangannya bisa
 * berdiri sebagai kolom terpisah di tabel.
 */
export default function SignatureActions({ recordId, stage, canSign, canReject }) {
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
    } = useSignatureActions({ recordId, canSign, canReject });

    if (!canSign && !canReject) return null;

    return (
        <NeuCard className="mb-4">
            <b className="text-sm block mb-0.5">{stage.label}</b>
            <p className="text-xs text-neu-sub mb-4">
                {canSign
                    ? 'Tanda tangan di bawah lalu tekan tombol untuk melanjutkan ke tahap berikutnya.'
                    : 'Anda dapat menolak PM ini dengan menyertakan alasan.'}
            </p>

            {canSign && (
                <div className="mb-4">
                    <label htmlFor="signer-name" className="block text-xs text-neu-sub mb-1.5">
                        Nama Penanda Tangan <span className="text-neu-bad">*</span>
                    </label>
                    <input
                        id="signer-name"
                        type="text"
                        value={name}
                        onChange={(e) => onNameChange(e.target.value)}
                        placeholder={`Tulis nama lengkap ${stage.short_label}`}
                        maxLength={100}
                        autoComplete="off"
                        disabled={busy}
                        aria-invalid={message?.field === 'name'}
                        aria-describedby={message ? 'signature-error' : undefined}
                        className={`neu-input mb-4 ${
                            message?.field === 'name'
                                ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]'
                                : ''
                        }`}
                    />

                    <NeuSignaturePad
                        ref={padRef}
                        disabled={busy}
                        error={message?.field === 'signature'}
                        onChange={onSignatureChange}
                    />
                </div>
            )}

            <label htmlFor="sign-note" className="block text-xs text-neu-sub mb-1.5">
                {canReject ? 'Catatan' : 'Catatan (opsional)'}
            </label>
            <textarea
                id="sign-note"
                value={note}
                onChange={(e) => onNoteChange(e.target.value)}
                rows={2}
                maxLength={500}
                disabled={busy}
                placeholder={canReject ? 'Tuliskan alasan penolakan' : 'Catatan tambahan (opsional)'}
                className="neu-input min-h-[64px] resize-y"
            />

            {message && (
                <p id="signature-error" role="alert" className="text-sm text-neu-bad font-semibold mt-3">
                    {message.text}
                </p>
            )}

            <div className="flex flex-col gap-2.5 mt-4">
                {canSign && (
                    <>
                        <NeuButton
                            variant="primary"
                            className="w-full !py-3.5"
                            disabled={busy || !nameFilled || !hasSignature}
                            onClick={handleSign}
                        >
                            {processing === 'sign'
                                ? 'Menyimpan tanda tangan…'
                                : stage.value === 'pic'
                                    ? 'Setujui & Tanda tangan'
                                    : 'Tanda tangan & Setujui'}
                        </NeuButton>

                        {/* Sama seperti tahap teknisi: tombol mati sampai nama
                            dan tanda tangan terisi, jadi tidak ada approval
                            tanpa identitas penandatanganannya. */}
                        {!busy && (!nameFilled || !hasSignature) && (
                            <p className="text-xs text-neu-sub text-center">
                                {!nameFilled && !hasSignature
                                    ? 'Nama penanda tangan dan tanda tangan wajib diisi.'
                                    : !nameFilled
                                        ? 'Nama penanda tangan wajib diisi.'
                                        : 'Tanda tangan wajib digambar.'}
                            </p>
                        )}
                    </>
                )}

                {canReject && (
                    <NeuButton
                        className="w-full !py-3.5 text-neu-bad"
                        disabled={busy}
                        onClick={handleReject}
                    >
                        {processing === 'reject' ? 'Mengirim…' : 'Tolak & Kembalikan ke Teknisi'}
                    </NeuButton>
                )}
            </div>
        </NeuCard>
    );
}