import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuRing from '@/Components/NeuRing';
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
const STATUS_COLORS = { done: '#22c55e', progress: '#60a5fa', todo: '#f59e0b', issue: '#ef4444' };
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

export default function Dashboard({
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
    reportMachine = { byPeriod: [], byStatus: { done:0,progress:0,todo:0,issue:0,total:0,percent:0 }, totalUnits: 0 },
    reportUtility = { byPeriod: [], byStatus: { done:0,progress:0,todo:0,issue:0,total:0,percent:0 }, totalUnits: 0 },
    reportYears = [],
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
    const [tab, setTab] = useState('mesin');
    // Filter periode bersifat tampilan saja ('all' = ringkasan setahun penuh).
    // Chart "Progress per Periode" tetap menampilkan semua periode sebagai konteks.
    const [periodFilter, setPeriodFilter] = useState('all');

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
        router.get(route('dashboard'), next, {
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

    const percentage = stats.total > 0 ? Math.round((stats.done / stats.total) * 100) : 0;
    const typeLabels = useMemo(() => Object.fromEntries((types ?? []).map((t) => [t.value, t.label])), [types]);

    const canExport = Boolean(auth?.can?.['report.export']);
    const exportParams = {
        year: filters.year,
        period: filters.period,
        status: filters.status !== 'all' ? filters.status : undefined,
        sub: filters.sub !== 'all' ? filters.sub : undefined,
        q: filters.q || undefined,
    };

    const activeReport = tab === 'utility' ? reportUtility : reportMachine;
    const reportYearsList = (reportYears?.length ? reportYears : years) ?? [];

    const BarChart = ({ data, color = '#38bdf8', showPercent = false, highlightKey = null }) => {
        const max = Math.max(1, ...data.map((d) => (showPercent ? d.percent || 0 : d.value || 0)));
        return (
            <div className="flex items-end gap-2 sm:gap-3 h-[110px] w-full">
                {data.map((d) => {
                    const val = showPercent ? d.percent || 0 : d.value || 0;
                    const h = `${Math.max(6, (val / max) * 100)}%`;
                    const isHighlighted = highlightKey === d.key;
                    const isDimmed = highlightKey != null && !isHighlighted;
                    return (
                        <div key={d.key} className="flex-1 flex flex-col items-center justify-end h-full min-w-[28px]">
                            {val > 0 && (
                                <span className={`text-[11px] sm:text-xs font-semibold leading-none mb-1 tabular-nums ${isHighlighted ? 'text-neu-accent' : 'text-neu-text'}`}>
                                    {showPercent ? `${val}%` : val}
                                </span>
                            )}
                            <div
                                className={`w-full rounded-t-md transition-opacity duration-150 ${isHighlighted ? 'ring-2 ring-neu-accent/50 ring-offset-1' : ''}`}
                                style={{ height: h, backgroundColor: isHighlighted ? '#0ea5e9' : color, maxHeight: '95%', opacity: isDimmed ? 0.5 : 1 }}
                            />
                            <span className={`text-[10px] sm:text-[11px] mt-1.5 text-center leading-none whitespace-nowrap ${isHighlighted ? 'text-neu-accent font-semibold' : 'text-neu-sub'}`}>
                                {d.label}
                            </span>
                        </div>
                    );
                })}
            </div>
        );
    };

    const byPeriodData = (activeReport.byPeriod ?? []).map((p) => ({
        key: p.period,
        label: PERIOD_LABELS_LIST[p.label] || p.label,
        value: p.done,
        total: p.total,
        percent: p.percent,
        locked: p.locked,
        isCurrent: p.isCurrent,
        dot: p.dot,
    }));

    const selectedPeriod = periodFilter === 'all'
        ? null
        : (activeReport.byPeriod ?? []).find((p) => p.period === periodFilter) ?? null;

    // Ringkasan yang ditampilkan: agregat setahun saat "Semua Periode", atau
    // angka satu periode saja saat filter periode dipilih.
    const periodStats = selectedPeriod
        ? {
            done: selectedPeriod.done ?? 0,
            progress: selectedPeriod.progress ?? 0,
            todo: selectedPeriod.todo ?? 0,
            issue: selectedPeriod.issue ?? 0,
            total: selectedPeriod.total ?? 0,
            percent: selectedPeriod.percent ?? 0,
        }
        : {
            done: activeReport.byStatus?.done ?? 0,
            progress: activeReport.byStatus?.progress ?? 0,
            todo: activeReport.byStatus?.todo ?? 0,
            issue: activeReport.byStatus?.issue ?? 0,
            total: activeReport.byStatus?.total ?? 0,
            percent: activeReport.byStatus?.percent ?? 0,
        };

    const periodScopeLabel = selectedPeriod
        ? `Periode ${selectedPeriod.label} ${filters.year}`
        : `Semua Periode ${filters.year}`;

    const byStatusData = STATUS_ORDER.map((k) => ({
        key: k,
        label: STATUS_LABELS[k],
        value: periodStats[k] ?? 0,
        color: STATUS_COLORS[k],
    }));

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-4">
                    <p className="text-neu-sub text-xs">PT Verra Inter Pangan</p>
                    <h1 className="text-lg sm:text-xl font-bold">Dashboard Report</h1>
                </div>

                <div className="flex flex-wrap gap-2 mb-3">
                    <NeuChip active={tab === 'mesin'} onClick={() => setTab('mesin')}>Mesin</NeuChip>
                    <NeuChip active={tab === 'utility'} onClick={() => setTab('utility')}>Utility</NeuChip>
                </div>

                <div className="flex gap-2.5 overflow-x-auto px-1 py-1.5 mb-3" role="group" aria-label="Filter tahun">
                    {(reportYearsList ?? []).map((y) => (
                        <NeuChip key={y} active={filters.year === y} onClick={() => handleYearChange(y)} aria-label={`Tahun ${y}`}>{y}</NeuChip>
                    ))}
                </div>

                <div className="flex gap-2.5 overflow-x-auto px-1 py-1.5 mb-3" role="group" aria-label="Filter periode">
                    <NeuChip active={periodFilter === 'all'} onClick={() => setPeriodFilter('all')} aria-label="Semua periode">Semua Periode</NeuChip>
                    {(periods ?? []).map((p) => (
                        <NeuChip
                            key={p.value}
                            active={periodFilter === p.value}
                            onClick={p.locked ? undefined : () => setPeriodFilter(p.value)}
                            disabled={p.locked}
                            dot={p.dot}
                            title={p.locked ? `${p.label} — belum dibuka` : `${p.label} — ${p.done}/${p.total} selesai`}
                            className={[p.locked ? 'neu-chip-locked' : '', p.isCurrent ? 'neu-chip-current' : ''].filter(Boolean).join(' ')}
                        >
                            {p.label}
                        </NeuChip>
                    ))}
                </div>

                {isLoading && <p className="text-xs text-neu-sub text-center mb-2" role="status">Memuat data…</p>}

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-3">
                    <NeuCard className="p-3.5">
                        <div className="flex flex-col sm:flex-row items-center gap-3.5">
                            <NeuRing percentage={periodStats.percent} />
                            <div className="flex-1 w-full grid grid-cols-4 gap-1">
                                {STATUS_ORDER.map((key) => (
                                    <div key={key} className="neu-inset py-1.5 px-0.5 text-center">
                                        <span className={`text-base font-bold block ${STATUS_TILE_COLORS[key]}`}>{periodStats[key] ?? 0}</span>
                                        <span className="text-[8px] text-neu-sub leading-tight block">{STATUS_LABELS[key]}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <p className="text-[10px] text-neu-sub mt-1.5">
                            {selectedPeriod
                                ? `${periodScopeLabel} · ${periodStats.done}/${periodStats.total} selesai`
                                : `Total unit ${tab === 'utility' ? 'Utility' : 'Mesin'}: ${activeReport.totalUnits}`}
                            {' · '}Total entri: {periodStats.total}
                        </p>
                    </NeuCard>

                    <NeuCard className="p-3.5">
                        <h2 className="text-sm font-semibold mb-1.5">Jumlah per Status ({periodScopeLabel})</h2>
                        <BarChart data={byStatusData} color="#38bdf8" />
                    </NeuCard>
                </div>

                <NeuCard className="mb-3 p-3.5">
                    <h2 className="text-sm font-semibold mb-1.5">Progress per Periode {filters.year}</h2>
                    <BarChart
                        data={byPeriodData.map((d) => ({ key: d.key, label: d.label, percent: d.percent, value: d.percent }))}
                        color="#38bdf8"
                        showPercent
                        highlightKey={selectedPeriod ? selectedPeriod.period : null}
                    />
                    <div className="mt-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-1 text-[9px] text-neu-sub">
                        {byPeriodData.map((d) => (
                            <div key={d.key} className="neu-inset p-1.5">
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold">{d.label}</span>
                                    <span className={`${d.locked ? 'text-neu-warn' : 'text-neu-text'} font-semibold`}>{d.percent}%</span>
                                </div>
                                <div className="mt-0.5 text-[8.5px]">{d.done}/{d.total} selesai{d.locked ? ' · Belum masuk' : ''}</div>
                            </div>
                        ))}
                    </div>
                </NeuCard>

                <NeuCard className="mb-3 !shadow-neu-in p-3">
                    <p className="text-[11px] text-neu-sub">Keterangan: Grafik rekap per {tab === 'utility' ? 'Utility' : 'Mesin'} berdasarkan status PM per periode tahun {filters.year}.</p>
                </NeuCard>

                {tab === 'mesin' ? null : (
                    <NeuCard className="mb-3 p-4">
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">Preventive Maintenance Utility</h2>
                                <p className="text-[11px] text-neu-sub">Lihat daftar unit Utility untuk mengisi PM &amp; Riwayat</p>
                            </div>
                            <Link href={route('utility.index')} className="neu-btn">Buka Halaman Utility</Link>
                        </div>
                    </NeuCard>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
