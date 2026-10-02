import { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuRing from '@/Components/NeuRing';
import NeuPill from '@/Components/NeuPill';

const PERIODS_FALLBACK = [];
const STATUS_FILTERS = [
    { value: 'all', label: 'Semua' },
    { value: 'todo', label: 'Belum' },
    { value: 'done', label: 'Selesai' },
    { value: 'issue', label: 'Kendala' },
];

const DOT_LEGEND = [
    { dot: 'green', label: 'Semua selesai' },
    { dot: 'blue', label: 'Belum selesai (periode ini)' },
    { dot: 'orange', label: 'Belum (periode lalu)' },
    { dot: 'red', label: 'Tunggakan lebih lama' },
];

const PERIOD_LABELS = {
    green: 'Semua mesin selesai',
    blue: 'Mesin ada yang belum selesai di periode ini',
    orange: 'Periode sebelumnya ada mesin belum selesai',
    red: 'Ada tunggakan lebih lama',
    muted: 'Periode belum dibuka',
};

export default function Dashboard({ auth, machines, stats, periods, years, currentPeriod, currentYear, search, statusFilter, isFuturePeriod }) {
    const [filters, setFilters] = useState({
        period: currentPeriod,
        year: currentYear,
        q: search ?? '',
        status: statusFilter ?? 'all',
    });
    const [isLoading, setIsLoading] = useState(false);
    const searchTimer = useRef(null);

    // Selaraskan state lokal dengan props setiap kali server merespons.
    useEffect(() => {
        setFilters({
            period: currentPeriod,
            year: currentYear,
            q: search ?? '',
            status: statusFilter ?? 'all',
        });
    }, [currentPeriod, currentYear, search, statusFilter]);

    const applyFilters = (patch) => {
        const next = { ...filters, ...patch };
        setFilters(next);
        setIsLoading(true);

        router.get(route('dashboard'), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => setIsLoading(false),
        });
    };

    const handlePeriodChange = (period) => {
        if (period === filters.period) return;
        applyFilters({ period });
    };

    const handleYearChange = (year) => {
        if (year === filters.year) return;
        applyFilters({ year });
    };

    const handleStatusFilter = (status) => {
        if (status === filters.status) return;
        applyFilters({ status });
    };

    const handleSearch = (e) => {
        const q = e.target.value;
        setFilters((prev) => ({ ...prev, q }));

        clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => {
            setIsLoading(true);
            router.get(route('dashboard'), { ...filters, q }, {
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

    const percentage = stats.total > 0 ? Math.round(stats.done / stats.total * 100) : 0;

    const statusLabels = { done: 'Selesai', todo: 'Belum', issue: 'Kendala' };
    const statusVariants = { done: 'done', todo: 'todo', issue: 'issue' };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                {/* Header */}
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl font-bold">Preventive Maintenance</h1>
                </div>

                {/* Filter tahun */}
                <div
                    className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-2"
                    role="group"
                    aria-label="Filter tahun"
                >
                    {(years ?? []).map((y) => (
                        <NeuChip
                            key={y}
                            active={filters.year === y}
                            onClick={() => handleYearChange(y)}
                            aria-label={`Tahun ${y}`}
                        >
                            {y}
                        </NeuChip>
                    ))}
                </div>

                {/* Filter periode + dot notifikasi */}
                <div
                    className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-2"
                    role="group"
                    aria-label="Periode"
                >
                    {(periods ?? PERIODS_FALLBACK).map((p) => (
                        <NeuChip
                            key={p.value}
                            active={filters.period === p.value}
                            onClick={p.locked ? undefined : () => handlePeriodChange(p.value)}
                            disabled={p.locked}
                            dot={p.dot}
                            title={p.locked
                                ? `${p.label} — belum dibuka, periode ini belum memasuki bulan`
                                : `${p.label} — ${p.done}/${p.total} mesin selesai · ${PERIOD_LABELS[p.dot] ?? ''}`}
                            aria-label={`Periode ${p.label}${p.isCurrent ? ' (periode berjalan)' : ''}`}
                            className={[
                                p.locked ? 'neu-chip-locked' : '',
                                p.isCurrent ? 'neu-chip-current' : '',
                            ].filter(Boolean).join(' ')}
                        >
                            {p.label}
                        </NeuChip>
                    ))}
                </div>

                {/* Legenda dot */}
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 px-1.5 mb-4 text-[11px] text-neu-sub">
                    {DOT_LEGEND.map((l) => (
                        <span key={l.dot} className="inline-flex items-center gap-1.5">
                            <span className={`neu-dot neu-dot-${l.dot}`} aria-hidden="true" />
                            {l.label}
                        </span>
                    ))}
                </div>

                {/* Summary */}
                <NeuCard className="mb-4">
                    <div className="flex items-center gap-5">
                        <NeuRing percentage={percentage} />
                        <div className="flex-1 grid grid-cols-3 gap-2.5">
                            {['done', 'todo', 'issue'].map((key) => (
                                <div key={key} className="neu-inset py-3 px-2 text-center">
                                    <span className={`text-xl font-bold block ${key === 'done' ? 'text-neu-accent' : key === 'todo' ? 'text-neu-warn' : 'text-neu-bad'}`}>
                                        {stats[key]}
                                    </span>
                                    <span className="text-[11px] text-neu-sub">{statusLabels[key]}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </NeuCard>

                {/* Search */}
                <div className="mb-3">
                    <div className="relative">
                        <input
                            type="search"
                            placeholder="Cari kode atau nama mesin…"
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
                                className="absolute right-2 top-1/2 -translate-y-1/2 grid place-items-center h-8 w-8 rounded-full text-neu-sub transition-all duration-150 hover:text-neu-bad hover:shadow-neu-in focus:outline-none focus:ring-2 focus:ring-neu-accent/40"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        )}
                    </div>
                </div>

                {/* Status filter */}
                <div
                    className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-3"
                    role="group"
                    aria-label="Filter status"
                >
                    {STATUS_FILTERS.map((f) => (
                        <NeuChip
                            key={f.value}
                            active={filters.status === f.value}
                            onClick={() => handleStatusFilter(f.value)}
                            aria-label={`Filter ${f.label}`}
                        >
                            {f.label}
                        </NeuChip>
                    ))}
                </div>

                {isLoading && (
                    <p className="text-xs text-neu-sub text-center mb-3" role="status">
                        Memuat data…
                    </p>
                )}

                {/* Future period warning */}
                {isFuturePeriod && (
                    <NeuCard className="mb-4 !shadow-neu-in">
                        <p className="text-sm text-neu-warn font-semibold">Periode ini belum tiba dan tidak dapat diisi.</p>
                    </NeuCard>
                )}

                {/* Machine list */}
                <div className="grid gap-0 lg:grid-cols-2 lg:gap-x-5">
                    {machines.length > 0 ? machines.map((machine) => (
                        <Link
                            key={machine.id}
                            href={route('machines.pm.create', { machine: machine.id, period: filters.period, year: filters.year })}
                            className="neu-card-link flex items-center gap-3.5 w-full text-left p-3.5 mb-4 active:shadow-neu-in"
                        >
                            <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">
                                {machine.code}
                            </span>
                            <div className="min-w-0">
                                <b className="block text-sm truncate">{machine.name}</b>
                                <span className="text-xs text-neu-sub">
                                    Minggu {machine.week_group} · {machine.type}
                                </span>
                            </div>
                            <NeuPill variant={statusVariants[machine.status]} className="ml-auto">
                                {statusLabels[machine.status]}
                            </NeuPill>
                        </Link>
                    )) : (
                        <p className="text-neu-sub text-center py-6 col-span-2">Tidak ada mesin yang cocok.</p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}