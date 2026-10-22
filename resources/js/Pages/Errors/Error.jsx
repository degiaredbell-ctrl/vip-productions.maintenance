import { useCallback, useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';

const CONTENT = {
    403: {
        title: 'Akses Ditolak',
        description: 'Role Anda tidak memiliki izin untuk membuka halaman atau melakukan tindakan ini. Hubungi Admin bila Anda memerlukan akses.',
        tone: '#B93A2E',
    },
    404: {
        title: 'Halaman Tidak Ditemukan',
        description: 'Halaman yang Anda cari tidak ada, sudah dipindahkan, atau datanya telah dihapus. Periksa kembali alamat yang Anda buka.',
        tone: '#1F6FEB',
    },
    405: {
        title: 'Metode Tidak Diizinkan',
        description: 'Permintaan ini tidak didukung oleh halaman tujuan. Silakan kembali dan ulangi dari menu aplikasi.',
        tone: '#A86B0B',
    },
    419: {
        title: 'Sesi Kedaluwarsa',
        description: 'Halaman terlalu lama dibiarkan terbuka sehingga sesi keamanannya berakhir. Muat ulang halaman lalu ulangi tindakan Anda.',
        tone: '#A86B0B',
    },
    429: {
        title: 'Terlalu Banyak Permintaan',
        description: 'Anda melakukan tindakan ini terlalu sering dalam waktu singkat. Tunggu sekitar satu menit, lalu coba lagi.',
        tone: '#A86B0B',
    },
    500: {
        title: 'Terjadi Kesalahan Server',
        description: 'Ada masalah di sisi server saat memproses permintaan Anda. Tim IT sudah dapat melihatnya di log. Silakan coba beberapa saat lagi.',
        tone: '#B93A2E',
    },
    503: {
        title: 'Sedang Pemeliharaan',
        description: 'Aplikasi sedang dalam pemeliharaan atau pembaruan singkat. Silakan kembali beberapa menit lagi.',
        tone: '#0F6E56',
    },
};

/* ------------------------------------------------------------------ */
/* Ilustrasi beranimasi per status                                    */
/* ------------------------------------------------------------------ */

function Illustration404() {
    return (
        <svg viewBox="0 0 200 160" className="w-full h-full" aria-hidden="true">
            <ellipse className="err-shadow" cx="100" cy="146" rx="46" ry="6" fill="#C3CAD6" />
            <g className="err-float">
                <rect x="62" y="28" width="76" height="98" rx="12" fill="#FFFFFF" stroke="#C3CAD6" strokeWidth="3" />
                <rect x="74" y="44" width="40" height="6" rx="3" fill="#E8ECF1" />
                <rect x="74" y="58" width="52" height="6" rx="3" fill="#E8ECF1" />
                <rect x="74" y="72" width="30" height="6" rx="3" fill="#E8ECF1" />
                <text x="100" y="112" textAnchor="middle" className="err-qmark" fontSize="30" fontWeight="800" fill="#1F6FEB">?</text>
            </g>
            <g className="err-orbit">
                <g transform="translate(140 52)">
                    <g className="err-orbit-counter">
                        <circle r="18" fill="rgba(31,111,235,0.12)" stroke="#1F6FEB" strokeWidth="5" />
                        <line x1="13" y1="13" x2="28" y2="28" stroke="#1F6FEB" strokeWidth="7" strokeLinecap="round" />
                        <path d="M-8 -6 a10 10 0 0 1 10 -6" stroke="#FFFFFF" strokeWidth="3" fill="none" strokeLinecap="round" />
                    </g>
                </g>
            </g>
        </svg>
    );
}

function Illustration403() {
    return (
        <svg viewBox="0 0 200 160" className="w-full h-full" aria-hidden="true">
            <ellipse className="err-shadow" cx="100" cy="146" rx="40" ry="6" fill="#C3CAD6" />
            <circle className="err-ring" cx="100" cy="86" r="52" fill="none" stroke="#B93A2E" strokeWidth="3" />
            <circle className="err-ring err-ring-2" cx="100" cy="86" r="52" fill="none" stroke="#B93A2E" strokeWidth="3" />
            <g className="err-shake">
                <path className="err-shackle" d="M76 74 V56 a24 24 0 0 1 48 0 V74" fill="none" stroke="#5A6475" strokeWidth="10" strokeLinecap="round" />
                <rect x="64" y="70" width="72" height="62" rx="14" fill="#B93A2E" />
                <rect x="64" y="70" width="72" height="14" rx="7" fill="rgba(255,255,255,0.18)" />
                <g className="err-keyhole">
                    <circle cx="100" cy="98" r="8" fill="#FFFFFF" />
                    <rect x="96" y="102" width="8" height="16" rx="3" fill="#FFFFFF" />
                </g>
            </g>
        </svg>
    );
}

function Gear({ cx, cy, r, teeth = 8, fill, className }) {
    const tooth = [];
    for (let i = 0; i < teeth; i += 1) {
        tooth.push(
            <rect key={i} x={cx - r * 0.18} y={cy - r - r * 0.32} width={r * 0.36} height={r * 0.5} rx={r * 0.08} fill={fill} transform={`rotate(${(360 / teeth) * i} ${cx} ${cy})`} />,
        );
    }

    return (
        <g className={className} style={{ transformOrigin: `${cx}px ${cy}px` }}>
            {tooth}
            <circle cx={cx} cy={cy} r={r} fill={fill} />
            <circle cx={cx} cy={cy} r={r * 0.38} fill="#E8ECF1" />
        </g>
    );
}

function Illustration500() {
    return (
        <svg viewBox="0 0 200 160" className="w-full h-full" aria-hidden="true">
            <ellipse className="err-shadow" cx="100" cy="146" rx="52" ry="6" fill="#C3CAD6" />
            <Gear cx={82} cy={84} r={30} teeth={10} fill="#5A6475" className="err-spin" />
            <Gear cx={136} cy={58} r={18} teeth={8} fill="#B93A2E" className="err-spin-rev" />
            <g className="err-spark"><path d="M150 96 l6 -12 l2 8 l8 -4 l-6 12 l-2 -8 z" fill="#A86B0B" /></g>
            <g className="err-spark err-spark-2"><path d="M44 50 l5 -10 l2 7 l7 -3 l-5 10 l-2 -7 z" fill="#B93A2E" /></g>
            <g className="err-smoke"><circle cx="128" cy="110" r="7" fill="#C3CAD6" /></g>
            <g className="err-smoke err-smoke-2"><circle cx="138" cy="112" r="5" fill="#C3CAD6" /></g>
        </svg>
    );
}

function Illustration503() {
    return (
        <svg viewBox="0 0 200 160" className="w-full h-full" aria-hidden="true">
            <ellipse className="err-shadow" cx="100" cy="146" rx="52" ry="6" fill="#C3CAD6" />
            <Gear cx={86} cy={76} r={28} teeth={10} fill="#0F6E56" className="err-spin" />
            <g className="err-swing" style={{ transformOrigin: '138px 112px' }}>
                <path d="M138 112 L138 56" stroke="#5A6475" strokeWidth="9" strokeLinecap="round" />
                <circle cx="138" cy="46" r="14" fill="#5A6475" />
                <rect x="132" y="28" width="12" height="18" rx="2" fill="#E8ECF1" />
            </g>
            <defs>
                <pattern id="err-stripes" width="16" height="12" patternUnits="userSpaceOnUse" patternTransform="skewX(-30)">
                    <rect width="8" height="12" fill="#A86B0B" />
                    <rect x="8" width="8" height="12" fill="#2B3445" />
                </pattern>
            </defs>
            <clipPath id="err-bar"><rect x="44" y="120" width="112" height="12" rx="6" /></clipPath>
            <g clipPath="url(#err-bar)">
                <rect x="20" y="120" width="160" height="12" fill="url(#err-stripes)" className="err-stripes" />
            </g>
        </svg>
    );
}

function Illustration419() {
    return (
        <svg viewBox="0 0 200 160" className="w-full h-full" aria-hidden="true">
            <ellipse className="err-shadow" cx="100" cy="146" rx="40" ry="6" fill="#C3CAD6" />
            <g className="err-float">
                <circle cx="100" cy="78" r="50" fill="#FFFFFF" stroke="#A86B0B" strokeWidth="6" />
                {Array.from({ length: 12 }, (_, i) => (
                    <rect key={i} x="98.5" y="34" width="3" height={i % 3 === 0 ? 10 : 5} rx="1.5" fill="#C3CAD6" transform={`rotate(${i * 30} 100 78)`} />
                ))}
                <rect className="err-hand-fast" x="98" y="40" width="4" height="40" rx="2" fill="#B93A2E" style={{ transformOrigin: '100px 78px' }} />
                <rect className="err-hand-slow" x="97" y="52" width="6" height="28" rx="3" fill="#2B3445" style={{ transformOrigin: '100px 78px' }} />
                <circle cx="100" cy="78" r="5" fill="#2B3445" />
            </g>
        </svg>
    );
}

function Illustration429() {
    return (
        <svg viewBox="0 0 200 160" className="w-full h-full" aria-hidden="true">
            <ellipse className="err-shadow" cx="100" cy="146" rx="36" ry="6" fill="#C3CAD6" />
            <g className="err-flip" style={{ transformOrigin: '100px 80px' }}>
                <rect x="66" y="26" width="68" height="10" rx="5" fill="#5A6475" />
                <rect x="66" y="124" width="68" height="10" rx="5" fill="#5A6475" />
                <path d="M74 36 h52 c0 26 -20 34 -20 44 c0 10 20 18 20 44 h-52 c0 -26 20 -34 20 -44 c0 -10 -20 -18 -20 -44 z" fill="#FFFFFF" stroke="#C3CAD6" strokeWidth="3" />
                <path className="err-sand-top" d="M82 46 h36 c-2 14 -14 22 -18 30 c-4 -8 -16 -16 -18 -30 z" fill="#A86B0B" />
                <path className="err-sand-bottom" d="M80 120 h40 c-2 -12 -12 -18 -20 -22 c-8 4 -18 10 -20 22 z" fill="#A86B0B" />
                <rect className="err-sand-stream" x="99" y="78" width="2" height="34" fill="#A86B0B" />
            </g>
        </svg>
    );
}

function IllustrationGeneric() {
    return (
        <svg viewBox="0 0 200 160" className="w-full h-full" aria-hidden="true">
            <ellipse className="err-shadow" cx="100" cy="146" rx="44" ry="6" fill="#C3CAD6" />
            <g className="err-shake">
                <path d="M100 24 L156 124 H44 Z" fill="#A86B0B" stroke="#A86B0B" strokeWidth="10" strokeLinejoin="round" />
                <rect x="94" y="56" width="12" height="38" rx="6" fill="#FFFFFF" />
                <circle cx="100" cy="108" r="7" fill="#FFFFFF" />
            </g>
        </svg>
    );
}

const ILLUSTRATIONS = {
    403: Illustration403,
    404: Illustration404,
    419: Illustration419,
    429: Illustration429,
    500: Illustration500,
    503: Illustration503,
};

/* ------------------------------------------------------------------ */

/**
 * Halaman error (403, 404, 405, 419, 429, 500, 503) dengan ilustrasi
 * beranimasi. Tombol X / Esc menutup pesan dan kembali ke halaman
 * sebelumnya (atau Beranda bila halaman ini dibuka langsung).
 */
export default function ErrorPage({ status = 500, message = null, homeUrl = '/' }) {
    const content = CONTENT[status] ?? {
        title: 'Terjadi Kesalahan',
        description: 'Permintaan Anda tidak dapat diproses. Silakan coba lagi.',
        tone: '#A86B0B',
    };
    const Art = ILLUSTRATIONS[status] ?? IllustrationGeneric;
    const [closing, setClosing] = useState(false);

    const leave = useCallback((target) => {
        setClosing(true);
        setTimeout(() => {
            if (target === 'back' && window.history.length > 1) {
                window.history.back();
            } else if (target === 'reload') {
                window.location.reload();
            } else {
                router.visit(homeUrl);
            }
        }, 220);
    }, [homeUrl]);

    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && leave('back');
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [leave]);

    const digits = String(status).split('');

    return (
        <div className="err-page min-h-screen bg-neu-bg relative overflow-hidden flex items-center justify-center p-4" style={{ '--err-tone': content.tone }}>
            <Head title={`${status} · ${content.title}`} />

            {/* Latar: gumpalan & partikel yang melayang */}
            <div className="pointer-events-none absolute inset-0" aria-hidden="true">
                <span className="err-blob err-blob-1" />
                <span className="err-blob err-blob-2" />
                <span className="err-blob err-blob-3" />
                {Array.from({ length: 14 }, (_, i) => (
                    <span
                        key={i}
                        className="err-particle"
                        style={{ left: `${(i * 73) % 100}%`, animationDelay: `${(i * 0.9) % 8}s`, animationDuration: `${9 + (i % 5) * 2}s` }}
                    />
                ))}
            </div>

            <div
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="err-title"
                aria-describedby="err-desc"
                className={`relative w-full max-w-lg rounded-[28px] bg-neu-bg shadow-neu-up px-6 pt-8 pb-7 sm:px-10 text-center ${closing ? 'err-pop-out' : 'err-pop-in'}`}
            >
                <button
                    type="button"
                    onClick={() => leave('back')}
                    aria-label="Tutup pesan error"
                    title="Tutup (Esc)"
                    className="err-close absolute top-4 right-4 grid place-items-center h-10 w-10 rounded-full bg-neu-bg shadow-neu-up-sm text-neu-sub hover:text-neu-text hover:shadow-neu-in transition-all"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div className="mx-auto w-48 h-40 sm:w-56 sm:h-44 -mt-2">
                    <Art />
                </div>

                <div className="flex justify-center gap-1 sm:gap-2 mt-1 select-none" aria-hidden="true">
                    {digits.map((d, i) => (
                        <span key={i} className="err-digit text-5xl sm:text-6xl font-extrabold" style={{ animationDelay: `${i * 0.15}s` }}>
                            {d}
                        </span>
                    ))}
                </div>

                <h1 id="err-title" className="mt-3 text-xl sm:text-2xl font-bold text-neu-text">
                    <span className="sr-only">Error {status}: </span>{content.title}
                </h1>
                <p id="err-desc" className="mt-2 text-sm text-neu-sub leading-relaxed">{content.description}</p>

                {message && (
                    <p className="mt-4 rounded-2xl shadow-neu-in px-4 py-3 text-sm font-semibold" style={{ color: content.tone }}>
                        {message}
                    </p>
                )}

                <div className="mt-6 flex flex-col-reverse sm:flex-row gap-3 justify-center">
                    <button type="button" onClick={() => leave('back')} className="neu-btn justify-center sm:min-w-[140px]">
                        ‹ Kembali
                    </button>
                    {status === 419 || status === 500 || status === 503 ? (
                        <button type="button" onClick={() => leave('reload')} className="neu-btn-primary justify-center sm:min-w-[140px]">
                            Muat Ulang
                        </button>
                    ) : (
                        <button type="button" onClick={() => leave('home')} className="neu-btn-primary justify-center sm:min-w-[140px]">
                            Ke Beranda
                        </button>
                    )}
                </div>

                <p className="mt-5 text-[11px] text-neu-sub">Kode error {status} · PM Preventive — PT Verra Inter Pangan</p>
            </div>
        </div>
    );
}
