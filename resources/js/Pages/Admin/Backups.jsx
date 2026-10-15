import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';
import Modal from '@/Components/Modal';
import ApplicationLogo from '@/Components/ApplicationLogo';

const formatDateTime = (value) => {
    if (!value) return '—';
    return value;
};

export default function Backups({ auth, backups = [], stats = {} }) {
    const [restoreTarget, setRestoreTarget] = useState(null);
    const [confirmText, setConfirmText] = useState('');
    const [restoring, setRestoring] = useState(false);
    const [creating, setCreating] = useState(false);

    const upload = useForm({ file: null, confirm: false });

    const createBackup = () => {
        setCreating(true);
        router.post(route('admin.backups.store'), {}, {
            preserveScroll: true,
            onFinish: () => setCreating(false),
        });
    };

    const openRestore = (backup) => {
        setRestoreTarget(backup);
        setConfirmText('');
    };

    const closeRestore = () => {
        setRestoreTarget(null);
        setConfirmText('');
    };

    const submitRestore = (e) => {
        e.preventDefault();
        setRestoring(true);
        router.post(route('admin.backups.restore', restoreTarget.id), { confirm: confirmText }, {
            preserveScroll: true,
            onSuccess: () => closeRestore(),
            onFinish: () => setRestoring(false),
        });
    };

    const destroy = (backup) => {
        if (!confirm(`Hapus backup ${backup.filename}? File akan dihapus permanen.`)) return;

        router.delete(route('admin.backups.destroy', backup.id), { preserveScroll: true });
    };

    const submitUpload = (e) => {
        e.preventDefault();
        upload.post(route('admin.backups.upload'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => upload.reset(),
        });
    };

    const confirmMatches = restoreTarget && confirmText === restoreTarget.filename;

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-6">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Backup &amp; Restore</h1>
                    <p className="text-neu-sub text-sm mt-1">
                        Cadangkan dan pulihkan database aplikasi (MySQL). Backup harian otomatis berjalan pukul {stats.schedule ?? '02:00'}.
                    </p>
                </div>

                <div className="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <NeuCard className="!p-4">
                        <p className="text-xs text-neu-sub">Jumlah Backup</p>
                        <p className="text-2xl font-bold mt-1">{stats.total ?? backups.length}</p>
                    </NeuCard>
                    <NeuCard className="!p-4">
                        <p className="text-xs text-neu-sub">Total Ukuran</p>
                        <p className="text-2xl font-bold mt-1">{stats.total_size_human ?? '0 B'}</p>
                    </NeuCard>
                    <NeuCard className="!p-4">
                        <p className="text-xs text-neu-sub">Backup Terakhir</p>
                        <p className="text-base font-bold mt-1">{formatDateTime(stats.last_at)}</p>
                    </NeuCard>
                </div>

                <div className="mb-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    <NeuButton
                        variant="primary"
                        onClick={createBackup}
                        disabled={creating}
                        className="!min-h-[44px]"
                    >
                        {creating ? 'Membuat backup…' : 'Buat Backup Sekarang'}
                    </NeuButton>
                    <p className="text-xs text-neu-sub">
                        Retensi: {stats.keep ?? 30} backup terbaru disimpan otomatis.
                    </p>
                </div>

                <NeuCard className="mb-4">
                    <h2 className="text-sm font-bold mb-1">Pulihkan dari File .sql</h2>
                    <p className="text-xs text-neu-bad mb-3">
                        Restore akan menimpa seluruh isi database saat ini. Pastikan Anda sudah membuat backup terbaru sebelum melanjutkan.
                    </p>
                    <form onSubmit={submitUpload} className="space-y-3">
                        <input
                            type="file"
                            accept=".sql"
                            onChange={(e) => upload.setData('file', e.target.files[0])}
                            className="neu-input file:mr-3 file:border-0 file:bg-transparent file:text-neu-accent text-sm"
                            aria-label="Berkas backup .sql"
                        />
                        {upload.errors.file && <p className="text-xs text-neu-bad">{upload.errors.file}</p>}
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={upload.data.confirm}
                                onChange={(e) => upload.setData('confirm', e.target.checked)}
                                className="rounded"
                            />
                            <span>Saya mengerti data saat ini akan ditimpa.</span>
                        </label>
                        {upload.errors.confirm && <p className="text-xs text-neu-bad">{upload.errors.confirm}</p>}
                        <div className="flex justify-end">
                            <NeuButton
                                type="submit"
                                variant="primary"
                                disabled={upload.processing || !upload.data.file || !upload.data.confirm}
                                className="!min-h-[44px]"
                            >
                                {upload.processing ? 'Memulihkan…' : 'Pulihkan Database'}
                            </NeuButton>
                        </div>
                    </form>
                </NeuCard>

                <NeuCard>
                    <h2 className="text-sm font-bold mb-3">Daftar Backup</h2>

                    {backups.length === 0 ? (
                        <div className="text-center py-12">
                            <ApplicationLogo className="w-16 h-16 mx-auto mb-4 text-neu-sub" />
                            <p className="text-neu-sub text-lg">Belum ada backup</p>
                            <p className="text-neu-sub text-sm mt-1">Buat backup pertama dengan tombol di atas</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="neu-table w-full">
                                <thead>
                                    <tr>
                                        <th scope="col">Berkas</th>
                                        <th scope="col">Ukuran</th>
                                        <th scope="col">Jenis</th>
                                        <th scope="col">Dibuat Oleh</th>
                                        <th scope="col">Waktu</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" className="w-56">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {backups.map((backup) => (
                                        <tr key={backup.id}>
                                            <td>
                                                <b className="text-sm font-mono break-all">{backup.filename}</b>
                                                {backup.status === 'failed' && backup.error && (
                                                    <span className="block text-xs text-neu-bad mt-0.5">{backup.error}</span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap text-sm">{backup.human_size}</td>
                                            <td className="whitespace-nowrap text-sm">{backup.type_label}</td>
                                            <td className="whitespace-nowrap text-sm">{backup.created_by}</td>
                                            <td className="whitespace-nowrap text-sm text-neu-sub">{formatDateTime(backup.created_at)}</td>
                                            <td>
                                                <NeuPill variant={backup.status === 'failed' ? 'issue' : 'done'}>
                                                    {backup.status === 'failed' ? 'Gagal' : 'Siap'}
                                                </NeuPill>
                                            </td>
                                            <td>
                                                <div className="flex items-center gap-1.5 justify-end">
                                                    {backup.exists && backup.status !== 'failed' && (
                                                        <>
                                                            <a
                                                                href={route('admin.backups.download', backup.id)}
                                                                className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs"
                                                            >
                                                                Unduh
                                                            </a>
                                                            <NeuButton
                                                                onClick={() => openRestore(backup)}
                                                                className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                                            >
                                                                Pulihkan
                                                            </NeuButton>
                                                        </>
                                                    )}
                                                    <NeuButton
                                                        onClick={() => destroy(backup)}
                                                        variant="bad"
                                                        className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                                    >
                                                        Hapus
                                                    </NeuButton>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </NeuCard>
            </div>

            <Modal show={Boolean(restoreTarget)} onClose={closeRestore} maxWidth="lg">
                {restoreTarget && (
                    <form onSubmit={submitRestore} className="p-6">
                        <h2 className="text-lg font-bold mb-1">Pulihkan Database</h2>
                        <p className="text-sm text-neu-sub mb-3">
                            Seluruh data saat ini akan diganti dengan isi <b className="font-mono">{restoreTarget.filename}</b>.
                            Tindakan ini tidak dapat dibatalkan.
                        </p>

                        <label className="block text-xs text-neu-sub mb-1">
                            Ketik nama berkas untuk konfirmasi:
                        </label>
                        <p className="text-xs font-mono mb-2 break-all">{restoreTarget.filename}</p>
                        <input
                            type="text"
                            value={confirmText}
                            onChange={(e) => setConfirmText(e.target.value)}
                            className="neu-input"
                            placeholder={restoreTarget.filename}
                            autoFocus
                        />

                        <div className="flex justify-end gap-2 mt-5">
                            <NeuButton type="button" onClick={closeRestore} className="!min-h-[44px]">
                                Batal
                            </NeuButton>
                            <NeuButton
                                type="submit"
                                variant="primary"
                                disabled={!confirmMatches || restoring}
                                className="!min-h-[44px]"
                            >
                                {restoring ? 'Memulihkan…' : 'Pulihkan Sekarang'}
                            </NeuButton>
                        </div>
                    </form>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
