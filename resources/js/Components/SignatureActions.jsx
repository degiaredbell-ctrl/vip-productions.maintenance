import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuSignaturePad from '@/Components/NeuSignaturePad';

/**
 * Panel aksi tanda tangan untuk tahap User PIC dan Atasan.
 *
 * Tahap 1 (teknisi) tidak memakai komponen ini: penandatanganannya ada di
 * akhir form checklist supaya teknisi cukup satu kali submit. Backend tetap
 * memvalidasi tahap mana yang boleh dikerjakan.
 *
 * `embedded` memasang panel ini di dalam kartu yang sudah ada (dipakai di
 * daftar Persetujuan), jadi kartu dan judulnya tidak digambar dua kali.
 * `idPrefix` wajib saat dipasang di daftar: halaman Persetujuan memuat banyak
 * panel sekaligus dan tanpa itu semua `id` input akan sama.
 */
export default function SignatureActions({ recordId, stage, canSign, canReject, embedded = false, idPrefix = '' }) {
    const nameId = `${idPrefix}signer-name`;
    const noteId = `${idPrefix}sign-note`;
    const padId = `${idPrefix}signature-pad`;

    const padRef = useRef(null);
    const [note, setNote] = useState('');
    const [signerName, setSignerName] = useState('');
    const [hasSignature, setHasSignature] = useState(false);
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState('');

    const nameFilled = signerName.trim() !== '';

    const handleSign = () => {
        if (!nameFilled) {
            setError('Nama penanda tangan wajib diisi.');
            return;
        }

        const dataUrl = padRef.current?.getDataUrl();

        if (!dataUrl) {
            setError('Tanda tangan wajib digambar sebelum melanjutkan.');
            return;
        }

        setError('');
        setProcessing('sign');

        router.post(route('pm.sign', recordId), { signature: dataUrl, signer_name: signerName.trim(), note }, {
            preserveScroll: true,
            onError: (errors) => {
                setProcessing('');
                setError(Object.values(errors)[0] ?? 'Gagal menyimpan tanda tangan.');
            },
            onSuccess: () => {
                setProcessing('');
                setNote('');
                setSignerName('');
                setHasSignature(false);
                padRef.current?.clear();
            },
        });
    };

    const handleReject = () => {
        if (!note.trim()) {
            setError('Alasan penolakan wajib diisi agar teknisi tahu apa yang diperbaiki.');
            return;
        }

        setError('');
        setProcessing('reject');

        router.post(route('pm.reject', recordId), { note }, {
            preserveScroll: true,
            onError: (errors) => {
                setProcessing('');
                setError(Object.values(errors)[0] ?? 'Gagal menolak PM.');
            },
            onSuccess: () => {
                setProcessing('');
                setNote('');
                padRef.current?.clear();
            },
        });
    };

    if (!canSign && !canReject) return null;

    const heading = embedded ? null : (
        <>
            <b className="text-sm block mb-0.5">{stage.label}</b>
            <p className="text-xs text-neu-sub mb-4">
                {canSign
                    ? 'Tanda tangan di bawah lalu tekan tombol untuk melanjutkan ke tahap berikutnya.'
                    : 'Anda dapat menolak PM ini dengan menyertakan alasan.'}
            </p>
        </>
    );

    return (
        <NeuCard className={embedded ? 'mt-4 !shadow-neu-in' : 'mb-4'}>
            {heading}

            {canSign && (
                <div className="mb-4">
                    <label htmlFor={nameId} className="block text-xs text-neu-sub mb-1.5">
                        Nama Penanda Tangan <span className="text-neu-bad">*</span>
                    </label>
                    <input
                        id={nameId}
                        type="text"
                        value={signerName}
                        onChange={(e) => {
                            setSignerName(e.target.value);
                            if (error) setError('');
                        }}
                        placeholder={`Tulis nama lengkap ${stage.short_label}`}
                        maxLength={100}
                        autoComplete="off"
                        disabled={processing !== ''}
                        className={`neu-input mb-4 ${error && !nameFilled ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]' : ''}`}
                    />

                    <NeuSignaturePad
                        ref={padRef}
                        id={padId}
                        disabled={processing !== ''}
                        error={Boolean(error) && !hasSignature}
                        onChange={(value) => {
                            setHasSignature(Boolean(value));
                            if (value && error) setError('');
                        }}
                    />
                </div>
            )}

            <label htmlFor={noteId} className="block text-xs text-neu-sub mb-1.5">
                {canReject ? 'Catatan' : 'Catatan (opsional)'}
            </label>
            <textarea
                id={noteId}
                value={note}
                onChange={(e) => {
                    setNote(e.target.value);
                    if (error) setError('');
                }}
                rows={2}
                maxLength={500}
                placeholder={canReject ? 'Tuliskan alasan penolakan' : 'Catatan tambahan (opsional)'}
                className="neu-input min-h-[64px] resize-y"
            />

            {error && (
                <p role="alert" className="text-sm text-neu-bad font-semibold mt-3">{error}</p>
            )}

            <div className="flex flex-col gap-2.5 mt-4">
                {canSign && (
                    <>
                        <NeuButton
                            variant="primary"
                            className="w-full !py-3.5"
                            disabled={processing !== '' || !nameFilled || !hasSignature}
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
                        {processing === '' && (!nameFilled || !hasSignature) && (
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
                        disabled={processing !== ''}
                        onClick={handleReject}
                    >
                        {processing === 'reject' ? 'Mengirim…' : 'Tolak & Kembalikan ke Teknisi'}
                    </NeuButton>
                )}
            </div>
        </NeuCard>
    );
}
