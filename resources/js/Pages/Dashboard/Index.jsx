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

    const BarSVG = ({ data, height = 140, valueColor = '#334155', labelColor = '#64748b', showValueAbove = true }) => {
        const max = Math.max(1, ...data.map((d) => d.value || 0));
        const barW = Math.max(12, Math.floor(320 / Math.max(1, data.length)) - 8);
        return (
            <svg width="100%" height={height} viewBox="0 0 360 140" preserveAspectRatio="none">
                {data.map((d, i) => {
                    const h = ((d.value || 0) / max) * 100;
                    const x = 24 + i * (barW + 8);
                    const y = 110 - h;
                    return (
                        <g key={d.key}>
                            <rect x={x} y={y} width={barW} height={h} rx="3" fill={d.color} />
                            {showValueAbove && (d.value || 0) > 0 && (
                                <text x={x + barW / 2} y={y - 2} textAnchor="middle" fontSize="8" fontWeight="600" fill={valueColor}>{d.value}</text>
                            )}
                            <text x={x + barW / 2} y={125} textAnchor="middle" fontSize="7.5" fontWeight="500" fill={labelColor}>{d.label}</text>
                        </g>
                    );
                })}
            </svg>
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

    const byStatusData = STATUS_ORDER.map((k) => ({
        key: k,
        label: STATUS_LABELS[k],
        value: activeReport.byStatus?.[k] ?? 0,
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

                {isLoading && <p className="text-xs text-neu-sub text-center mb-2" role="status">Memuat data…</p>}

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-3">
                    <NeuCard className="p-3.5">
                        <div className="flex flex-col sm:flex-row items-center gap-3.5">
                            <NeuRing percentage={activeReport.byStatus?.percent ?? 0} />
                            <div className="flex-1 w-full grid grid-cols-4 gap-1">
                                {STATUS_ORDER.map((key) => (
                                    <div key={key} className="neu-inset py-1.5 px-0.5 text-center">
                                        <span className={`text-base font-bold block ${STATUS_TILE_COLORS[key]}`}>{activeReport.byStatus?.[key] ?? 0}</span>
                                        <span className="text-[8px] text-neu-sub leading-tight block">{STATUS_LABELS[key]}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <p className="text-[10px] text-neu-sub mt-1.5">Total unit {tab === 'utility' ? 'Utility' : 'Mesin'}: {activeReport.totalUnits} · Total entri (unit×periode): {activeReport.byStatus?.total ?? 0}</p>
                    </NeuCard>

                    <NeuCard className="p-3.5">
                        <h2 className="text-sm font-semibold mb-1.5">Jumlah per Status (Semua Periode {filters.year})</h2>
                        <BarSVG data={byStatusData} height={130} />
                    </NeuCard>
                </div>

                <NeuCard className="mb-3 p-3.5">
                    <h2 className="text-sm font-semibold mb-1.5">Progress per Periode {filters.year}</h2>
                    <BarSVG data={byPeriodData.map((d) => ({ key: d.key, label: d.label, value: d.percent, color: '#38bdf8' }))} height={130} />
                    <div className="mt-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-1 text-[8px] text-neu-sub">
                        {byPeriodData.map((d) => (
                            <div key={d.key} className="neu-inset p-1">
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold text-[8px]">{d.label}</span>
                                    <span className={`${d.locked ? 'text-neu-warn' : 'text-neu-text'} font-semibold`}>{d.percent}%</span>
                                </div>
                                <div className="mt-0.5 text-[7.5px]">{d.done}/{d.total} selesai{d.locked ? ' · Belum masuk' : ''}</div>
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
