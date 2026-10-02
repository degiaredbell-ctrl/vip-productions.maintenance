import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    activeClassName = '',
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            aria-current={active ? 'page' : undefined}
            className={`neu-nav ${className} ${active ? activeClassName : ''}`}
        >
            {children}
        </Link>
    );
}