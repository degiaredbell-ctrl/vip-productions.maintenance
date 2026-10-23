import { useState } from 'react';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuButton from '@/Components/NeuButton';
import Modal from '@/Components/Modal';
import SparePartDashboardPanel from '@/Components/SparePartDashboardPanel';

export default function SparePartsDashboard({ auth, ...data }) {
    const { can = {} } = data;

    const [resetOpen, setResetOpen] = useState(false);
    const [scope, setScope] = useState('dashboard');
    const [confirmText, setConfirmText] = useState('');
    const [resetting, setResetting] = useState(false);

    const changeRange = (value) => {
        router.get(route('spareparts.dashboard'), { range: value }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openReset = () => {
        setScope('dashboard');
        setConfirmText('');
        setResetOpen(true);
    };

    const closeReset = () => {
        setResetOpen(false);
        setConfirmText('');
    };

    const submitReset = (e) => {
        e.preventDefault();
        setResetting(true);
        router.post(route('spareparts.reset'), { scope, confirm: confirmText }, {
            preserveScroll: true,
            onSuccess: () => closeReset(),
            onFinish: () => setResetting(false),
        });
    };

    const confirmMatches = confirmText.trim().toUpperCase() === 'RESET';

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="flex flex-wrap items-end justify-between gap-3 mb-5">
                    <div>
                        <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                        <h1 className="text-xl sm:text-2xl font-bold">Dashboard Suku Cadang</h1>
                        <p className="text-xs text-neu-sub mt-0.5">Pantau stok, kebutuhan restock, dan pemakaian suku cadang ke unit.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <NeuButton href={route('form-maintenance.suku-cadang')} variant={can.manage ? 'primary' : 'default'} className="!min-h-[40px] !py-2 !text-sm">
                            {can.manage ? '+ Catat Pemakaian' : 'Riwayat Pemakaian'}
                        </NeuButton>
                        <NeuButton href={route('spareparts.index')} className="!min-h-[40px] !py-2 !text-sm">
                            Master Suku Cadang
                        </NeuButton>
                        {can.reset && (
                            <NeuButton onClick={openReset} variant="bad" className="!min-h-[40px] !py-2 !text-sm">
                                Reset Data
                            </NeuButton>
                        )}
                    </div>
                </div>

                <SparePartDashboardPanel data={data} onRangeChange={changeRange} />
            </div>

            <Modal show={resetOpen} onClose={closeReset} maxWidth="lg">
                <form onSubmit={submitReset} className="p-6">
                    <h2 className="text-lg font-bold mb-1">Reset Data Suku Cadang</h2>
                    <p className="text-sm text-neu-sub mb-4">
                        Tindakan ini tidak dapat dibatalkan. Pilih cakupan reset sebelum melanjutkan.
                    </p>

                    <div className="space-y-2 mb-4">
                        <label className="flex items-start gap-2 p-3 rounded-lg border border-neu-border cursor-pointer">
                            <input
                                type="radio"
                                name="scope"
                                value="dashboard"
                                checked={scope === 'dashboard'}
                                onChange={() => setScope('dashboard')}
                                className="mt-1"
                            />
                            <span>
                                <span className="block text-sm font-bold">Reset Dashboard saja</span>
                                <span className="block text-xs text-neu-sub mt-0.5">
                                    Hapus semua riwayat transaksi (barang masuk/keluar) dan kembalikan stok ke stok awal.
                                    Daftar master suku cadang (kode, nama, kategori, dll.) tetap ada.
                                </span>
                            </span>
                        </label>
                        <label className="flex items-start gap-2 p-3 rounded-lg border border-neu-border cursor-pointer">
                            <input
                                type="radio"
                                name="scope"
                                value="all"
                                checked={scope === 'all'}
                                onChange={() => setScope('all')}
                                className="mt-1"
                            />
                            <span>
                                <span className="block text-sm font-bold text-neu-bad">Reset Dashboard + Data Master Suku Cadang</span>
                                <span className="block text-xs text-neu-sub mt-0.5">
                                    Selain riwayat transaksi, seluruh daftar master suku cadang juga dihapus permanen.
                                    Gunakan ini hanya jika ingin memulai ulang dari kosong.
                                </span>
                            </span>
                        </label>
                    </div>

                    <label className="block text-xs text-neu-sub mb-1">
                        Ketik <b className="font-mono">RESET</b> untuk konfirmasi:
                    </label>
                    <input
                        type="text"
                        value={confirmText}
                        onChange={(e) => setConfirmText(e.target.value)}
                        className="neu-input"
                        placeholder="RESET"
                        autoFocus
                    />

                    <div className="flex justify-end gap-2 mt-5">
                        <NeuButton type="button" onClick={closeReset} className="!min-h-[44px]">
                            Batal
                        </NeuButton>
                        <NeuButton
                            type="submit"
                            variant="bad"
                            disabled={!confirmMatches || resetting}
                            className="!min-h-[44px]"
                        >
                            {resetting ? 'Mereset…' : 'Reset Sekarang'}
                        </NeuButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
