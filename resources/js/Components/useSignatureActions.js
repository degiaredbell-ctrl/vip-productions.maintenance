import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Logika approve/tolak satu PM: nama manual, gambar tanda tangan, catatan, dan
 * status request-nya.
 *
 * Dipisah dari tampilan karena satu tahap ini dirender di dua tempat dengan
 * bentuk berbeda: sebagai kartu utuh di `Pm/Form`, dan sebagai beberapa kolom
 * terpisah (Nama / Tanda Tangan / Catatan / Aksi) di baris tabel antrean
 * Persetujuan. Menyalin request ke dua tempat berisiko membuat satu jalur
 * validating mandatory dan jalur lain tidak.
 *
 * Nama manual dan gambar tanda tangan keduanya wajib di semua tahap: backend
 * `SignPmRecordRequest` menolak request yang salah satunya kosong, jadi tombol
 * juga dimatikan di sini supaya frontend tidak menawarkan aksi yang pasti gagal.
 */
export default function useSignatureActions({ recordId, canSign = false, canReject = false }) {
    const padRef = useRef(null);
    const [note, setNote] = useState('');
    const [signerName, setSignerName] = useState('');
    const [hasSignature, setHasSignature] = useState(false);
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState('');

    const nameFilled = signerName.trim() !== '';
    const busy = processing !== '';

    /**
     * Pesan error dipisah dari penanda kolom, supaya input nama merah hanya
     * kalau memang namanya yang kosong — bukan karena tanda tangannya belum
     * digambar.
     */
    const message = error
        ? {
              text: error,
              field: !nameFilled && canSign ? 'name' : !hasSignature && canSign ? 'signature' : null,
          }
        : null;

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
                    setSignerName('');
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
        name: signerName,
        note,
        hasSignature,
        busy,
        processing,
        message,
        nameFilled,
        onNameChange: (value) => {
            setSignerName(value);
            if (error) setError('');
        },
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