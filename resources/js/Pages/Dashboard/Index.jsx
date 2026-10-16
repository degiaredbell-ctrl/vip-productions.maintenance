import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuRing from '@/Components/NeuRing';
import NeuPill from '@/Components/NeuPill';
import NeuButton from '@/Components/NeuButton';

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
    reportSummary = null,
}) {
    // Scope Beranda mengikuti hak akses aset: role teknisi khusus mesin tidak
    // melihat tab/report Utility, dan sebaliknya. Role general memakai
    // dashboard.view sehingga tetap melihat keduanya (tidak berubah).
    const canViewMachines = Boolean(auth?.can?.['machine.manage'] || auth?.can?.['machine.view'] || auth?.can?.['dashboard.view']);
    const canViewUtilities = Boolean(auth?.can?.['utility.view'] || auth?.can?.['dashboard.view']);

    const [filters, setFilters] = useState({
        period: currentPeriod,
        year: currentYear,
        q: search ?? '',
        status: statusFilter ?? 'all',
        sub: subCategory ?? 'all',
    });
    const [isLoading, setIsLoading] = useState(false);
    const searchTimer = useRef(null);
    const [tab, setTab] = useState(canViewMachines ? 'mesin' : 'utility');
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

    // Jaga agar tab yang aktif selalu berada dalam scope role (mis. tab
    // Utility tersisa dari state lama padahal role tidak boleh membukanya).
    // Bila role tidak punya scope aset sama sekali, biarkan tab apa adanya
    // supaya tidak terjadi perpindahan bolak-balik tanpa henti.
    useEffect(() => {
        if (!canViewMachines && !canViewUtilities) return;
        if (tab === 'mesin' && !canViewMachines) setTab('utility');
        else if (tab === 'utility' && !canViewUtilities) setTab('mesin');
    }, [tab, canViewMachines, canViewUtilities]);

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

    // Kartu laporan (Ringkasan Tindakan & Komponen Paling Sering Diganti)
    // ikut filter periode yang sedang dipilih, tanpa perlu minta server lagi:
    // backend sudah mengirim data untuk tiap periode + agregat `all`.
    const canReportView = Boolean(auth?.can?.['report.view']) && reportSummary != null;
    const activeSummary = canReportView
        ? (reportSummary[periodFilter] ?? reportSummary.all)
        : { actions: { clean: 0, repair: 0, lubricate: 0, replace: 0 }, topParts: [] };
    const exportReportParams = {
        year: filters.year,
        period: periodFilter === 'all' ? undefined : periodFilter,
        format: 'xlsx',
    };

    const activeReport = tab === 'utility' ? reportUtility : reportMachine;
    const reportYearsList = (reportYears?.length ? reportYears : years) ?? [];

    const BarChart = ({ data, color = '#38bdf8', showPercent = false, highlightKey = null }) => {
        // Chart persentase memakai sumbu tetap 0-100% supaya tinggi batang
        // mencerminkan persentase aslinya (bukan diskalakan ke nilai tertinggi).
        // Chart jumlah (mis. per status) tetap diskalakan ke nilai tertinggi.
        const max = showPercent ? 100 : Math.max(1, ...data.map((d) => d.value || 0));
        return (
            <div className="flex items-end gap-2 sm:gap-3 h-[110px] w-full">
                {data.map((d) => {
                    const val = showPercent ? d.percent || 0 : d.value || 0;
                    const h = `${Math.max(6, (val / max) * 100)}%`;
                    const isHighlighted = highlightKey === d.key;
                    const isDimmed = highlightKey != null && !isHighlighted;
                    // Tiap batang boleh punya warnanya sendiri (mis. warna status);
                    // kalau tidak diisi, pakai warna default chart. Batang yang
                    // di-highlight tetap memakai warnanya sendiri, dibedakan lewat
                    // ring + batang lain yang diredupkan.
                    const barColor = d.color || color;
                    return (
                        <div key={d.key} className="flex-1 flex flex-col items-center justify-end h-full min-w-[28px]">
                            {val > 0 && (
                                <span className={`text-[11px] sm:text-xs font-semibold leading-none mb-1 tabular-nums ${isHighlighted ? 'text-neu-active' : 'text-neu-text'}`}>
                                    {showPercent ? `${val}%` : val}
                                </span>
                            )}
                            <div
                                className={`w-full rounded-t-md transition-opacity duration-150 ${isHighlighted ? 'ring-2 ring-neu-active/50 ring-offset-1' : ''}`}
                                style={{ height: h, backgroundColor: barColor, maxHeight: '95%', opacity: isDimmed ? 0.5 : 1 }}
                            />
                            <span className={`text-[10px] sm:text-[11px] mt-1.5 text-center leading-none whitespace-nowrap ${isHighlighted ? 'text-neu-active font-semibold' : 'text-neu-sub'}`}>
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
                    {canViewMachines && <NeuChip active={tab === 'mesin'} onClick={() => setTab('mesin')}>Mesin</NeuChip>}
                    {canViewUtilities && <NeuChip active={tab === 'utility'} onClick={() => setTab('utility')}>Utility</NeuChip>}
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
                        color="#033066"
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

                {canViewUtilities && tab === 'utility' ? (
                    <NeuCard className="mb-3 p-4">
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">Preventive Maintenance Utility</h2>
                                <p className="text-[11px] text-neu-sub">Lihat daftar unit Utility untuk mengisi PM &amp; Riwayat</p>
                            </div>
                            <Link href={route('utility.index')} className="neu-btn">Buka Halaman Utility</Link>
                        </div>
                    </NeuCard>
                ) : null}

                {/* Kartu laporan yang dulu berada di halaman /reports
                    (kini dilebur ke Beranda). Sumbernya sama dengan ekspor
                    XLSX/PDF di bawah supaya angka kartu dan berkas cocok. */}
                {canReportView ? (
                    <>
                        <NeuCard className="mb-3">
                            <b className="text-sm mb-3 block">Ringkasan Tindakan</b>
                            <div className="space-y-2">
                                {[
                                    { label: 'Bersihkan', value: activeSummary.actions.clean },
                                    { label: 'Perbaiki', value: activeSummary.actions.repair },
                                    { label: 'Lumasi', value: activeSummary.actions.lubricate },
                                    { label: 'Perlu Pergantian', value: activeSummary.actions.replace },
                                ].map((a) => (
                                    <div key={a.label} className="flex items-center justify-between">
                                        <span className="text-sm">{a.label}</span>
                                        <NeuPill variant="done">{a.value}x</NeuPill>
                                    </div>
                                ))}
                            </div>
                        </NeuCard>

                        <NeuCard className="mb-3">
                            <b className="text-sm mb-3 block">Komponen Paling Sering Diganti</b>
                            {activeSummary.topParts.length > 0 ? (
                                <div className="space-y-2">
                                    {activeSummary.topParts.map((p, i) => (
                                        <div key={i} className="flex items-center justify-between">
                                            <span className="text-sm">{p.item_name}</span>
                                            <NeuPill variant="done">{p.total}x</NeuPill>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-neu-sub">Belum ada data.</p>
                            )}
                        </NeuCard>

                        {canExport ? (
                            <div className="flex gap-3">
                                <NeuButton
                                    native
                                    href={route('dashboard.report.export', exportReportParams)}
                                    variant="primary"
                                    className="flex-1"
                                >
                                    Ekspor Excel
                                </NeuButton>
                                <NeuButton
                                    native
                                    href={route('dashboard.report.export', { ...exportReportParams, format: 'pdf' })}
                                    className="flex-1"
                                >
                                    Cetak PDF
                                </NeuButton>
                            </div>
                        ) : null}
                    </>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}
