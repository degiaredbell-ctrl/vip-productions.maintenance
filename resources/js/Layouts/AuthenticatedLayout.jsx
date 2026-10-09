import { useEffect, useState } from 'react';
import NeuToast from '@/Components/NeuToast';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import { Link, usePage } from '@inertiajs/react';

export default function Authenticated({ user: userProp, header, children }) {
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const page = usePage();
    const { can } = page.props.auth;
    const user = userProp ?? page.props.auth.user;
    const pathname = page.url.split('?')[0];
    const flash = page.props.flash ?? {};

    // Pesan sukses/gagal dari flash Laravel perlu dirender sendiri;
    // kalau tidak, redirect()->back()->with('success', ...) jadi tidak terlihat.
    const [toast, setToast] = useState(null);

    useEffect(() => {
        if (!flash.success && !flash.error) return;

        setToast({ message: flash.success ?? flash.error, variant: flash.success ? 'done' : 'issue' });
    }, [flash.success, flash.error]);

    useEffect(() => {
        if (!toast) return;

        const timer = setTimeout(() => setToast(null), 4000);

        return () => clearTimeout(timer);
    }, [toast]);

    const isActive = (path, exact = false) =>
        exact ? pathname === path : pathname === path || pathname.startsWith(`${path}/`);

    // Badge antrean persetujuan dihitung di backend (SignatureChain), bukan di
    // client, supaya angka yang sama dipakai juga untuk query daftarnya.
    const pending = Number(page.props.pendingApprovals ?? 0);

    const navItems = [
        { name: 'Beranda', href: route('dashboard'), path: '/dashboard', exact: true, show: true },
        {
            name: 'Persetujuan',
            href: route('approvals.index'),
            path: '/approvals',
            badge: pending,
            // Menu disembunyikan kalau memang tidak ada antrean dan user tidak
            // punya wewenang, supaya sidebar tidak penuh item mati.
            show: pending > 0 || can['pm.sign'] || can['pm.acknowledge'] || can['pm.approve'],
        },
        { name: 'Laporan', href: route('reports.index'), path: '/reports', show: can['report.view'] },
        { name: 'Form Maintenance', href: route('form-maintenance'), path: '/form-maintenance', show: can['machine.manage'] || can['dashboard.view'] || can['template.manage'] },
        { name: 'Mesin', href: route('machines.index'), path: '/machines', show: can['machine.manage'] },
        { name: 'Utility', href: route('utility.index'), path: '/utility', show: can['dashboard.view'] },
        { name: 'Pengguna', href: route('admin.users'), path: '/admin/users', show: can['user.manage'] },
        { name: 'Role', href: route('admin.roles.index'), path: '/admin/roles', show: user?.roles?.includes('admin') },
        { name: 'Template', href: route('admin.templates'), path: '/admin/templates', show: can['template.manage'] },
        { name: 'Audit Log', href: route('admin.audit-logs'), path: '/admin/audit-logs', show: can['audit.view'] },
    ].filter(item => item.show);

    return (
        <div className="min-h-screen bg-neu-bg">
            <NeuToast toast={toast} onDismiss={() => setToast(null)} />

            {/*
                Sidebar baru dipakai mulai desktop (lg/1024px). iPad dan
                tablet memakai layout mobile (top bar + bottom menu) supaya
                tampilannya konsisten dengan ponsel, dengan ruang konten penuh.
            */}
            <aside className="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:w-[260px] bg-neu-bg p-6 gap-3">
                <div className="flex items-center gap-2.5 mb-3 px-1">
                    <ApplicationLogo className="w-9 h-9 lg:w-10 lg:h-10 flex-none" />
                    <div className="min-w-0">
                        <div className="font-bold text-base lg:text-lg leading-tight truncate">PM Preventive</div>
                        <div className="text-[11px] text-neu-sub truncate">Verra Inter Pangan</div>
                    </div>
                </div>
                <nav className="flex flex-col gap-2">
                    {navItems.map((item) => (
                        <NavLink
                            key={item.href}
                            href={item.href}
                            active={isActive(item.path, item.exact)}
                            activeClassName="neu-nav-sunken"
                            className="neu-nav-raised flex items-center gap-3 px-3.5 py-3 text-sm font-semibold"
                        >
                            <span
                                className={`h-5 w-1 rounded-full transition-colors duration-150 ${
                                    isActive(item.path, item.exact) ? 'bg-neu-accent' : 'bg-transparent'
                                }`}
                                aria-hidden="true"
                            />
                            <span className="truncate">{item.name}</span>
                            {item.badge > 0 && (
                                <span
                                    className="ml-auto neu-pill text-neu-info"
                                    aria-label={`${item.badge} menunggu`}
                                >
                                    {item.badge}
                                </span>
                            )}
                        </NavLink>
                    ))}
                </nav>
            </aside>

            {/* Main content */}
            <div className="lg:pl-[260px] lg:ml-[5px]">
                {/* Top bar - ponsel & tablet */}
                <header className="lg:hidden sticky top-0 z-30 bg-neu-bg/90 backdrop-blur px-3 py-2.5 flex items-center justify-between">
                    <div className="flex items-center gap-2 font-bold min-w-0">
                        <ApplicationLogo className="w-7 h-7 flex-none" />
                        <span className="truncate">PM Preventive</span>
                    </div>
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="neu-btn !min-h-[36px] !px-2.5 !py-1.5 text-sm">
                                <span>{user.name}</span>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Profil</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">Keluar</Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                {/* Header - desktop */}
                <header className="hidden lg:flex items-center justify-end px-5 py-3">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="neu-btn !min-h-[36px] !px-2.5 !py-1.5 text-sm">
                                <span>{user.name}</span>
                                <span className="neu-pill text-neu-accent text-[10px] py-0.5 px-2">{user.roles?.[0] ?? '-'}</span>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Profil</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">Keluar</Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                <main className="px-3 sm:px-4 lg:px-5 pt-1 pb-24 lg:pb-6">
                    {header}
                    {children}
                </main>
            </div>

            {/* Bottom nav - ponsel & tablet (< lg). Semua menu ditampilkan dan
                bisa digeser horizontal, jadi tidak ada item yang tercampak
                hanya karena layar ponsel sempit. */}
            <nav className="lg:hidden fixed bottom-0 left-0 right-0 bg-neu-bg z-30 shadow-[0_-6px_16px_rgba(195,202,214,0.55)]">
                <div className="overflow-x-auto">
                <div className="flex gap-1.5 px-3 pt-2 pb-[calc(8px+env(safe-area-inset-bottom))] w-max mx-auto">
                {navItems.map((item) => (
                    <NavLink
                        key={item.href}
                        href={item.href}
                        active={isActive(item.path, item.exact)}
                        activeClassName="neu-nav-sunken"
                        className="neu-nav-raised flex-none min-w-[68px] flex flex-col items-center gap-1 py-2 px-2 text-[11px] font-semibold relative whitespace-nowrap"
                    >
                        {item.name}
                        {/*
                            Dot jumlah antrean diletakkan persis di pojok kanan
                            atas tombol, bukan di atas ikonnya, supaya tetap
                            terbaca sebagai lencana tanpa melintasi batas antar
                            tombol.
                        */}
                        {item.badge > 0 && (
                            <span
                                className="absolute top-1 right-1 min-w-[16px] h-4 px-1 grid place-items-center rounded-full bg-neu-info text-white text-[9px] font-bold"
                                aria-label={`${item.badge} menunggu`}
                            >
                                {item.badge > 99 ? '99+' : item.badge}
                            </span>
                        )}
                    </NavLink>
                ))}
                </div>
                </div>
            </nav>
        </div>
    );
}
