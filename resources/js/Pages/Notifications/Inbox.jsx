import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import { BellIcon, CATEGORY_META } from '@/Components/NotificationBell';

function ItemsTable({ items, total, overdue }) {
    return (
        <div className="mt-2 neu-inset p-2 overflow-x-auto">
            <table className="w-full text-[11px]">
                <thead>
                    <tr className="text-neu-sub text-left">
                        <th className="px-2 py-1">Perangkat</th>
                        <th className="px-2 py-1">Lokasi</th>
                        <th className="px-2 py-1 text-center">Minggu</th>
                        <th className="px-2 py-1">Batas</th>
                        <th className="px-2 py-1 text-right">{overdue ? 'Terlambat' : 'Status'}</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((it) => (
                        <tr key={it.id} className="border-t border-neu-sub/10">
                            <td className="px-2 py-1">
                                <Link href={it.path} className="font-semibold text-neu-accent hover:underline">{it.code}</Link>
                                <span className="block text-neu-sub">{it.name}</span>
                            </td>
                            <td className="px-2 py-1">{it.location || '-'}</td>
                            <td className="px-2 py-1 text-center">W{it.week}</td>
                            <td className="px-2 py-1 whitespace-nowrap">{it.deadline}</td>
                            <td className={`px-2 py-1 text-right whitespace-nowrap ${overdue ? 'font-bold text-neu-bad' : ''}`}>
                                {overdue ? `${it.days_late} hari` : it.status}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {total > items.length && (
                <p className="px-2 pt-1 text-[11px] text-neu-sub">…dan {total - items.length} perangkat lainnya (lihat email / Beranda).</p>
            )}
        </div>
    );
}

function InboxItem({ n }) {
    const [expanded, setExpanded] = useState(false);
    const meta = CATEGORY_META[n.category] ?? CATEGORY_META.other;
    const hasList = (n.items ?? []).length > 0;

    const markRead = () => router.post(route('notifications.read', n.id), {}, { preserveScroll: true, preserveState: true });
    const remove = () => router.delete(route('notifications.destroy', n.id), { preserveScroll: true, preserveState: true });

    return (
        <NeuCard className={`!p-4 ${n.read ? 'opacity-80' : ''}`}>
            <div className="flex items-start gap-3">
                <span className={`mt-1.5 h-2.5 w-2.5 flex-none rounded-full ${n.read ? 'bg-neu-sub/30' : meta.dot}`} aria-label={n.read ? 'Sudah dibaca' : 'Belum dibaca'} />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2 mb-0.5">
                        <span className={`neu-pill text-[10px] py-0.5 ${meta.color}`}>{meta.label}</span>
                        {n.doc_number && <span className="text-[11px] font-mono text-neu-sub">{n.doc_number}</span>}
                        <span className="text-[11px] text-neu-sub ml-auto" title={n.at}>{n.at} · {n.ago}</span>
                    </div>
                    <b className={`block text-sm leading-snug ${n.read ? 'font-semibold' : ''}`}>{n.title}</b>
                    <p className="text-xs text-neu-sub mt-0.5">{n.body}</p>

                    {n.category === 'approval' && (
                        <dl className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-0.5 text-[11px]">
                            {n.unit && <div><dt className="inline text-neu-sub">Perangkat: </dt><dd className="inline font-semibold">{n.unit}</dd></div>}
                            {n.period && <div><dt className="inline text-neu-sub">Periode: </dt><dd className="inline">{n.period}</dd></div>}
                            {n.actor && <div><dt className="inline text-neu-sub">{n.kind === 'revision' ? 'Diminta oleh: ' : 'Oleh: '}</dt><dd className="inline font-semibold">{n.actor}{n.actor_role ? ` (${n.actor_role})` : ''}</dd></div>}
                            {n.acted_at && <div><dt className="inline text-neu-sub">Waktu: </dt><dd className="inline">{n.acted_at} WIB</dd></div>}
                            {n.reason && (
                                <div className="sm:col-span-2 mt-1 rounded-xl bg-neu-bad/10 px-3 py-2 text-neu-bad">
                                    <dt className="inline font-semibold">Catatan revisi: </dt><dd className="inline">{n.reason}</dd>
                                </div>
                            )}
                        </dl>
                    )}

                    {hasList && (
                        <>
                            {(n.roles ?? []).length > 0 && <p className="mt-1 text-[11px] text-neu-sub">Sebagai: {n.roles.join(', ')}</p>}
                            <button type="button" onClick={() => setExpanded((v) => !v)} className="mt-1.5 text-[11px] font-semibold text-neu-accent hover:underline">
                                {expanded ? '▾ Sembunyikan' : '▸ Tampilkan'} daftar {n.total} perangkat
                            </button>
                            {expanded && <ItemsTable items={n.items} total={n.total} overdue={n.category === 'overdue'} />}
                        </>
                    )}

                    <div className="mt-3 flex flex-wrap gap-2">
                        <NeuButton href={n.open_url} size="sm" variant={n.read ? 'default' : 'primary'} className="!min-h-[32px] !py-1 !px-3 !text-xs">
                            {n.category === 'approval' ? 'Buka Dokumen' : 'Buka'}
                        </NeuButton>
                        {!n.read && (
                            <NeuButton onClick={markRead} size="sm" className="!min-h-[32px] !py-1 !px-3 !text-xs">Tandai dibaca</NeuButton>
                        )}
                        <NeuButton onClick={remove} size="sm" className="!min-h-[32px] !py-1 !px-3 !text-xs !text-neu-sub">Hapus</NeuButton>
                    </div>
                </div>
            </div>
        </NeuCard>
    );
}

function InboxTab({ inbox, filter, unreadCount }) {
    const go = (value) => router.get(route('notifications.inbox'), value === 'all' ? {} : { filter: value }, { preserveState: true, preserveScroll: true });
    const filters = [
        ['all', 'Semua'],
        ['unread', `Belum dibaca${unreadCount ? ` (${unreadCount})` : ''}`],
        ['schedule', 'Jadwal PM'],
        ['overdue', 'Terlambat'],
        ['approval', 'Approval'],
    ];

    return (
        <>
            <div className="flex flex-wrap items-center gap-2 mb-4">
                {filters.map(([value, label]) => (
                    <NeuChip key={value} size="sm" active={filter === value} onClick={() => go(value)}>{label}</NeuChip>
                ))}
                <div className="ml-auto flex gap-2">
                    <NeuButton
                        size="sm"
                        disabled={!unreadCount}
                        onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}
                        className="!min-h-[34px] !py-1 !px-3 !text-xs"
                    >
                        Tandai semua dibaca
                    </NeuButton>
                    <NeuButton
                        size="sm"
                        onClick={() => confirm('Hapus semua notifikasi yang sudah dibaca?') && router.delete(route('notifications.clear-read'), { preserveScroll: true })}
                        className="!min-h-[34px] !py-1 !px-3 !text-xs !text-neu-sub"
                    >
                        Hapus yang sudah dibaca
                    </NeuButton>
                </div>
            </div>

            {inbox.data.length === 0 ? (
                <NeuCard className="text-center py-12">
                    <BellIcon className="w-10 h-10 mx-auto mb-3 text-neu-sub" />
                    <p className="text-sm text-neu-sub">{filter === 'all' ? 'Belum ada notifikasi.' : 'Tidak ada notifikasi untuk filter ini.'}</p>
                </NeuCard>
            ) : (
                <div className="space-y-3">
                    {inbox.data.map((n) => <InboxItem key={n.id} n={n} />)}
                </div>
            )}

            {inbox.last_page > 1 && (
                <div className="flex flex-wrap justify-center gap-1.5 mt-5">
                    {inbox.links.map((link, i) => (
                        <button
                            key={i}
                            type="button"
                            disabled={!link.url || link.active}
                            onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                            className={`neu-chip neu-chip-sm ${link.active ? 'neu-chip-active' : ''} disabled:opacity-50`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </>
    );
}


/**
 * Kotak Masuk notifikasi in-app. Halaman berdiri sendiri (tidak ada di menu
 * mana pun); dibuka dari lonceng di header → "Lihat semua notifikasi".
 */
export default function Inbox({ auth, inbox, filter = 'all', unreadCount = 0 }) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Kotak Masuk</h1>
                    <p className="text-sm text-neu-sub mt-0.5 max-w-2xl">
                        Notifikasi untuk Anda: jadwal PM, keterlambatan, dan dokumen approval.
                    </p>
                </div>

                <InboxTab inbox={inbox} filter={filter} unreadCount={unreadCount} />
            </div>
        </AuthenticatedLayout>
    );
}
