import { useMemo, useState } from 'react';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import StockLineChart from '@/Components/StockLineChart';

const STATUS = {
    out: { label: 'Habis', variant: 'issue', bar: 'bg-neu-bad' },
    low: { label: 'Menipis', variant: 'todo', bar: 'bg-neu-warn' },
    ok: { label: 'Aman', variant: 'done', bar: 'bg-neu-accent' },
};

const fmt = (n) => Number(n ?? 0).toLocaleString('id-ID');

const coverLabel = (months) => {
    if (months === null || months === undefined) return 'Belum ada pemakaian';
    if (months < 1) return `± ${Math.max(Math.round(months * 30), 0)} hari lagi`;
    return `± ${String(months).replace('.', ',')} bulan lagi`;
};

function SectionTitle({ title, hint, right }) {
    return (
        <div className="flex items-start justify-between gap-3 mb-3">
            <div className="min-w-0">
                <h2 className="text-base font-bold">{title}</h2>
                {hint && <p className="text-xs text-neu-sub mt-0.5">{hint}</p>}
            </div>
            {right}
        </div>
    );
}

function Empty({ children }) {
    return <p className="text-sm text-neu-sub text-center py-6">{children}</p>;
}

function KpiCard({ label, value, sub, tone = 'text', alert = false, onClick, active = false }) {
    const toneClass = {
        text: 'text-neu-text',
        bad: 'text-neu-bad',
        warn: 'text-neu-warn',
        good: 'text-neu-accent',
        info: 'text-neu-info',
    }[tone];

    const body = (
        <>
            <div className="flex items-center justify-between gap-2">
                <span className="text-xs font-semibold text-neu-sub">{label}</span>
                {alert && (
                    <span
                        className="neu-alert-icon [--alert-color:#B93A2E] h-5 w-5 flex-none grid place-items-center rounded-full bg-neu-bad text-white text-[11px] font-bold"
                        aria-hidden="true"
                    >
                        <span className="neu-alert-mark">!</span>
                    </span>
                )}
            </div>
            <b className={`block text-2xl sm:text-3xl mt-1 ${toneClass}`}>{value}</b>
            {sub && <span className="block text-[11px] text-neu-sub mt-0.5">{sub}</span>}
        </>
    );

    if (onClick) {
        return (
            <button
                type="button"
                onClick={onClick}
                aria-pressed={active}
                className={`neu-card !p-4 text-left transition-shadow hover:shadow-neu-up focus:outline-none focus-visible:ring-2 focus-visible:ring-neu-accent/40 ${active ? '!shadow-neu-in' : ''}`}
            >
                {body}
            </button>
        );
    }

    return <NeuCard className="!p-4">{body}</NeuCard>;
}

function HBar({ value, max, className = 'bg-neu-accent' }) {
    return (
        <div className="h-2.5 rounded-full shadow-neu-in overflow-hidden">
            <div className={`h-full rounded-full ${className}`} style={{ width: `${Math.max((value / Math.max(max, 1)) * 100, value > 0 ? 3 : 0)}%` }} />
        </div>
    );
}

const GRANULARITY = {
    daily: { label: 'Harian', window: '30 hari terakhir' },
    weekly: { label: 'Mingguan', window: '12 minggu terakhir' },
    monthly: { label: 'Bulanan', window: '12 bulan terakhir' },
};

/**
 * Isi Dashboard Suku Cadang: KPI stok, restock, grafik garis barang
 * masuk/keluar, dan pemakaian per suku cadang/unit.
 *
 * Dipakai halaman Dashboard Suku Cadang dan bagian Suku Cadang di Beranda.
 * Datanya dari SparePartDashboardService; perubahan rentang diserahkan ke
 * halaman induk lewat `onRangeChange` karena tiap halaman punya URL sendiri.
 */
export default function SparePartDashboardPanel({ data = {}, onRangeChange }) {
    const {
        range, ranges = [], slowMovingDays, kpi = {}, restock = [], lineTrend = {}, topParts = [],
        topUnits = [], categories = [], slowMoving = [], recent = [], hasMovements = false,
    } = data;

    const [restockFilter, setRestockFilter] = useState('all');
    const [showAllRestock, setShowAllRestock] = useState(false);
    const [granularity, setGranularity] = useState('daily');

    const toggleRestock = (status) => {
        setRestockFilter((current) => (current === status ? 'all' : status));
        setShowAllRestock(false);
        document.getElementById('restock')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const restockRows = restock.filter((row) => restockFilter === 'all' || row.status === restockFilter);
    const visibleRestock = showAllRestock ? restockRows : restockRows.slice(0, 10);
    const healthyPercent = kpi.total ? Math.round((kpi.ok / kpi.total) * 100) : 0;
    const topPartMax = Math.max(...topParts.map((p) => p.qty), 1);
    const topUnitMax = Math.max(...topUnits.map((u) => u.qty), 1);

    const lineData = useMemo(() => lineTrend[granularity] ?? [], [lineTrend, granularity]);
    const lineTotals = useMemo(() => ({
        in: lineData.reduce((sum, d) => sum + d.in, 0),
        out: lineData.reduce((sum, d) => sum + d.out, 0),
    }), [lineData]);

    return (
        <>
            {/* KPI stok */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                <KpiCard label="Total Item" value={fmt(kpi.total)} sub={`${fmt(kpi.stock_units)} unit di gudang`} />
                <KpiCard
                    label="Stok Habis"
                    value={fmt(kpi.out)}
                    sub="Klik untuk lihat daftar"
                    tone={kpi.out > 0 ? 'bad' : 'good'}
                    alert={kpi.out > 0}
                    onClick={() => toggleRestock('out')}
                    active={restockFilter === 'out'}
                />
                <KpiCard
                    label="Stok Menipis"
                    value={fmt(kpi.low)}
                    sub="Di bawah / sama dengan minimum"
                    tone={kpi.low > 0 ? 'warn' : 'good'}
                    onClick={() => toggleRestock('low')}
                    active={restockFilter === 'low'}
                />
                <KpiCard label="Stok Aman" value={`${healthyPercent}%`} sub={`${fmt(kpi.ok)} dari ${fmt(kpi.total)} item`} tone="good" />
            </div>

            {/* KPI pergerakan */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <KpiCard label="Pemakaian Bulan Ini" value={fmt(kpi.out_qty_month)} sub="unit keluar" tone="warn" />
                <KpiCard label="Barang Masuk Bulan Ini" value={fmt(kpi.in_qty_month)} sub="unit masuk" tone="info" />
                <KpiCard label={`Unit Dilayani (${range} bln)`} value={fmt(kpi.units_served)} sub="mesin/utility memakai suku cadang" />
                <KpiCard label="Tidak Bergerak" value={fmt(kpi.slow_moving)} sub={`ada stok, tanpa pemakaian > ${slowMovingDays} hari`} />
            </div>

            <div className="flex flex-wrap items-center gap-2.5 mb-4" role="group" aria-label="Rentang waktu">
                <span className="text-xs text-neu-sub font-semibold">Rentang:</span>
                {ranges.map((r) => (
                    <NeuChip key={r} size="sm" active={range === r} onClick={() => onRangeChange?.(r)}>
                        {r} bulan
                    </NeuChip>
                ))}
            </div>

            {/* Restock */}
            <NeuCard className="mb-4 scroll-mt-4" id="restock">
                <SectionTitle
                    title="Perlu Restock"
                    hint="Diurutkan dari yang paling mendesak: stok habis, lalu yang paling jauh di bawah minimum."
                    right={restockFilter !== 'all' && (
                        <button type="button" className="text-xs font-semibold text-neu-accent whitespace-nowrap" onClick={() => setRestockFilter('all')}>
                            Tampilkan semua
                        </button>
                    )}
                />
                {restockRows.length === 0 ? (
                    <Empty>Semua stok di atas batas minimum.</Empty>
                ) : (
                    <>
                        <div className="neu-table-wrap">
                            <table className="neu-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Suku Cadang</th>
                                        <th scope="col">Stok / Min</th>
                                        <th scope="col">Pesan Minimal</th>
                                        <th scope="col">Perkiraan Habis</th>
                                        <th scope="col">Lokasi / Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visibleRestock.map((row) => (
                                        <tr key={row.id}>
                                            <td className="neu-cell-lead">
                                                <b className="block text-sm break-words">{row.name}</b>
                                                <span className="block text-xs text-neu-sub">
                                                    {row.code}{row.category && ` · ${row.category}`}
                                                </span>
                                            </td>
                                            <td data-label="Stok / Min">
                                                <div className="flex items-center gap-2">
                                                    <NeuPill variant={STATUS[row.status].variant}>{STATUS[row.status].label}</NeuPill>
                                                    <span className="text-sm font-semibold">
                                                        {fmt(row.stock)} / {fmt(row.min_stock)} {row.unit}
                                                    </span>
                                                </div>
                                                <div className="mt-1.5 max-w-[180px]">
                                                    <HBar value={Math.max(row.stock, 0)} max={row.min_stock} className={STATUS[row.status].bar} />
                                                </div>
                                            </td>
                                            <td data-label="Pesan Minimal">
                                                <b className="text-sm">{fmt(row.shortage)} {row.unit}</b>
                                                {row.used_in_range > 0 && (
                                                    <span className="block text-[11px] text-neu-sub">
                                                        terpakai {fmt(row.used_in_range)} dalam {range} bln
                                                    </span>
                                                )}
                                            </td>
                                            <td data-label="Perkiraan Habis">
                                                <span className={`text-xs ${row.status === 'out' ? 'text-neu-bad font-semibold' : 'text-neu-sub'}`}>
                                                    {row.status === 'out' ? 'Sudah habis' : coverLabel(row.cover_months)}
                                                </span>
                                            </td>
                                            <td data-label="Lokasi / Supplier">
                                                <span className="block text-xs break-words">{row.location || '—'}</span>
                                                <span className="block text-xs text-neu-sub break-words">{row.supplier || 'Supplier belum diisi'}</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {restockRows.length > 10 && (
                            <div className="text-center mt-3">
                                <NeuButton onClick={() => setShowAllRestock((v) => !v)} className="!min-h-[36px] !py-1.5 !px-3 !text-xs">
                                    {showAllRestock ? 'Tampilkan 10 teratas' : `Tampilkan semua (${restockRows.length})`}
                                </NeuButton>
                            </div>
                        )}
                    </>
                )}
            </NeuCard>

            {/* Grafik garis barang masuk & keluar */}
            <NeuCard className="mb-4">
                <SectionTitle
                    title="Grafik Barang Masuk & Keluar"
                    hint={`${GRANULARITY[granularity].window} · masuk ${fmt(lineTotals.in)} · keluar ${fmt(lineTotals.out)} unit`}
                    right={(
                        <div className="flex flex-wrap gap-2 justify-end" role="group" aria-label="Skala grafik">
                            {Object.entries(GRANULARITY).map(([key, g]) => (
                                <NeuChip key={key} size="sm" active={granularity === key} onClick={() => setGranularity(key)}>
                                    {g.label}
                                </NeuChip>
                            ))}
                        </div>
                    )}
                />
                {hasMovements ? <StockLineChart data={lineData} /> : <Empty>Belum ada transaksi stok yang tercatat.</Empty>}
            </NeuCard>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                {/* Kesehatan stok per kategori */}
                <NeuCard>
                    <SectionTitle title="Kondisi Stok per Kategori" hint="Proporsi item habis, menipis, dan aman." />
                    {categories.length === 0 ? (
                        <Empty>Belum ada data suku cadang.</Empty>
                    ) : (
                        <ul className="space-y-3">
                            {categories.slice(0, 8).map((cat) => (
                                <li key={cat.name}>
                                    <div className="flex items-center justify-between gap-2 text-xs mb-1">
                                        <b className="truncate">{cat.name}</b>
                                        <span className="text-neu-sub whitespace-nowrap">
                                            {cat.out > 0 && <span className="text-neu-bad font-semibold">{cat.out} habis · </span>}
                                            {cat.low > 0 && <span className="text-neu-warn font-semibold">{cat.low} menipis · </span>}
                                            {cat.total} item
                                        </span>
                                    </div>
                                    <div className="h-3 rounded-full shadow-neu-in overflow-hidden flex" role="img" aria-label={`${cat.name}: ${cat.out} habis, ${cat.low} menipis, ${cat.ok} aman`}>
                                        {['out', 'low', 'ok'].map((k) => cat[k] > 0 && (
                                            <div key={k} className={STATUS[k].bar} style={{ width: `${(cat[k] / cat.total) * 100}%` }} />
                                        ))}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="flex flex-wrap gap-3 text-[11px] text-neu-sub mt-4">
                        {Object.entries(STATUS).map(([k, s]) => (
                            <span key={k} className="flex items-center gap-1.5"><span className={`w-3 h-3 rounded ${s.bar}`} /> {s.label}</span>
                        ))}
                    </div>
                </NeuCard>
                {/* Tidak bergerak */}
                <NeuCard>
                    <SectionTitle
                        title="Stok Tidak Bergerak"
                        hint={`Ada stok tetapi tidak dipakai lebih dari ${slowMovingDays} hari. Pertimbangkan untuk tidak menambah stok.`}
                    />
                    {slowMoving.length === 0 ? (
                        <Empty>Semua stok bergerak aktif.</Empty>
                    ) : (
                        <ul className="divide-y divide-neu-dark/30">
                            {slowMoving.map((part) => (
                                <li key={part.id} className="flex items-center justify-between gap-3 py-2">
                                    <span className="min-w-0">
                                        <b className="block text-xs break-words">{part.name}</b>
                                        <span className="block text-[11px] text-neu-sub">{part.code}{part.location && ` · ${part.location}`}</span>
                                    </span>
                                    <b className="text-xs whitespace-nowrap">{fmt(part.stock)} {part.unit}</b>
                                </li>
                            ))}
                        </ul>
                    )}
                    {kpi.slow_moving > slowMoving.length && (
                        <p className="text-[11px] text-neu-sub mt-2">Menampilkan {slowMoving.length} dari {kpi.slow_moving} item (stok terbanyak).</p>
                    )}
                </NeuCard>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                {/* Paling banyak dipakai */}
                <NeuCard>
                    <SectionTitle title="Paling Banyak Dipakai" hint={`10 suku cadang teratas · ${range} bulan terakhir`} />
                    {topParts.length === 0 ? (
                        <Empty>Belum ada pemakaian pada rentang ini.</Empty>
                    ) : (
                        <ol className="space-y-3">
                            {topParts.map((part, i) => (
                                <li key={part.id}>
                                    <div className="flex items-start justify-between gap-2 text-xs mb-1">
                                        <span className="min-w-0">
                                            <b className="text-neu-sub mr-1">{i + 1}.</b>
                                            <b className="break-words">{part.name}</b>
                                            <span className="text-neu-sub"> · {part.code}</span>
                                        </span>
                                        <b className="whitespace-nowrap">{fmt(part.qty)} {part.unit}</b>
                                    </div>
                                    <HBar value={part.qty} max={topPartMax} className="bg-neu-warn" />
                                    <span className="block text-[11px] text-neu-sub mt-1">
                                        {part.count}× transaksi · sisa stok {fmt(part.stock)} · {coverLabel(part.cover_months)}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    )}
                </NeuCard>

                {/* Unit paling banyak memakai */}
                <NeuCard>
                    <SectionTitle title="Unit Paling Banyak Memakai" hint={`10 Mesin/Utility teratas · ${range} bulan terakhir`} />
                    {topUnits.length === 0 ? (
                        <Empty>Belum ada pemakaian ke unit pada rentang ini.</Empty>
                    ) : (
                        <ol className="space-y-3">
                            {topUnits.map((unit, i) => (
                                <li key={unit.id}>
                                    <div className="flex items-start justify-between gap-2 text-xs mb-1">
                                        <span className="min-w-0">
                                            <b className="text-neu-sub mr-1">{i + 1}.</b>
                                            <b className="break-words">{unit.name}</b>
                                            <span className={unit.category === 'utility' ? 'text-neu-warn' : 'text-neu-info'}> · {unit.category_label}</span>
                                        </span>
                                        <b className="whitespace-nowrap">{fmt(unit.qty)} unit</b>
                                    </div>
                                    <HBar value={unit.qty} max={topUnitMax} className={unit.category === 'utility' ? 'bg-neu-warn' : 'bg-neu-info'} />
                                    <span className="block text-[11px] text-neu-sub mt-1">{unit.code} · {unit.parts} jenis suku cadang</span>
                                </li>
                            ))}
                        </ol>
                    )}
                </NeuCard>
            </div>

            {/* Transaksi terakhir */}
            <NeuCard className="mb-4">
                <SectionTitle title="Transaksi Terakhir" hint="10 pergerakan stok terbaru." />
                {recent.length === 0 ? (
                    <Empty>Belum ada transaksi.</Empty>
                ) : (
                    <ul className="space-y-2.5">
                        {recent.map((m) => (
                            <li key={m.id} className="flex items-start gap-3">
                                <span
                                    className={`neu-inset w-9 h-9 flex-none grid place-items-center text-sm font-bold ${m.type === 'in' ? 'text-neu-info' : 'text-neu-warn'}`}
                                    aria-label={m.type_label}
                                >
                                    {m.type === 'in' ? '↓' : '↑'}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <b className="text-xs break-words">{m.part}</b>
                                        <b className={`text-xs whitespace-nowrap ${m.type === 'in' ? 'text-neu-info' : 'text-neu-warn'}`}>
                                            {m.type === 'in' ? '+' : '−'}{fmt(m.qty)} {m.unit}
                                        </b>
                                    </div>
                                    <span className="block text-[11px] text-neu-sub break-words">
                                        {m.date}
                                        {m.machine && ` · ${m.machine}`}
                                        {m.period && ` · ${m.period}`}
                                        {m.user && ` · ${m.user}`}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </NeuCard>

            {kpi.no_min > 0 && (
                <p className="text-xs text-neu-sub text-center">
                    {kpi.no_min} item belum punya batas minimum stok, sehingga tidak bisa terdeteksi "menipis". Atur di Master Suku Cadang.
                </p>
            )}
        </>
    );
}
