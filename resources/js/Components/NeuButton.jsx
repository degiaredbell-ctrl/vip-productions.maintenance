import { Link } from '@inertiajs/react';

export default function NeuButton({ children, href, onClick, type = 'button', variant = 'default', className = '', disabled = false, ...props }) {
    const baseClass = variant === 'primary' ? 'neu-btn-primary' : 'neu-btn';
    const fullClass = `${baseClass} ${className}`;

    if (href) {
        return (
            <Link href={href} className={fullClass} {...props}>
                {children}
            </Link>
        );
    }

    return (
        <button type={type} onClick={onClick} className={fullClass} disabled={disabled} {...props}>
            {children}
        </button>
    );
}
