import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';

export default function Authenticated({ user: userProp, header, children }) {
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const page = usePage();
    const { can } = page.props.auth;
    const user = userProp ?? page.props.auth.user;

    const navItems = [
        { name: 'Beranda', href: route('dashboard'), show: true },
        { name: 'Laporan', href: route('reports.index'), show: can['report.view'] },
        { name: 'Mesin', href: route('machines.index'), show: can['machine.manage'] },
        { name: 'Pengguna', href: route('admin.users'), show: can['user.manage'] },
        { name: 'Template', href: route('admin.templates'), show: can['template.manage'] },
        { name: 'Audit Log', href: route('admin.audit-logs'), show: can['audit.view'] },
    ].filter(item => item.show);

    return (
        <div className="min-h-screen bg-neu-bg">
            {/* Sidebar - Desktop */}
            <aside className="hidden lg:flex lg:flex-col lg:w-[270px] lg:fixed lg:inset-y-0 bg-neu-bg p-6 gap-3">
                <div className="font-bold text-lg mb-4">PM Preventive</div>
                <nav className="flex flex-col gap-2">
                    {navItems.map((item) => (
                        <NavLink
                            key={item.href}
                            href={item.href}
                            active={route().current(item.href.replace(/^(https?:\/\/[^\/]+)/, '').replace(/^\//, '/'))}
                            className="flex items-center gap-3 rounded-neu-sm px-4 py-3.5 text-sm font-semibold text-neu-sub shadow-neu-up-sm"
                            activeClassName="shadow-neu-in text-neu-accent"
                        >
                            {item.name}
                        </NavLink>
                    ))}
                </nav>
            </aside>

            {/* Main content */}
            <div className="lg:pl-[270px]">
                {/* Top bar - Mobile */}
                <header className="lg:hidden sticky top-0 z-30 bg-neu-bg/90 backdrop-blur px-4 py-3 flex items-center justify-between">
                    <div className="font-bold">PM Preventive</div>
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="neu-btn !min-h-[40px] !px-3">
                                <span className="text-sm">{user.name}</span>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Profil</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">Keluar</Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                {/* Desktop header */}
                <header className="hidden lg:flex items-center justify-end px-8 py-4">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="flex items-center gap-2 text-sm font-semibold text-neu-sub">
                                <span>{user.name}</span>
                                <span className="neu-pill text-neu-accent">{user.roles?.[0] ?? '-'}</span>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Profil</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">Keluar</Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                <main className="px-4 pb-28 lg:px-8 lg:pb-8 pt-2">
                    {children}
                </main>
            </div>

            {/* Bottom nav - Mobile */}
            <nav className="lg:hidden fixed bottom-0 left-0 right-0 bg-neu-bg flex gap-1 px-3 pt-2 pb-[calc(8px+env(safe-area-inset-bottom))] shadow-[0_-6px_16px_rgba(195,202,214,0.55)] z-30">
                {navItems.slice(0, 5).map((item) => (
                    <NavLink
                        key={item.href}
                        href={item.href}
                        className="flex-1 flex flex-col items-center gap-1 rounded-neu-sm py-2 text-[11px] font-semibold text-neu-sub"
                        activeClassName="shadow-neu-in text-neu-accent"
                    >
                        {item.name}
                    </NavLink>
                ))}
            </nav>
        </div>
    );
}
