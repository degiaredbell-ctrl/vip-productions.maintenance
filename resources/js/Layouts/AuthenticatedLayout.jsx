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
        { name: 'Mesin', href: route('machines.index'), path: '/machines', show: can['machine.manage'] },
        { name: 'Pengguna', href: route('admin.users'), path: '/admin/users', show: can['user.manage'] },
        { name: 'Template', href: route('admin.templates'), path: '/admin/templates', show: can['template.manage'] },
        { name: 'Audit Log', href: route('admin.audit-logs'), path: '/admin/audit-logs', show: can['audit.view'] },
    ].filter(item => item.show);

    return (
        <div className="min-h-screen bg-neu-bg">
            <NeuToast toast={toast} onDismiss={() => setToast(null)} />

            {/*
                Sidebar dipakai mulai tablet (md/768px) supaya iPad tidak lagi
                memakai bottom navigation seperti ponsel. Lebarnya dikecilkan di
                tablet lalu dilebarkan di desktop agar konten tetap lega.
            */}
            <aside className="hidden md:flex md:flex-col md:fixed md:inset-y-0 md:w-[210px] lg:w-[260px] bg-neu-bg p-4 lg:p-6 gap-3">
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
            <div className="md:pl-[210px] lg:pl-[260px]">
                {/* Top bar - ponsel */}
                <header className="md:hidden sticky top-0 z-30 bg-neu-bg/90 backdrop-blur px-4 py-3 flex items-center justify-between">
                    <div className="flex items-center gap-2 font-bold min-w-0">
                        <ApplicationLogo className="w-7 h-7 flex-none" />
                        <span className="truncate">PM Preventive</span>
                    </div>
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="neu-btn !min-h-[40px] !px-3">
                                <span className="text-sm">{user.name}</span>
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

                {/* Header - tablet & desktop */}
                <header className="hidden md:flex items-center justify-end px-5 lg:px-8 py-4">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="neu-btn !min-h-[40px] !px-3">
                                <span className="text-sm">{user.name}</span>
                                <span className="neu-pill text-neu-accent">{user.roles?.[0] ?? '-'}</span>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Profil</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">Keluar</Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                <main className="px-4 sm:px-6 md:px-5 lg:px-8 pt-2 pb-28 md:pb-8">
                    {header}
                    {children}
                </main>
            </div>

            {/* Bottom nav - ponsel (< md) */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 bg-neu-bg flex gap-1 px-3 pt-2 pb-[calc(8px+env(safe-area-inset-bottom))] shadow-[0_-6px_16px_rgba(195,202,214,0.55)] z-30">
                {navItems.slice(0, 5).map((item) => (
                    <NavLink
                        key={item.href}
                        href={item.href}
                        active={isActive(item.path, item.exact)}
                        activeClassName="neu-nav-sunken"
                        className="neu-nav-raised flex-1 flex flex-col items-center gap-1 py-2 text-[11px] font-semibold relative"
                    >
                        {item.name}
                        {item.badge > 0 && (
                            <span
                                className="absolute top-1 right-1/4 min-w-[16px] h-4 px-1 grid place-items-center rounded-full bg-neu-info text-white text-[9px] font-bold"
                                aria-label={`${item.badge} menunggu`}
                            >
                                {item.badge > 99 ? '99+' : item.badge}
                            </span>
                        )}
                    </NavLink>
                ))}
            </nav>
        </div>
    );
}
