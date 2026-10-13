/*
 * Ikon garis (inline SVG) untuk tombol aksi. Memakai gaya yang sama dengan
 * ikon lain di aplikasi: stroke currentColor, tanpa fill, viewBox 24, dan
 * garis membulat. Semua ikon menerima `className` (default w-4 h-4) supaya
 * ukuran/warna mengikuti tombol pembungkusnya.
 */

function base(className) {
    return {
        className: className ?? 'w-4 h-4',
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 2,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': 'true',
    };
}

export function PlusIcon({ className }) {
    return (
        <svg {...base(className)}>
            <path d="M12 5v14M5 12h14" />
        </svg>
    );
}

export function MinusIcon({ className }) {
    return (
        <svg {...base(className)}>
            <path d="M5 12h14" />
        </svg>
    );
}

export function TrashIcon({ className }) {
    return (
        <svg {...base(className)}>
            <path d="M3 6h18" />
            <path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" />
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
            <path d="M10 11v6M14 11v6" />
        </svg>
    );
}

export function EditIcon({ className }) {
    return (
        <svg {...base(className)}>
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </svg>
    );
}

export function HistoryIcon({ className }) {
    return (
        <svg {...base(className)}>
            <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
            <path d="M3 4v4h4" />
            <path d="M12 8v4l3 2" />
        </svg>
    );
}
