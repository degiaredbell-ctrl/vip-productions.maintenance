import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';

const PERIOD_LABELS_LIST = {
    'Jan-Feb': 'Jan–Feb',
    'Mar-Apr': 'Mar–Apr',
    'Mei-Jun': 'Mei–Jun',
    'Jul-Ags': 'Jul–Ags',
    'Sep-Okt': 'Sep–Okt',
    'Nov-Des': 'Nov–Des',
};

const STATUS_ORDER = ['done', 'progress', 'todo', 'issue'];
const STATUS_LABELS = { done: 'Selesai', progress: 'Sedang Approval', todo: 'Belum', issue: 'Perlu Revisi' };
const STATUS_TILE_COLORS = { done: 'text-neu-accent', progress: 'text-neu-info', todo: 'text-neu-warn', issue: 'text-neu-bad' };
const STATUS_VARIANTS = { done: 'done', progress: 'progress', todo: 'todo', issue: 'issue' };

const DOT_LEGEND = [
    { dot: 'green', label: 'Semua selesai' },
    { dot: 'blue', label: 'Belum selesai (periode ini)' },
    { dot: 'orange', label: 'Belum (periode lalu)' },
    { dot: 'red', label: 'Tunggakan lebih lama' },
];

const PERIOD_TOOLTIP = {
    green: 'Semua mesin selesai',
    blue: 'Mesin ada yang belum selesai di periode ini',
    orange: 'Periode sebelumnya ada mesin belum selesai',
    red: 'Ada tunggakan lebih lama',
    muted: 'Periode belum dibuka',
};

export default function FormMaintenanceMesin({
    auth,
    machines = [],
    stats = { total: 0, done: 0, progress: 0, todo: 0, issue: 0 },
    periods = [],
    years = [],
    subCategories = [],
    subCategory = 'all',
    types = [],
    currentPeriod,
    currentYear,
    search = '',
    statusFilter = 'all',
    isFuturePeriod = false,
}) {
    const [filters, setFilters] = useState({
        period: currentPeriod,
        year: currentYear,
        q: search ?? '',
        status: statusFilter ?? 'all',
        sub: subCategory ?? 'all',
    });
    const [isLoading, setIsLoading] = useState(false);
    const searchTimer = useRef(null);

    useEffect(() => {
        setFilters({
            period: currentPeriod,
            year: currentYear,
            q: search ?? '',
            status: statusFilter ?? 'all',
            sub: subCategory ?? 'all',
        });
    }, [currentPeriod, currentYear, search, statusFilter, subCategory]);

    const applyFilters = (patch) => {
        const next = { ...filters, ...patch };
        setFilters(next);
        setIsLoading(true);
        router.get(route('form-maintenance.mesin'), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => setIsLoading(false),
        });
    };

    const handleYearChange = (year) => {
        if (year === filters.year) return;
        applyFilters({ year });
    };

    const handleSearch = (e) => {
        const q = e.target.value;
        setFilters((prev) => ({ ...prev, q }));
        clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => {
            setIsLoading(true);
            router.get(route('form-maintenance.mesin'), { ...filters, q }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onFinish: () => setIsLoading(false),
            });
        }, 350);
    };

    const clearSearch = () => {
        clearTimeout(searchTimer.current);
        if (!filters.q) return;
        applyFilters({ q: '' });
    };
    useEffect(() => () => clearTimeout(searchTimer.current), []);

    const percentage = stats.total > 0 ? Math.round((stats.done / stats.total) * 100) : 0;
    const typeLabels = useMemo(() => Object.fromEntries((types ?? []).map((t) => [t.value, t.label])), [types]);

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Form Maintenance - Mesin</h1>
                </div>

                <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-3" role="group" aria-label="Filter tahun">
                    {(years ?? []).map((y) => (
                        <NeuChip key={y} active={filters.year === y} onClick={() => handleYearChange(y)} aria-label={`Tahun ${y}`}>{y}</NeuChip>
                    ))}
                </div>

                {isLoading && <p className="text-xs text-neu-sub text-center mb-3" role="status">Memuat data…</p>}

                {/* Ringkasan Status */}
                <NeuCard className="mb-4">
                    <div className="flex flex-col sm:flex-row items-center gap-4 sm:gap-5">
                        <div className="flex-1 w-full grid grid-cols-4 gap-2">
                            {STATUS_ORDER.map((key) => (
                                <div key={key} className="neu-inset py-3 px-1.5 text-center">
                                    <span className={`text-xl font-bold block ${STATUS_TILE_COLORS[key]}`}>{stats[key] ?? 0}</span>
                                    <span className="text-[10px] text-neu-sub leading-tight block">{STATUS_LABELS[key]}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <p className="text-xs text-neu-sub mt-3">Total mesin: {stats.total}</p>
                </NeuCard>

                {/* Filter Periode */}
                <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-2" role="group" aria-label="Periode">
                    {(periods ?? []).map((p) => (
                        <NeuChip
                            key={p.value}
                            active={filters.period === p.value}
                            onClick={p.locked ? undefined : () => router.get(route('form-maintenance.mesin'), { ...filters, period: p.value }, { preserveState: true, preserveScroll: true })}
                            disabled={p.locked}
                            dot={p.dot}
                            title={p.locked ? `${p.label} — belum dibuka` : `${p.label} — ${p.done}/${p.total} selesai · ${PERIOD_TOOLTIP[p.dot] ?? ''}`}
                            className={[p.locked ? 'neu-chip-locked' : '', p.isCurrent ? 'neu-chip-current' : ''].filter(Boolean).join(' ')}
                        >{p.label}</NeuChip>
                    ))}
                </div>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 px-1.5 mb-4 text-[11px] text-neu-sub">
                    {DOT_LEGEND.map((l) => (
                        <span key={l.dot} className="inline-flex items-center gap-1.5">
                            <span className={`neu-dot neu-dot-${l.dot}`} aria-hidden="true" />
                            {l.label}
                        </span>
                    ))}
                </div>

                {/* Pencarian */}
                <div className="mb-3">
                    <div className="relative">
                        <input
                            type="search"
                            placeholder="Cari kode, nama, area (C.7), atau lokasi…"
                            value={filters.q}
                            onChange={handleSearch}
                            className="neu-input pr-11"
                            aria-label="Cari mesin"
                        />
                        {filters.q && (
                            <button
                                type="button"
                                onClick={clearSearch}
                                aria-label="Bersihkan pencarian"
                                className="absolute right-2 top-1/2 -translate-y-1/2 grid place-items-center h-8 w-8 rounded-full text-neu-sub hover:text-neu-bad hover:shadow-neu-in"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        )}
                    </div>
                </div>

                {/* Filter Status */}
                <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-3" role="group" aria-label="Filter status">
                    {[{value:'all',label:'Semua'},{value:'todo',label:'Belum'},{value:'progress',label:'Sedang Approval'},{value:'done',label:'Selesai'},{value:'issue',label:'Perlu Revisi'}].map((f) => (
                        <NeuChip key={f.value} active={filters.status === f.value} onClick={() => applyFilters({ status: f.value })}>{f.label}</NeuChip>
                    ))}
                </div>

                {/* Filter Sub Kategori */}
                <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-3" role="group" aria-label="Filter sub kategori">
                    <NeuChip active={filters.sub === 'all'} onClick={() => applyFilters({ sub: 'all' })}>Semua Area</NeuChip>
                    {(subCategories ?? []).map((s) => (
                        <NeuChip key={s} active={filters.sub === s} onClick={() => applyFilters({ sub: s })}>{s}</NeuChip>
                    ))}
                </div>

                {isFuturePeriod && (
                    <NeuCard className="mb-4 !shadow-neu-in">
                        <p className="text-sm text-neu-warn font-semibold">Periode ini belum tiba dan tidak dapat diisi.</p>
                    </NeuCard>
                )}

                <p className="text-xs text-neu-sub mb-3">Menampilkan {machines.length} dari {stats.total} mesin</p>

                {/* Daftar Mesin */}
                <div className="grid grid-cols-1 gap-0 sm:grid-cols-2 sm:gap-x-4 xl:grid-cols-3 xl:gap-x-5">
                    {machines.length > 0 ? machines.map((machine) => (
                        <Link
                            key={machine.id}
                            href={route('machines.pm.create', {
                                machine: machine.id,
                                period: filters.period,
                                year: filters.year,
                                sub: filters.sub !== 'all' ? filters.sub : undefined,
                                status: filters.status !== 'all' ? filters.status : undefined,
                                q: filters.q || undefined,
                            })}
                            className="neu-card-link flex items-start gap-3.5 w-full text-left p-3.5 mb-4 active:shadow-neu-in"
                        >
                            <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">{machine.sub_category ?? '—'}</span>
                            <div className="min-w-0 flex-1">
                                <b className="block text-sm truncate">{machine.name}</b>
                                <span className="block text-xs text-neu-sub truncate">{machine.code} · {typeLabels[machine.type] ?? machine.type} · Minggu {machine.week_group}</span>
                                {machine.progress_note && <span className="block text-[11px] font-semibold text-neu-info truncate">{machine.progress_note}</span>}
                            </div>
                            <NeuPill variant={STATUS_VARIANTS[machine.status]} className="ml-auto self-start">{STATUS_LABELS[machine.status]}</NeuPill>
                        </Link>
                    )) : (
                        <p className="text-neu-sub text-center py-6 sm:col-span-2 xl:col-span-3">Tidak ada mesin yang cocok.</p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}