export default function NeuChip({ children, active = false, onClick, className = '', ...props }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`neu-chip ${active ? 'neu-chip-active' : ''} ${className}`}
            aria-pressed={active}
            {...props}
        >
            {children}
        </button>
    );
}
