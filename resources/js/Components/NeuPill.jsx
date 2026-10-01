export default function NeuPill({ children, variant = 'default', className = '' }) {
    const colors = {
        default: 'text-neu-sub',
        done: 'text-neu-accent',
        todo: 'text-neu-warn',
        issue: 'text-neu-bad',
    };

    return (
        <span className={`neu-pill ${colors[variant] || colors.default} ${className}`}>
            {children}
        </span>
    );
}
