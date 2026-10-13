import { useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import ApplicationLogo from '@/Components/ApplicationLogo';

const STATUS_LABELS = {
    draft: 'Menunggu Tanda Tangan',
    submitted: 'Sedang Approval (User PIC)',
    pic_approved: 'Sedang Approval (Atasan)',
    approved: 'Selesai',
    rejected: 'Perlu Revisi',
};

const STATUS_COLORS = {
    draft: 'text-neu-sub',
    submitted: 'text-neu-info',
    pic_approved: 'text-neu-info',
    approved: 'text-neu-accent',
    rejected: 'text-neu-bad',
};

const STATUS_VARIANTS = {
    draft: 'todo',
    submitted: 'progress',
    pic_approved: 'progress',
    approved: 'done',
    rejected: 'issue',
};

export default function AdminPmRecordsIndex({ auth, records, filters, years, periods, statuses }) {
    const [localFilters, setLocalFilters] = useState(filters);
    const page = usePage();
    const { can } = page.props.auth;

    const applyFilters = (patch) => {
        const next = { ...localFilters, ...patch, page: 1 };
        setLocalFilters(next);
        router.get(route('admin.pm-records'), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setLocalFilters({});
        router.get(route('admin.pm-records'), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleSearch = (e) => {
        const q = e.target.value;
        setLocalFilters((prev) => ({ ...prev, q, page: 1 }));
    };

    const debouncedSearch = useMemo(
        () => {
            let timer;
            return (value) => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    applyFilters({ q: value });
                }, 300);
            };
        },
        []
    );

    const deleteRecord = (record) => {
        if (!confirm(`Hapus PM Record untuk ${record.machine?.code} (${record.period} ${record.year})?\n\nTindakan ini permanen dan tidak dapat dibatalkan. Semua item, tanda tangan, revisi, dan log audit terkait akan ikut terhapus.`)) {
            return;
        }

        router.delete(route('admin.pm-records.destroy', record.id), {
            preserveScroll: true,
        });
    };

    const statusOptions = statuses.map((s) => ({ value: s.value, label: s.label ?? s.shortLabel ?? s.value }));

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-6">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">PM Records (Admin)</h1>
                    <p className="text-neu-sub text-sm mt-1">Lihat, filter, dan hapus semua PM Record di sistem</p>
                </div>

                {!localFilters.type && (
                    <div className="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button
                            type="button"
                            onClick={() => applyFilters({ type: 'generic' })}
                            className="neu-card text-left p-6 hover:shadow-neu-in transition-shadow"
                        >
                            <h2 className="text-lg font-bold">Machine</h2>
                            <p className="text-sm text-neu-sub mt-1">Tampilkan PM Records kategori Mesin</p>
                        </button>
                        <button
                            type="button"
                            onClick={() => applyFilters({ type: 'utility' })}
                            className="neu-card text-left p-6 hover:shadow-neu-in transition-shadow"
                        >
                            <h2 className="text-lg font-bold">Utilities</h2>
                            <p className="text-sm text-neu-sub mt-1">Tampilkan PM Records kategori Utility</p>
                        </button>
                    </div>
                )}

                {localFilters.type && (
                    <div className="mb-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <NeuButton
                                type="button"
                                variant="ghost"
                                onClick={() => applyFilters({ type: undefined })}
                                className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                            >
                                ‹ Kembali
                            </NeuButton>
                            <h2 className="text-sm font-bold">
                                {localFilters.type === 'utility' ? 'Utilities' : 'Machine'}
                            </h2>
                        </div>
                    </div>
                )}

                {/* Filter Form */}
                {localFilters.type && (
                <NeuCard className="mb-4">
                    <form onSubmit={(e) => e.preventDefault()} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            {/* Search */}
                            <div className="relative">
                                <input
                                    type="search"
                                    placeholder="Cari kode, nama mesin, teknisi, catatan…"
                                    value={localFilters.q ?? ''}
                                    onChange={handleSearch}
                                    className="neu-input pr-11"
                                    aria-label="Cari PM Record"
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

                            {/* Status Filter */}
                            <div>
                                <select
                                    value={localFilters.status ?? ''}
                                    onChange={(e) => applyFilters({ status: e.target.value || undefined })}
                                    className="neu-input"
                                    aria-label="Filter status"
                                >
                                    <option value="">Semua Status</option>
                                    {statusOptions.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Period Filter */}
                            <div>
                                <select
                                    value={localFilters.period ?? ''}
                                    onChange={(e) => applyFilters({ period: e.target.value || undefined })}
                                    className="neu-input"
                                    aria-label="Filter periode"
                                >
                                    <option value="">Semua Periode</option>
                                    {periods?.map((p) => (
                                        <option key={p} value={p}>{p}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Year Filter */}
                            <div>
                                <select
                                    value={localFilters.year ?? ''}
                                    onChange={(e) => applyFilters({ year: e.target.value || undefined })}
                                    className="neu-input"
                                    aria-label="Filter tahun"
                                >
                                    <option value="">Semua Tahun</option>
                                    {years?.map((y) => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Type Filter */}
                            <div>
                                <select
                                    value={localFilters.type ?? ''}
                                    onChange={(e) => applyFilters({ type: e.target.value || undefined })}
                                    className="neu-input"
                                    aria-label="Filter tipe"
                                >
                                    <option value="">Semua Tipe</option>
                                    <option value="generic">Mesin</option>
                                    <option value="utility">Utility</option>
                                </select>
                            </div>
                        </div>

                        {(Object.keys(localFilters).length > 0 && Object.values(localFilters).some(v => v)) && (
                            <div className="flex items-center justify-end pt-2">
                                <button type="button" onClick={clearFilters} className="neu-btn !min-h-[40px] !px-3 !py-1.5 text-xs">
                                    Bersihkan Filter
                                </button>
                            </div>
                        )}
                    </form>
                </NeuCard>
                )}

                {/* Records Table */}
                {localFilters.type && (
                    <NeuCard>
                        {records.data.length === 0 ? (
                        <div className="text-center py-12">
                            <ApplicationLogo className="w-16 h-16 mx-auto mb-4 text-neu-sub" />
                            <p className="text-neu-sub text-lg">Tidak ada PM Record yang cocok</p>
                            <p className="text-neu-sub text-sm mt-1">Coba ubah filter atau pencarian</p>
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="neu-table w-full">
                                    <thead>
                                        <tr>
                                            <th scope="col" className="w-8"></th>
                                            <th scope="col">Mesin</th>
                                            <th scope="col">Periode / Tahun</th>
                                            <th scope="col">Teknisi</th>
                                            <th scope="col">User PIC</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Diperbarui</th>
                                            <th scope="col" className="w-24">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {records.data.map((record) => (
                                            <tr key={record.id}>
                                                <td>
                                                    <span className="neu-inset w-8 h-8 flex-none grid place-items-center font-bold text-xs text-neu-accent">
                                                        {record.machine?.sub_category ?? '—'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={route('machines.pm.create', {
                                                                machine: record.machine?.id,
                                                                period: record.period,
                                                                year: record.year,
                                                            })}
                                                            className="block"
                                                        >
                                                            <b className="block text-sm truncate">{record.machine?.name ?? '—'}</b>
                                                            <span className="block text-xs text-neu-sub truncate">{record.machine?.code} · {record.machine?.type === 'utility' ? 'Utility' : 'Mesin'}</span>
                                                        </Link>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap">
                                                    <span className="text-sm font-medium">{record.period}</span>
                                                    <span className="block text-xs text-neu-sub">{record.year}</span>
                                                </td>
                                                <td className="max-w-[150px]">
                                                    <span className="text-sm truncate block">{record.technician_name ?? '—'}</span>
                                                    {record.technician && (
                                                        <span className="text-xs text-neu-sub">{record.technician.email}</span>
                                                    )}
                                                </td>
                                                <td className="max-w-[150px]">
                                                    <span className="text-sm truncate block">{record.picUser?.name ?? '—'}</span>
                                                </td>
                                                <td>
                                                    <NeuPill variant={STATUS_VARIANTS[record.status] ?? 'todo'}>
                                                        {STATUS_LABELS[record.status] ?? record.status}
                                                    </NeuPill>
                                                </td>
                                                <td className="whitespace-nowrap text-sm text-neu-sub">
                                                    {record.updated_at ? new Date(record.updated_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'}
                                                </td>
                                                <td>
                                                    <div className="flex items-center gap-1.5 justify-end">
                                                        <Link
                                                            href={route('machines.pm.create', {
                                                                machine: record.machine?.id,
                                                                period: record.period,
                                                                year: record.year,
                                                            })}
                                                            className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs"
                                                        >
                                                            Lihat
                                                        </Link>
                                                        <NeuButton
                                                            onClick={() => deleteRecord(record)}
                                                            variant="bad"
                                                            className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                                            aria-label={`Hapus PM Record ${record.machine?.code}`}
                                                        >
                                                            Hapus
                                                        </NeuButton>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {records.last_page > 1 && (
                                <div className="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                                    <p className="text-sm text-neu-sub">
                                        Menampilkan {records.from}–{records.to} dari {records.total} record
                                    </p>
                                    <div className="flex gap-1.5">
                                        {records.current_page > 1 && (
                                            <NeuButton
                                                onClick={() => router.get(route('admin.pm-records'), { ...localFilters, page: records.current_page - 1 }, { preserveState: true, preserveScroll: true, replace: true })}
                                                className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                            >
                                                ‹ Sebelumnya
                                            </NeuButton>
                                        )}
                                        {records.current_page < records.last_page && (
                                            <NeuButton
                                                onClick={() => router.get(route('admin.pm-records'), { ...localFilters, page: records.current_page + 1 }, { preserveState: true, preserveScroll: true, replace: true })}
                                                className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                            >
                                                Selanjutnya ›
                                            </NeuButton>
                                        )}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </NeuCard>
                )}
            </div>
        </AuthenticatedLayout>
    );
}