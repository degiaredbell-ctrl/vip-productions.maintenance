import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';
import ApplicationLogo from '@/Components/ApplicationLogo';

// Label ramah untuk aksi yang paling sering muncul. Yang tidak terdaftar
// tetap ditampilkan apa adanya (format `domain.aksi`).
const ACTION_LABELS = {
    'machine.create': 'Tambah Mesin',
    'machine.update': 'Ubah Mesin',
    'machine.delete': 'Hapus Mesin',
    'machine.restore': 'Pulihkan Mesin',
    'machine.import': 'Impor Mesin',
    'utility.create': 'Tambah Utility',
    'utility.update': 'Ubah Utility',
    'utility.delete': 'Hapus Utility',
    'component.create': 'Tambah Komponen',
    'component.update': 'Ubah Komponen',
    'component.delete': 'Hapus Komponen',
    'component.assign': 'Penugasan Komponen',
    'sparepart.create': 'Tambah Suku Cadang',
    'sparepart.update': 'Ubah Suku Cadang',
    'sparepart.delete': 'Hapus Suku Cadang',
    'sparepart.restore': 'Pulihkan Suku Cadang',
    'sparepart.movement': 'Mutasi Suku Cadang',
    'sparepart.import': 'Impor Suku Cadang',
    'user.create': 'Tambah Pengguna',
    'user.update': 'Ubah Pengguna',
    'user.delete': 'Hapus Pengguna',
    'role.update': 'Ubah Role',
    'role.permissions': 'Ubah Permission Role',
    'pm.submit': 'Kirim PM',
    'pm.sign': 'Tanda Tangan PM',
    'pm.acknowledge': 'Persetujuan User PIC',
    'pm.approve': 'Persetujuan Atasan',
    'pm.reject': 'Tolak PM',
    'pm.revise': 'Revisi PM',
    'pm.delete': 'Hapus PM Record',
    'backup.create': 'Buat Backup',
    'backup.restore': 'Restore Database',
    'backup.delete': 'Hapus Backup',
    'backup.failed': 'Backup Gagal',
};

const GROUP_VARIANTS = {
    backup: 'issue',
    user: 'progress',
    role: 'progress',
    pm: 'progress',
};

const variantFor = (group) => GROUP_VARIANTS[group] ?? 'default';

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
    return String(value);
};

export default function ActivityLogs({ auth, logs, filters = {}, actions = [], users = [], stats = {} }) {
    const [localFilters, setLocalFilters] = useState(filters);
    const [expanded, setExpanded] = useState({});
    const [showFilters, setShowFilters] = useState(false);

    const applyFilters = (patch) => {
        const next = { ...localFilters, ...patch, page: 1 };
        setLocalFilters(next);
        router.get(route('admin.activity-logs'), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const goToPage = (page) => {
        router.get(route('admin.activity-logs'), { ...localFilters, page }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setLocalFilters({});
        router.get(route('admin.activity-logs'), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Pencarian diketik cepat; tunda 300ms supaya tidak membanjiri server.
    const debouncedSearch = useMemo(() => {
        let timer;
        return (value) => {
            clearTimeout(timer);
            timer = setTimeout(() => applyFilters({ q: value }), 300);
        };
    }, [localFilters]);

    const toggle = (id) => setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));

    const active = (value) => value !== undefined && value !== null && value !== '';

    const hasFilter = Object.values(localFilters).some(active);

    const changeEntries = (changes) => {
        if (!changes || typeof changes !== 'object') return [];
        return Object.entries(changes);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-6">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Activity Logs</h1>
                    <p className="text-neu-sub text-sm mt-1">
                        Jejak aktivitas pengguna dan perubahan data di seluruh sistem
                    </p>
                </div>

                <div className="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <NeuCard className="!p-4">
                        <p className="text-xs text-neu-sub">Total Aktivitas</p>
                        <p className="text-2xl font-bold mt-1">{stats.total ?? logs.total ?? 0}</p>
                    </NeuCard>
                    <NeuCard className="!p-4">
                        <p className="text-xs text-neu-sub">Hari Ini</p>
                        <p className="text-2xl font-bold mt-1">{stats.today ?? 0}</p>
                    </NeuCard>
                    <NeuCard className="!p-4">
                        <p className="text-xs text-neu-sub">Pelaku Aktif</p>
                        <p className="text-2xl font-bold mt-1">{stats.actors ?? 0}</p>
                    </NeuCard>
                </div>

                <NeuCard className="mb-4 !p-3.5">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-2">
                        <div className="relative flex-1">
                            <input
                                type="search"
                                placeholder="Cari aksi, pelaku, subjek, atau IP…"
                                value={localFilters.q ?? ''}
                                onChange={(e) => {
                                    setLocalFilters((prev) => ({ ...prev, q: e.target.value }));
                                    debouncedSearch(e.target.value);
                                }}
                                className="neu-input pr-11"
                                aria-label="Cari aktivitas"
                            />
                            {localFilters.q && (
                                <button
                                    type="button"
                                    onClick={() => applyFilters({ q: '' })}
                                    aria-label="Bersihkan pencarian"
                                    className="absolute right-2 top-1/2 -translate-y-1/2 grid place-items-center h-8 w-8 rounded-full text-neu-sub hover:text-neu-bad hover:shadow-neu-in"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <NeuButton
                                type="button"
                                variant={showFilters ? 'primary' : 'default'}
                                onClick={() => setShowFilters((v) => !v)}
                                className="!min-h-[44px] !px-4 text-xs"
                            >
                                Filter
                            </NeuButton>
                            {hasFilter && (
                                <button type="button" onClick={clearFilters} className="neu-btn !min-h-[44px] !px-3 text-xs">
                                    Bersihkan
                                </button>
                            )}
                        </div>
                    </div>

                    {showFilters && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                            <select
                                value={localFilters.action ?? ''}
                                onChange={(e) => applyFilters({ action: e.target.value || undefined })}
                                className="neu-input"
                                aria-label="Filter aksi"
                            >
                                <option value="">Semua Aksi</option>
                                {actions.map((a) => (
                                    <option key={a} value={a}>{ACTION_LABELS[a] ?? a}</option>
                                ))}
                            </select>
                            <select
                                value={localFilters.user_id ?? ''}
                                onChange={(e) => applyFilters({ user_id: e.target.value || undefined })}
                                className="neu-input"
                                aria-label="Filter pelaku"
                            >
                                <option value="">Semua Pelaku</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                            <input
                                type="date"
                                value={localFilters.from ?? ''}
                                onChange={(e) => applyFilters({ from: e.target.value || undefined })}
                                className="neu-input"
                                aria-label="Dari tanggal"
                            />
                            <input
                                type="date"
                                value={localFilters.to ?? ''}
                                onChange={(e) => applyFilters({ to: e.target.value || undefined })}
                                className="neu-input"
                                aria-label="Sampai tanggal"
                            />
                        </div>
                    )}
                </NeuCard>

                {logs.data.length === 0 ? (
                    <NeuCard>
                        <div className="text-center py-12">
                            <ApplicationLogo className="w-16 h-16 mx-auto mb-4 text-neu-sub" />
                            <p className="text-neu-sub text-lg">Tidak ada aktivitas yang cocok</p>
                            <p className="text-neu-sub text-sm mt-1">Coba ubah filter atau kata kunci pencarian</p>
                        </div>
                    </NeuCard>
                ) : (
                    <div className="space-y-2">
                        {logs.data.map((log) => {
                            const entries = changeEntries(log.changes);
                            const isOpen = Boolean(expanded[log.id]);

                            return (
                                <NeuCard key={log.id} className="!p-3.5">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <NeuPill variant={variantFor(log.action_group)}>
                                                    {ACTION_LABELS[log.action] ?? log.action}
                                                </NeuPill>
                                                <span className="text-xs text-neu-sub font-mono">{log.action}</span>
                                            </div>
                                            <p className="text-sm mt-1.5">
                                                <b>{log.user_name}</b>
                                                {log.subject_type && (
                                                    <span className="text-neu-sub"> · {log.subject_type}{log.subject_id ? ` #${log.subject_id}` : ''}</span>
                                                )}
                                            </p>
                                            {log.ip && <p className="text-xs text-neu-sub mt-0.5">IP {log.ip}</p>}
                                        </div>
                                        <div className="text-right flex-none">
                                            <p className="text-xs text-neu-sub whitespace-nowrap">{log.created_at}</p>
                                            {log.created_at_human && (
                                                <p className="text-[11px] text-neu-sub">{log.created_at_human}</p>
                                            )}
                                            {entries.length > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() => toggle(log.id)}
                                                    className="text-xs text-neu-accent mt-1 hover:underline"
                                                    aria-expanded={isOpen}
                                                >
                                                    {isOpen ? 'Sembunyikan' : `Detail (${entries.length})`}
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {isOpen && entries.length > 0 && (
                                        <div className="neu-inset mt-3 p-3 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                                            {entries.map(([key, value]) => (
                                                <div key={key} className="min-w-0">
                                                    <p className="text-[11px] text-neu-sub font-mono truncate">{key}</p>
                                                    <p className="text-xs break-words">{formatValue(value)}</p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </NeuCard>
                            );
                        })}
                    </div>
                )}

                {logs.last_page > 1 && (
                    <div className="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p className="text-sm text-neu-sub">
                            Menampilkan {logs.from}–{logs.to} dari {logs.total} aktivitas
                        </p>
                        <div className="flex gap-1.5">
                            {logs.current_page > 1 && (
                                <NeuButton
                                    onClick={() => goToPage(logs.current_page - 1)}
                                    className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                >
                                    ‹ Sebelumnya
                                </NeuButton>
                            )}
                            {logs.current_page < logs.last_page && (
                                <NeuButton
                                    onClick={() => goToPage(logs.current_page + 1)}
                                    className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                >
                                    Selanjutnya ›
                                </NeuButton>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
