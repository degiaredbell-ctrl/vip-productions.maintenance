import { useEffect, useMemo, useRef, useState } from 'react';

const SERIES = [
    { key: 'in', label: 'Barang Masuk', color: '#1F6FEB' },
    { key: 'out', label: 'Barang Keluar', color: '#A86B0B' },
];

const HEIGHT = 240;
const PAD = { top: 16, right: 14, bottom: 28, left: 40 };

const fmt = (n) => Number(n ?? 0).toLocaleString('id-ID');

const TICKS = 4;

/** Jarak garis bantu sumbu Y yang "bulat" (1, 2, 5, 10, 20, 50, …) dan selalu bilangan bulat. */
function niceStep(maxValue) {
    const raw = maxValue / TICKS;
    if (raw <= 1) return 1;
    const pow = 10 ** Math.floor(Math.log10(raw));

    return [1, 2, 5, 10].find((m) => m * pow >= raw) * pow;
}

/**
 * Grafik garis Barang Masuk vs Barang Keluar.
 *
 * Digambar dengan SVG biasa (tanpa library chart) dan lebarnya diukur dari
 * kontainer, bukan diskalakan lewat viewBox, supaya teks sumbu tetap terbaca
 * di layar ponsel. Arahkan kursor / sentuh grafik untuk melihat angka per titik.
 */
export default function StockLineChart({ data = [] }) {
    const wrapRef = useRef(null);
    const [width, setWidth] = useState(640);
    const [active, setActive] = useState(null);

    useEffect(() => {
        const el = wrapRef.current;
        if (!el) return undefined;

        const update = () => setWidth(Math.max(el.clientWidth, 280));
        update();

        const observer = new ResizeObserver(update);
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    useEffect(() => setActive(null), [data]);

    const innerW = width - PAD.left - PAD.right;
    const innerH = HEIGHT - PAD.top - PAD.bottom;
    const step = useMemo(() => niceStep(Math.max(0, ...data.flatMap((d) => [d.in, d.out]))), [data]);
    const yMax = step * TICKS;

    const x = (i) => PAD.left + (data.length <= 1 ? innerW / 2 : (i / (data.length - 1)) * innerW);
    const y = (v) => PAD.top + innerH - (v / yMax) * innerH;

    const ticks = Array.from({ length: TICKS + 1 }, (_, i) => i * step);
    // Label sumbu X ditipiskan supaya tidak bertumpuk (≈ 1 label per 56px).
    const labelEvery = Math.max(1, Math.ceil(data.length / Math.max(1, Math.floor(innerW / 56))));

    const pathOf = (key) => data.map((d, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(d[key]).toFixed(1)}`).join(' ');
    const areaOf = (key) => `${pathOf(key)} L${x(data.length - 1).toFixed(1)},${y(0)} L${x(0).toFixed(1)},${y(0)} Z`;

    const pick = (clientX) => {
        const rect = wrapRef.current.getBoundingClientRect();
        const rel = clientX - rect.left - PAD.left;
        const i = data.length <= 1 ? 0 : Math.round((rel / innerW) * (data.length - 1));
        setActive(Math.min(Math.max(i, 0), data.length - 1));
    };

    const point = active !== null ? data[active] : null;
    const tooltipLeft = point ? Math.min(Math.max(x(active) - 80, 0), width - 160) : 0;

    return (
        <div>
            <div className="flex flex-wrap items-center gap-4 text-[11px] text-neu-sub mb-2">
                {SERIES.map((s) => (
                    <span key={s.key} className="flex items-center gap-1.5">
                        <span className="w-4 h-[3px] rounded-full" style={{ backgroundColor: s.color }} />
                        {s.label}
                    </span>
                ))}
            </div>

            <div
                ref={wrapRef}
                className="relative w-full select-none touch-pan-y"
                onMouseMove={(e) => pick(e.clientX)}
                onMouseLeave={() => setActive(null)}
                onTouchStart={(e) => pick(e.touches[0].clientX)}
                onTouchMove={(e) => pick(e.touches[0].clientX)}
            >
                <svg
                    width={width}
                    height={HEIGHT}
                    className="block"
                    role="img"
                    aria-label={`Grafik garis barang masuk dan keluar, ${data.length} titik`}
                >
                    <defs>
                        {SERIES.map((s) => (
                            <linearGradient key={s.key} id={`stock-area-${s.key}`} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor={s.color} stopOpacity="0.18" />
                                <stop offset="100%" stopColor={s.color} stopOpacity="0" />
                            </linearGradient>
                        ))}
                    </defs>

                    {ticks.map((t) => (
                        <g key={t}>
                            <line x1={PAD.left} x2={width - PAD.right} y1={y(t)} y2={y(t)} stroke="#C3CAD6" strokeOpacity="0.6" strokeDasharray={t === 0 ? undefined : '3 4'} />
                            <text x={PAD.left - 8} y={y(t)} dy="0.32em" textAnchor="end" fontSize="10" fill="#5A6475">{fmt(t)}</text>
                        </g>
                    ))}

                    {data.map((d, i) => (i % labelEvery === 0 || i === data.length - 1) && (
                        <text key={d.key} x={x(i)} y={HEIGHT - 8} textAnchor="middle" fontSize="10" fill="#5A6475">{d.label}</text>
                    ))}

                    {data.length > 0 && SERIES.map((s) => (
                        <g key={s.key}>
                            <path d={areaOf(s.key)} fill={`url(#stock-area-${s.key})`} />
                            <path d={pathOf(s.key)} fill="none" stroke={s.color} strokeWidth="2.25" strokeLinejoin="round" strokeLinecap="round" />
                        </g>
                    ))}

                    {/* Titik hanya digambar kalau tidak terlalu rapat. */}
                    {data.length <= 31 && SERIES.map((s) => data.map((d, i) => (
                        <circle key={`${s.key}-${d.key}`} cx={x(i)} cy={y(d[s.key])} r={active === i ? 4.5 : 2.5} fill="#FFFFFF" stroke={s.color} strokeWidth="2" />
                    )))}

                    {point && (
                        <line x1={x(active)} x2={x(active)} y1={PAD.top} y2={PAD.top + innerH} stroke="#5A6475" strokeOpacity="0.4" strokeDasharray="3 3" />
                    )}
                </svg>

                {point && (
                    <div
                        className="absolute top-1 pointer-events-none neu-card !p-2.5 !rounded-xl w-40 text-[11px]"
                        style={{ left: tooltipLeft }}
                    >
                        <b className="block mb-1 text-neu-text">{point.title}</b>
                        {SERIES.map((s) => (
                            <span key={s.key} className="flex items-center justify-between gap-2">
                                <span className="flex items-center gap-1.5 text-neu-sub">
                                    <span className="w-2 h-2 rounded-full" style={{ backgroundColor: s.color }} />
                                    {s.label}
                                </span>
                                <b className="tabular-nums" style={{ color: s.color }}>{fmt(point[s.key])}</b>
                            </span>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
