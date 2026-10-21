import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuButton from '@/Components/NeuButton';
import SparePartDashboardPanel from '@/Components/SparePartDashboardPanel';

export default function SparePartsDashboard({ auth, ...data }) {
    const { can = {} } = data;

    const changeRange = (value) => {
        router.get(route('spareparts.dashboard'), { range: value }, { preserveState: true, preserveScroll: true, replace: true });
    };

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
                    </div>
                </div>

                <SparePartDashboardPanel data={data} onRangeChange={changeRange} />
            </div>
        </AuthenticatedLayout>
    );
}
