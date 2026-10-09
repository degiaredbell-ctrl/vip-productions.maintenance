import { Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import ApplicationLogo from '@/Components/ApplicationLogo';

const MENU_CARDS = [
    {
        name: 'Mesin',
        description: 'Lihat status PM mesin per periode & isi checklist',
        icon: (
            <svg className="w-10 h-10 text-neu-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m-1.5 0h1.5m-16.5 0a2.25 2.25 0 0 0-2.25 2.25V21A2.25 2.25 0 0 0 3.75 23.25h16.5A2.25 2.25 0 0 0 22.5 21V5.25A2.25 2.25 0 0 0 20.25 3H6.75A2.25 2.25 0 0 0 4.5 5.25V13.5" />
            </svg>
        ),
        href: route('form-maintenance.mesin'),
        permission: 'dashboard.view',
        color: 'bg-neu-accent/10 border-neu-accent/20',
    },
    {
        name: 'Utility',
        description: 'Kelola unit utility & komponen maintenance',
        icon: (
            <svg className="w-10 h-10 text-neu-info" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 3.75H3.006a2.25 2.25 0 0 0-2.25 2.25v11.994a2.25 2.25 0 0 0 2.25 2.25h18a2.25 2.25 0 0 0 2.25-2.25V13.5m-9-9.75v9m0 0h9m-9 0l10.393 10.393m-10.393 0L9 21M21 15a2.25 2.25 0 0 0-2.25-2.25H15" />
            </svg>
        ),
        href: route('form-maintenance.utility'),
        permission: 'dashboard.view',
        color: 'bg-neu-info/10 border-neu-info/20',
    },
    {
        name: 'Komponen',
        description: 'Kelola master komponen/parts & penugasannya ke Mesin dan Utility',
        icon: (
            <svg className="w-10 h-10 text-neu-warn" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 3h5.259a1.5 1.5 0 0 1 1.06.44l4.5 2.598a1.5 1.5 0 0 1 .3.939V19.5a1.5 1.5 0 0 1-1.5 1.5H15" />
            </svg>
        ),
        href: route('admin.components'),
        permission: 'template.manage',
        color: 'bg-neu-warn/10 border-neu-warn/20',
    },
];

export default function FormMaintenanceIndex({ auth }) {
    const visibleCards = MENU_CARDS.filter(card => auth.can[card.permission]);

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-6">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Form Maintenance</h1>
                    <p className="text-neu-sub text-sm mt-1">Pilih menu untuk mengelola form Preventive Maintenance</p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {visibleCards.map((card) => (
                        <Link key={card.name} href={card.href} className="block">
                            <NeuCard className={`h-full transition-all duration-200 hover:shadow-neu-up border ${card.color}`}>
                                <div className="flex flex-col h-full items-center text-center p-6">
                                    <div className={`mb-4 p-4 rounded-neu ${card.color.replace('bg-', 'bg-').replace('border-', 'border-')} flex items-center justify-center`}>
                                        {card.icon}
                                    </div>
                                    <h2 className="text-lg font-bold text-neu-text mb-2">{card.name}</h2>
                                    <p className="text-sm text-neu-sub leading-relaxed">{card.description}</p>
                                    <div className="mt-auto pt-4 w-full">
                                        <span className="neu-btn w-full justify-center">
                                            Buka
                                            <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </NeuCard>
                        </Link>
                    ))}

                    {visibleCards.length === 0 && (
                        <NeuCard className="col-span-full text-center py-12">
                            <ApplicationLogo className="w-16 h-16 mx-auto mb-4 text-neu-sub" />
                            <p className="text-neu-sub">Tidak ada menu yang tersedia untuk role Anda.</p>
                        </NeuCard>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}