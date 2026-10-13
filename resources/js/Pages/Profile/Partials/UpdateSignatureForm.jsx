import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuSignaturePad from '@/Components/NeuSignaturePad';
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

/**
 * Kartu "Tanda Tangan Tersimpan" di halaman Profile.
 *
 * User menggambar tanda tangannya sekali, hasilnya disimpan sebagai PNG dan
 * dipakai langsung di panel persetujuan PM (tanpa menggambar ulang). Kartu ini
 * menampilkan preview tanda tangan yang aktif, tombol ganti (membuka canvas
 * untuk menggambar ulang), dan tombol hapus.
 *
 * `savedSignature` datang dari ProfileController@edit: null kalau belum pernah
 * menyimpan, atau `{ url, version }` untuk preview. `version` dipakai untuk
 * mem-bypass cache browser setelah gambar diganti.
 */
export default function UpdateSignatureForm({ savedSignature = null }) {
    const padRef = useRef(null);
    const [editing, setEditing] = useState(false);
    const [hasInk, setHasInk] = useState(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [status, setStatus] = useState('');

    const hasSaved = savedSignature !== null;
    const previewUrl = hasSaved ? `${savedSignature.url}?${savedSignature.version}` : null;

    const startEditing = () => {
        setEditing(true);
        setHasInk(false);
        setError('');
        setStatus('');
    };

    const cancelEditing = () => {
        setEditing(false);
        setHasInk(false);
        setError('');
        padRef.current?.clear();
    };

    const handleSave = () => {
        const dataUrl = padRef.current?.getDataUrl();

        if (!dataUrl) {
            setError('Gambar tanda tangan wajib digambar.');
            return;
        }

        setError('');
        setBusy(true);

        router.post(
            route('profile.signature.store'),
            { signature: dataUrl },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setBusy(false);
                    setError(Object.values(errors)[0] ?? 'Gagal menyimpan tanda tangan.');
                },
                onSuccess: () => {
                    setBusy(false);
                    setEditing(false);
                    setHasInk(false);
                    padRef.current?.clear();
                    setStatus('Tanda tangan tersimpan dan siap dipakai untuk persetujuan.');
                    window.setTimeout(() => setStatus(''), 4000);
                },
            },
        );
    };

    const handleDelete = () => {
        if (!confirm('Hapus tanda tangan tersimpan dari profil Anda? Persetujuan yang sudah ditandatangani tidak terpengaruh.')) {
            return;
        }

        setBusy(true);
        setError('');

        router.delete(route('profile.signature.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                setBusy(false);
                setStatus('Tanda tangan tersimpan dihapus.');
                window.setTimeout(() => setStatus(''), 4000);
            },
            onError: () => setBusy(false),
        });
    };

    return (
        <NeuCard>
            <header className="mb-4">
                <h2 className="text-lg font-bold text-neu-text">Tanda Tangan Tersimpan</h2>
                <p className="mt-1 text-sm text-neu-sub">
                    Simpan tanda tangan (PNG) sekali di sini agar bisa dipakai langsung untuk persetujuan,
                    tanpa menggambar ulang.
                </p>
            </header>

            {hasSaved && !editing && (
                <>
                    <p className="text-xs text-neu-sub mb-1.5">Tanda tangan aktif</p>
                    <div className="neu-inset rounded-neu-sm p-3 flex items-center justify-center">
                        <img src={previewUrl} alt="Tanda tangan tersimpan" className="max-h-24" />
                    </div>
                    <div className="flex flex-wrap gap-2 mt-3">
                        <NeuButton size="sm" onClick={startEditing} disabled={busy}>
                            Ganti tanda tangan
                        </NeuButton>
                        <NeuButton size="sm" className="!text-neu-bad" onClick={handleDelete} disabled={busy}>
                            Hapus
                        </NeuButton>
                    </div>
                </>
            )}

            {(editing || !hasSaved) && (
                <>
                    <NeuSignaturePad
                        ref={padRef}
                        id="profile-signature-pad"
                        disabled={busy}
                        error={Boolean(error) && !hasInk}
                        onChange={(value) => setHasInk(Boolean(value))}
                    />
                    <p className="text-xs text-neu-sub mt-2">
                        Tanda tangan hanya tersimpan di akun Anda dan dipakai persetujuan atas nama Anda.
                    </p>

                    {hasSaved && (
                        <div className="flex flex-wrap gap-2 mb-3 mt-3">
                            <NeuButton size="sm" onClick={cancelEditing} disabled={busy}>
                                Batal
                            </NeuButton>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-3 mt-4">
                        <NeuButton
                            variant="primary"
                            disabled={busy || !hasInk}
                            onClick={handleSave}
                        >
                            {busy ? 'Menyimpan…' : 'Simpan tanda tangan'}
                        </NeuButton>
                    </div>
                </>
            )}

            {error && (
                <p role="alert" className="text-sm text-neu-bad font-semibold mt-3">
                    {error}
                </p>
            )}
            {status && (
                <p role="status" className="text-sm text-neu-accent font-semibold mt-3">
                    {status}
                </p>
            )}
        </NeuCard>
    );
}