export default function NeuPill({ children, variant = 'default', className = '' }) {
    const colors = {
        default: 'text-neu-sub',
        done: 'text-neu-accent',
        // Dipakai untuk PM yang sudah dikerjakan tapi masih di rantai approval.
        progress: 'text-neu-info',
        todo: 'text-neu-warn',
        issue: 'text-neu-bad',
    };

    return (
        <span className={`neu-pill ${colors[variant] || colors.default} ${className}`}>
            {children}
        </span>
    );
}
