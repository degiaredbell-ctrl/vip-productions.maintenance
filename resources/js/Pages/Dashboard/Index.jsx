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

    const BarSVG = ({ data, height = 160 }) => {
        const total = data.reduce((s, d) => s + (d.value || 0), 0);
        const max = Math.max(1, ...data.map((d) => d.value || 0));
        const barW = Math.max(14, Math.floor(280 / Math.max(1, data.length)) - 10);
        return (
            <svg width="100%" height={height} viewBox="0 0 320 160" preserveAspectRatio="none">
                {data.map((d, i) => {
                    const h = ((d.value || 0) / max) * 120;
                    const x = 20 + i * (barW + 10);
                    const y = 140 - h;
                    return (
                        <g key={d.key}>
                            <rect x={x} y={y} width={barW} height={h} rx="4" fill={d.color} />
                            <text x={x + barW / 2} y={y - 4} textAnchor="middle" fontSize="9" fill="#475569">{d.value}</text>
                            <text x={x + barW / 2} y={155} textAnchor="middle" fontSize="8" fill="#64748b">{d.label}</text>
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
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Dashboard Report</h1>
                </div>

                <div className="flex gap-2 mb-4">
                    <NeuChip active={tab === 'mesin'} onClick={() => setTab('mesin')}>Mesin</NeuChip>
                    <NeuChip active={tab === 'utility'} onClick={() => setTab('utility')}>Utility</NeuChip>
                </div>

                <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-3" role="group" aria-label="Filter tahun">
                    {(reportYearsList ?? []).map((y) => (
                        <NeuChip key={y} active={filters.year === y} onClick={() => handleYearChange(y)} aria-label={`Tahun ${y}`}>{y}</NeuChip>
                    ))}
                </div>

                {isLoading && <p className="text-xs text-neu-sub text-center mb-3" role="status">Memuat data…</p>}

                <NeuCard className="mb-4">
                    <div className="flex flex-col sm:flex-row items-center gap-4 sm:gap-5">
                        <NeuRing percentage={activeReport.byStatus?.percent ?? 0} />
                        <div className="flex-1 w-full grid grid-cols-4 gap-2">
                            {STATUS_ORDER.map((key) => (
                                <div key={key} className="neu-inset py-3 px-1.5 text-center">
                                    <span className={`text-xl font-bold block ${STATUS_TILE_COLORS[key]}`}>{activeReport.byStatus?.[key] ?? 0}</span>
                                    <span className="text-[10px] text-neu-sub leading-tight block">{STATUS_LABELS[key]}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <p className="text-xs text-neu-sub mt-3">Total unit {tab === 'utility' ? 'Utility' : 'Mesin'}: {activeReport.totalUnits} · Total entri (unit×periode): {activeReport.byStatus?.total ?? 0}</p>
                </NeuCard>

                <NeuCard className="mb-4">
                    <h2 className="text-sm font-semibold mb-2">Progress per Periode {filters.year}</h2>
                    <BarSVG data={byPeriodData.map((d) => ({ key: d.key, label: d.label, value: d.percent, color: '#0ea5e9' }))} height={180} />
                    <div className="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-2 text-[10px] text-neu-sub">
                        {byPeriodData.map((d) => (
                            <div key={d.key} className="neu-inset p-2">
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold">{d.label}</span>
                                    <span className={d.locked ? 'text-neu-warn' : ''}>{d.percent}%</span>
                                </div>
                                <div className="mt-1">{d.done}/{d.total} selesai{d.locked ? ' · Belum memasuki periode' : ''}</div>
                            </div>
                        ))}
                    </div>
                </NeuCard>

                <NeuCard className="mb-4">
                    <h2 className="text-sm font-semibold mb-2">Jumlah per Status (Semua Periode {filters.year})</h2>
                    <BarSVG data={byStatusData} height={160} />
                </NeuCard>

                <NeuCard className="mb-4 !shadow-neu-in">
                    <p className="text-xs text-neu-sub">Keterangan: Grafik di atas merupakan rekap per {tab === 'utility' ? 'Utility' : 'Mesin'} berdasarkan status PM per periode tahun {filters.year}.</p>
                </NeuCard>

                {tab === 'mesin' ? (
                    <>
                        {/* Bagian list mesin di dashboard dihilangkan sesuai permintaan */}
                    </>
                ) : (
                    <>
                        <NeuCard className="mb-4">
                            <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-semibold">Preventive Maintenance Utility</h2>
                                    <p className="text-xs text-neu-sub">Lihat daftar unit Utility untuk mengisi PM &amp; Riwayat</p>
                                </div>
                                <Link href={route('utility.index')} className="neu-btn">Buka Halaman Utility</Link>
                            </div>
                        </NeuCard>
                        <NeuCard className="mb-4">
                            <p className="text-xs text-neu-sub">Rekap Utility per periode &amp; status tahun {filters.year} ditampilkan di atas.</p>
                        </NeuCard>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
