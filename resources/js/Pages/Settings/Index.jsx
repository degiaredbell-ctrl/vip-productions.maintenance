import { Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import ApplicationLogo from '@/Components/ApplicationLogo';

// Setiap kartu punya daftar permission; kartu tampil "aktif" bila user punya
// setidaknya satu, dan tampil abu-abu (tidak bisa diklik) bila tidak. Permission
// di sini SENGAJA sama dengan middleware route tujuannya supaya kartu yang
// tampak aktif tidak berujung 403.
const hasAny = (auth, permissions) => permissions.some((p) => Boolean(auth.can[p]));

const MENU_CARDS = [
    {
        name: 'Mesin',
        description: 'Kelola data mesin, komponen, dan isi checklist PM',
        icon: (
            <svg className="w-10 h-10 text-neu-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m-1.5 0h1.5m-16.5 0a2.25 2.25 0 0 0-2.25 2.25V21A2.25 2.25 0 0 0 3.75 23.25h16.5A2.25 2.25 0 0 0 22.5 21V5.25A2.25 2.25 0 0 0 20.25 3H6.75A2.25 2.25 0 0 0 4.5 5.25V13.5" />
            </svg>
        ),
        href: route('machines.index'),
        permissions: ['machine.manage', 'machine.view'],
        lockedHint: 'Role Anda tidak memiliki akses ke halaman Mesin.',
        color: 'bg-neu-accent/10 border-neu-accent/20',
    },
    {
        name: 'Utility',
        description: 'Kelola unit utility, komponen maintenance, dan isi checklist PM',
        icon: (
            <svg className="w-10 h-10 text-neu-info" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 3.75H3.006a2.25 2.25 0 0 0-2.25 2.25v11.994a2.25 2.25 0 0 0 2.25 2.25h18a2.25 2.25 0 0 0 2.25-2.25V13.5m-9-9.75v9m0 0h9m-9 0l10.393 10.393m-10.393 0L9 21M21 15a2.25 2.25 0 0 0-2.25-2.25H15" />
            </svg>
        ),
        href: route('utility.index'),
        permissions: ['utility.view', 'dashboard.view'],
        lockedHint: 'Role Anda tidak memiliki akses ke halaman Utility.',
        color: 'bg-neu-info/10 border-neu-info/20',
    },
    {
        name: 'Komponen / Parts',
        description: 'Master komponen/parts & penugasannya ke Mesin dan Utility',
        icon: (
            <svg className="w-10 h-10 text-neu-warn" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 3h5.259a1.5 1.5 0 0 1 1.06.44l4.5 2.598a1.5 1.5 0 0 1 .3.939V19.5a1.5 1.5 0 0 1-1.5 1.5H15" />
            </svg>
        ),
        href: null,
        comingSoon: true,
        color: 'bg-neu-warn/10 border-neu-warn/20',
    },
    {
        name: 'Template',
        description: 'Kumpulan komponen siap pakai untuk Mesin & Utility, bisa diterapkan ke banyak unit sekaligus',
        icon: (
            <svg className="w-10 h-10 text-neu-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
            </svg>
        ),
        href: route('admin.templates'),
        // Sama dengan middleware route /admin/templates.
        permissions: ['template.manage'],
        lockedHint: 'Role Anda tidak memiliki akses ke halaman Template.',
        color: 'bg-neu-accent/10 border-neu-accent/20',
    },
    {
        name: 'Suku Cadang',
        description: 'Master suku cadang & transaksi stok (masuk/keluar)',
        icon: (
            <svg className="w-10 h-10 text-neu-sub" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
            </svg>
        ),
        href: null,
        comingSoon: true,
        color: 'bg-neu-sub/10 border-neu-sub/20',
    },
];

export default function SettingsIndex({ auth }) {
    const cards = MENU_CARDS.map((card) => ({
        ...card,
        allowed: !card.comingSoon && hasAny(auth, card.permissions ?? []),
    }));
    const hasAnyCard = cards.some((card) => card.allowed);

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-6">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Settings</h1>
                    <p className="text-neu-sub text-sm mt-1">Pusat pengaturan aset &amp; data pendukung Preventive Maintenance</p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {cards.map((card) => {
                        const content = (
                            <NeuCard
                                className={`h-full duration-200 border ${card.color} ${
                                    card.allowed ? 'transition-all hover:shadow-neu-up' : 'opacity-45 grayscale cursor-not-allowed select-none'
                                }`}
                            >
                                <div className="flex flex-col h-full items-center text-center p-6">
                                    <div className={`mb-4 p-4 rounded-neu ${card.color} flex items-center justify-center`}>
                                        {card.icon}
                                    </div>
                                    <h2 className="text-lg font-bold text-neu-text mb-2">{card.name}</h2>
                                    <p className="text-sm text-neu-sub leading-relaxed">{card.description}</p>
                                    <div className="mt-auto pt-4 w-full">
                                        {card.allowed ? (
                                            <span className="neu-btn w-full justify-center">
                                                Buka
                                                <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                </svg>
                                            </span>
                                        ) : card.comingSoon ? (
                                            <span className="neu-btn w-full justify-center !text-neu-sub" aria-disabled="true">
                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                Segera hadir
                                            </span>
                                        ) : (
                                            <span
                                                className="neu-btn w-full justify-center !text-neu-sub"
                                                title={card.lockedHint}
                                                aria-disabled="true"
                                            >
                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                </svg>
                                                Tidak tersedia
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </NeuCard>
                        );

                        return card.allowed ? (
                            <Link key={card.name} href={card.href} className="block">
                                {content}
                            </Link>
                        ) : (
                            <div key={card.name} className="block" aria-disabled="true" title={card.comingSoon ? 'Belum tersedia' : card.lockedHint}>
                                {content}
                            </div>
                        );
                    })}

                    {!hasAnyCard && (
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
