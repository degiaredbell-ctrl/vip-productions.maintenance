const DOT_LABELS = {
    green: 'Semua mesin selesai',
    blue: 'Ada mesin belum selesai di periode ini',
    orange: 'Periode sebelumnya belum selesai',
    red: 'Ada tunggakan lebih lama',
    muted: 'Periode belum dibuka',
};

export default function NeuChip({ children, active = false, onClick, className = '', dot = null, title, ...props }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`neu-chip ${active ? 'neu-chip-active' : ''} ${className}`}
            aria-pressed={active}
            {...(dot ? { title: title ?? DOT_LABELS[dot], 'aria-label': `${children} — ${DOT_LABELS[dot]}` } : {})}
            {...props}
        >
            {dot && <span className={`neu-dot neu-dot-${dot} ${active ? 'neu-dot-live' : ''}`} aria-hidden="true" />}
            {children}
        </button>
    );
}