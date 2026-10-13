import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Logika approve/tolak satu PM: tanda tangan, catatan, dan status request-nya.
 *
 * Dipisah dari tampilan supaya `SignatureActions` (kartu utuh di form PM) tidak
 * bercampur dengan request-nya. Antrean Persetujuan tidak memakai hook ini
 * lagi karena di sana tidak ada penandatanganan.
 *
 * Sumber tanda tangan ada dua (dipilih lewat `mode`):
 *
 * - `saved`: memakai tanda tangan tersimpan di halaman Profile akun yang login
 *   (`use_saved_signature` di payload). Approve langsung, tanpa menggambar.
 * - `draw`: gambar baru di canvas (perilaku lama). Bisa sekaligus disimpan ke
 *   profil (`save_signature_to_profile`) untuk dipakai lagi berikutnya.
 *
 * Nama penanda tangan tidak lagi diketik di sini: tahap persetujuan memakai
 * nama akun login, jadi `signerName` diteruskan lewat props dan hanya dibaca.
 * Field nama hanya dibutuhkan backend untuk tahap teknisi, dan tahap itu tidak
 * memakai hook ini — teknisi menandatangani lewat submit form checklist.
 *
 * `signerName` tetap dikirim di payload sebagai nilai cadangan, dan
 * `signerNameFilled` dipakai untuk mematikan tombol kalau nama akunnya kosong.
 */
export default function useSignatureActions({
    recordId,
    canSign = false,
    canReject = false,
    signerName = '',
    savedSignature = null,
}) {
    const padRef = useRef(null);
    const [note, setNote] = useState('');
    const [hasSignature, setHasSignature] = useState(false);
    const [mode, setMode] = useState(savedSignature ? 'saved' : 'draw');
    const [saveToProfile, setSaveToProfile] = useState(false);
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState('');

    const signerNameFilled = signerName.trim() !== '';
    const busy = processing !== '';

    // Tanda tangan dianggap siap kalau mode yang dipilih terpenuhi: mode
    // `saved` cukup karena tersimpan di profil, mode `draw` butuh goresan di
    // canvas.
    const signatureReady = mode === 'saved' ? Boolean(savedSignature) : hasSignature;

    /**
     * Pesan error dipisah dari penanda kolom, supaya hanya bagian yang
     * bermasalah yang diberi sorotan, bukan semua input sekaligus.
     */
    const message = error
        ? {
              text: error,
              field: !signerNameFilled && canSign ? 'name' : !signatureReady && canSign ? 'signature' : null,
          }
        : null;

    const handleSign = () => {
        if (!signerNameFilled) {
            setError('Nama penanda tangan tidak tersedia. Periksa nama akun Anda.');
            return;
        }

        if (mode === 'saved') {
            if (!savedSignature) {
                setError('Anda belum menyimpan tanda tangan di halaman Profile.');
                return;
            }

            setError('');
            setProcessing('sign');

            router.post(
                route('pm.sign', recordId),
                { use_saved_signature: true, signer_name: signerName.trim(), note },
                {
                    preserveScroll: true,
                    onError: (errors) => {
                        setProcessing('');
                        setError(Object.values(errors)[0] ?? 'Gagal menyimpan tanda tangan.');
                    },
                    onSuccess: () => {
                        setProcessing('');
                        setNote('');
                    },
                },
            );

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
            {
                signature: dataUrl,
                signer_name: signerName.trim(),
                note,
                save_signature_to_profile: saveToProfile,
            },
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
        signatureReady,
        busy,
        processing,
        message,
        signerNameFilled,
        savedSignature,
        mode,
        saveToProfile,
        onNoteChange: (value) => {
            setNote(value);
            if (error) setError('');
        },
        onSignatureChange: (value) => {
            setHasSignature(Boolean(value));
            if (value && error) setError('');
        },
        onModeChange: (value) => {
            // Pad di-mount ulang setiap kali masuk "gambar baru" (dia tidak
            // dirender di mode "tersimpan"), jadi tinta lamanya hilang.
            // Reset penanda goresan saat transisi ke mode gambar baru, supaya
            // tombol tidak aktif pada pad yang ternyata kosong.
            if (value === 'draw' && mode !== 'draw') {
                setHasSignature(false);
            }
            setMode(value);
            if (error) setError('');
        },
        onSaveToProfileChange: (value) => {
            setSaveToProfile(Boolean(value));
            if (error) setError('');
        },
        canSign,
        canReject,
        handleSign,
        handleReject,
    };
}