import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Logika approve/tolak satu PM: gambar tanda tangan, catatan, dan status
 * request-nya.
 *
 * Dipisah dari tampilan supaya `SignatureActions` (kartu utuh di form PM) tidak
 * bercampur dengan request-nya. Antrean Persetujuan tidak memakai hook ini
 * lagi karena di sana tidak ada penandatanganan.
 *
 * Nama penanda tangan tidak lagi diketik di sini: tahap persetujuan memakai
 * nama akun login, jadi `signerName` diteruskan lewat props dan hanya dibaca.
 * Field nama hanya dibutuhkan backend untuk tahap teknisi, dan tahap itu tidak
 * memakai hook ini — teknisi menandatangani lewat submit form checklist.
 *
 * `signerName` tetap dikirim di payload sebagai nilai cadangan, dan
 * `signerNameFilled` dipakai untuk mematikan tombol kalau nama akunnya kosong.
 */
export default function useSignatureActions({ recordId, canSign = false, canReject = false, signerName = '' }) {
    const padRef = useRef(null);
    const [note, setNote] = useState('');
    const [hasSignature, setHasSignature] = useState(false);
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState('');

    const signerNameFilled = signerName.trim() !== '';
    const busy = processing !== '';

    /**
     * Pesan error dipisah dari penanda kolom, supaya hanya bagian yang
     * bermasalah yang diberi sorotan, bukan semua input sekaligus.
     */
    const message = error
        ? {
              text: error,
              field: !signerNameFilled && canSign ? 'name' : !hasSignature && canSign ? 'signature' : null,
          }
        : null;

    const handleSign = () => {
        if (!signerNameFilled) {
            setError('Nama penanda tangan tidak tersedia. Periksa nama akun Anda.');
            return;
        }

        const dataUrl = padRef.current?.getDataUrl();

        if (!dataUrl) {
            setError('Tanda tangan wajib digambar sebelum melanjutkan.');
            return;
        }

        setError('');
        setProcessing('sign');

        router.post(
            route('pm.sign', recordId),
            { signature: dataUrl, signer_name: signerName.trim(), note },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setProcessing('');
                    setError(Object.values(errors)[0] ?? 'Gagal menyimpan tanda tangan.');
                },
                onSuccess: () => {
                    setProcessing('');
                    setNote('');
                    setHasSignature(false);
                    padRef.current?.clear();
                },
            },
        );
    };

    const handleReject = () => {
        if (!note.trim()) {
            setError('Alasan penolakan wajib diisi agar teknisi tahu apa yang diperbaiki.');
            return;
        }

        setError('');
        setProcessing('reject');

        router.post(
            route('pm.reject', recordId),
            { note },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setProcessing('');
                    setError(Object.values(errors)[0] ?? 'Gagal menolak PM.');
                },
                onSuccess: () => {
                    setProcessing('');
                    setNote('');
                },
            },
        );
    };

    return {
        padRef,
        signerName,
        note,
        hasSignature,
        busy,
        processing,
        message,
        signerNameFilled,
        onNoteChange: (value) => {
            setNote(value);
            if (error) setError('');
        },
        onSignatureChange: (value) => {
            setHasSignature(Boolean(value));
            if (value && error) setError('');
        },
        canSign,
        canReject,
        handleSign,
        handleReject,
    };
}