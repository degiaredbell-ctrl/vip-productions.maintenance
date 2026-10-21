import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';

const POLL_MS = 45000;

export const CATEGORY_META = {
    schedule: { label: 'Jadwal PM', color: 'text-neu-info', dot: 'bg-neu-info' },
    overdue: { label: 'Terlambat', color: 'text-neu-bad', dot: 'bg-neu-bad' },
    approval: { label: 'Approval', color: 'text-neu-accent', dot: 'bg-neu-accent' },
    other: { label: 'Info', color: 'text-neu-sub', dot: 'bg-neu-sub' },
};

export function BellIcon({ className = 'w-5 h-5' }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.8} aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
    );
}

/**
 * Lonceng notifikasi in-app di header. Jumlah belum dibaca ikut di setiap
 * navigasi Inertia (shared prop) dan diperbarui berkala, jadi notifikasi
 * approval baru muncul tanpa perlu memuat ulang halaman.
 */
export default function NotificationBell() {
    const shared = Number(usePage().props.notificationsUnread ?? 0);
    const [unread, setUnread] = useState(shared);
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState(null);
    const [loading, setLoading] = useState(false);
    const panelRef = useRef(null);

    useEffect(() => setUnread(shared), [shared]);

    useEffect(() => {
        const tick = () => {
            if (document.hidden) return;
            window.axios.get(route('notifications.feed'), { params: { only: 'count' } })
                .then(({ data }) => setUnread(Number(data.unread ?? 0)))
                .catch(() => {});
        };
        const timer = setInterval(tick, POLL_MS);
        document.addEventListener('visibilitychange', tick);

        return () => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', tick);
        };
    }, []);

    const load = useCallback(() => {
        setLoading(true);
        window.axios.get(route('notifications.feed'))
            .then(({ data }) => {
                setItems(data.items ?? []);
                setUnread(Number(data.unread ?? 0));
            })
            .catch(() => setItems([]))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        if (!open) return undefined;
        load();
        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        const onClick = (e) => panelRef.current && !panelRef.current.contains(e.target) && setOpen(false);
        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onClick);

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onClick);
        };
    }, [open, load]);

    const markAll = () => {
        window.axios.post(route('notifications.read-all'), {}, { headers: { Accept: 'application/json' } })
            .then(() => {
                setUnread(0);
                setItems((prev) => (prev ?? []).map((n) => ({ ...n, read: true })));
                router.reload({ only: ['notificationsUnread'] });
            })
            .catch(() => {});
    };

    return (
        <div className="relative" ref={panelRef}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="neu-btn relative !min-h-[36px] !w-[40px] !px-0 !py-1.5 justify-center"
                aria-label={unread > 0 ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'}
                aria-expanded={open}
            >
                <BellIcon />
                {unread > 0 && (
                    <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 grid place-items-center rounded-full bg-neu-bad text-white text-[10px] font-bold">
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div className="fixed left-2 right-2 top-14 sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-2 z-50 sm:w-[380px] rounded-[20px] bg-neu-bg shadow-neu-up p-3">
                    <div className="flex items-center justify-between px-1 pb-2">
                        <b className="text-sm">Notifikasi</b>
                        {unread > 0 && (
                            <button type="button" onClick={markAll} className="text-[11px] font-semibold text-neu-accent hover:underline">
                                Tandai semua dibaca
                            </button>
                        )}
                    </div>

                    <div className="max-h-[60vh] overflow-y-auto -mx-1 px-1 space-y-1.5">
                        {loading && items === null && <p className="text-xs text-neu-sub px-2 py-6 text-center">Memuat…</p>}
                        {items !== null && items.length === 0 && (
                            <p className="text-xs text-neu-sub px-2 py-6 text-center">Belum ada notifikasi.</p>
                        )}
                        {(items ?? []).map((n) => {
                            const meta = CATEGORY_META[n.category] ?? CATEGORY_META.other;

                            return (
                                <Link
                                    key={n.id}
                                    href={n.open_url}
                                    onClick={() => setOpen(false)}
                                    className={`block rounded-2xl px-3 py-2.5 transition-shadow ${n.read ? 'hover:shadow-neu-in' : 'shadow-neu-up-sm hover:shadow-neu-in'}`}
                                >
                                    <div className="flex items-start gap-2">
                                        <span className={`mt-1.5 h-2 w-2 flex-none rounded-full ${n.read ? 'bg-transparent' : meta.dot}`} aria-hidden="true" />
                                        <div className="min-w-0 flex-1">
                                            <span className={`text-[10px] font-bold uppercase tracking-wide ${meta.color}`}>{meta.label}</span>
                                            <b className={`block text-xs leading-snug ${n.read ? 'font-medium text-neu-sub' : ''}`}>{n.title}</b>
                                            <span className="block text-[11px] text-neu-sub line-clamp-2">{n.body}</span>
                                            <span className="block text-[10px] text-neu-sub mt-0.5" title={n.at}>{n.ago}</span>
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>

                    <Link
                        href={route('notifications.inbox')}
                        onClick={() => setOpen(false)}
                        className="neu-btn w-full justify-center mt-2 !min-h-[36px] !py-1.5 text-xs"
                    >
                        Lihat semua notifikasi
                    </Link>
                </div>
            )}
        </div>
    );
}
