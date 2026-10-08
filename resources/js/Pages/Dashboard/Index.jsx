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
                        <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-2" role="group" aria-label="Periode">
                            {(periods ?? []).map((p) => (
                                <NeuChip
                                    key={p.value}
                                    active={filters.period === p.value}
                                    onClick={p.locked ? undefined : () => router.get(route('dashboard'), { ...filters, period: p.value }, { preserveState: true, preserveScroll: true })}
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
                        <NeuCard className="mb-4">
                            <div className="flex flex-col sm:flex-row items-center gap-4 sm:gap-5">
                                <NeuRing percentage={percentage} />
                                <div className="flex-1 w-full grid grid-cols-4 gap-2">
                                    {['done', 'progress', 'todo', 'issue'].map((key) => (
                                        <div key={key} className="neu-inset py-3 px-1.5 text-center">
                                            <span className={`text-xl font-bold block ${STATUS_TILE_COLORS[key]}`}>{stats[key] ?? 0}</span>
                                            <span className="text-[10px] text-neu-sub leading-tight block">{STATUS_LABELS[key]}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </NeuCard>
                        {canExport && (
                            <div className="flex gap-3 mb-4">
                                <a href={route('dashboard.export', { ...exportParams, format: 'xlsx' })} className="neu-btn flex-1 text-center">Ekspor Excel</a>
                                <a href={route('dashboard.export', { ...exportParams, format: 'pdf' })} className="neu-btn flex-1 text-center">Cetak PDF</a>
                            </div>
                        )}
                        <div className="mb-3">
                            <div className="relative">
                                <input type="search" placeholder="Cari kode, nama, area (C.7), atau lokasi…" value={filters.q} onChange={handleSearch} className="neu-input pr-11" aria-label="Cari mesin" />
                                {filters.q && (
                                    <button type="button" onClick={clearSearch} aria-label="Bersihkan pencarian" className="absolute right-2 top-1/2 -translate-y-1/2 grid place-items-center h-8 w-8 rounded-full text-neu-sub hover:text-neu-bad hover:shadow-neu-in">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                )}
                            </div>
                        </div>
                        <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-3" role="group" aria-label="Filter status">
                            {[{value:'all',label:'Semua'},{value:'todo',label:'Belum'},{value:'progress',label:'Sedang Approval'},{value:'done',label:'Selesai'},{value:'issue',label:'Perlu Revisi'}].map((f) => (
                                <NeuChip key={f.value} active={filters.status === f.value} onClick={() => applyFilters({ status: f.value })}>{f.label}</NeuChip>
                            ))}
                        </div>
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
