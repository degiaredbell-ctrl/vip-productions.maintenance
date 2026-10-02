import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuSignaturePad from '@/Components/NeuSignaturePad';

/**
 * Panel aksi tanda tangan untuk satu tahap persetujuan.
 *
 * Satu komponen untuk tahap 1 (tanda tangan teknisi), tahap 2 (User PIC), dan
 * tahap 3 (Atasan) karena yang berbeda hanya label tombol dan apakah tombol
 * "Tolak" tersedia. Backend tetap memvalidasi tahap mana yang boleh dikerjakan.
 */
export default function SignatureActions({ recordId, stage, canSign, canReject }) {
    const padRef = useRef(null);
    const [note, setNote] = useState('');
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState('');

    const handleSign = () => {
        const dataUrl = padRef.current?.getDataUrl();

        if (!dataUrl) {
            setError('Tanda tangan wajib digambar sebelum melanjutkan.');
            return;
        }

        setError('');
        setProcessing('sign');

        router.post(route('pm.sign', recordId), { signature: dataUrl, note }, {
            preserveScroll: true,
            onError: (errors) => {
                setProcessing('');
                setError(Object.values(errors)[0] ?? 'Gagal menyimpan tanda tangan.');
            },
            onSuccess: () => {
                setProcessing('');
                setNote('');
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
                    <NeuSignaturePad
                        ref={padRef}
                        disabled={processing !== ''}
                        onChange={(value) => value && setError('')}
                    />
                </div>
            )}

            <label htmlFor="sign-note" className="block text-xs text-neu-sub mb-1.5">
                {canReject ? 'Catatan' : 'Catatan (opsional)'}
            </label>
            <textarea
                id="sign-note"
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
                    <NeuButton
                        variant="primary"
                        className="w-full !py-3.5"
                        disabled={processing !== ''}
                        onClick={handleSign}
                    >
                        {processing === 'sign'
                            ? 'Menyimpan tanda tangan…'
                            : stage.value === 'technician'
                                ? 'Tanda tangan & Kirim untuk Approval'
                                : 'Setujui & Tanda tangan'}
                    </NeuButton>
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
