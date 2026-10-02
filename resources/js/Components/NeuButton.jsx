import { Link } from '@inertiajs/react';

export default function NeuButton({ children, href, onClick, type = 'button', variant = 'default', className = '', disabled = false, native = false, ...props }) {
    const baseClass = variant === 'primary' ? 'neu-btn-primary' : 'neu-btn';
    const fullClass = `${baseClass} ${className}`;

    if (href) {
        // Unduhan (ekspor/cetak) harus memakai anchor biasa: Inertia hanya
        // menangani respons JSON dari controller-nya sendiri, sedangkan
        // respons file (xlsx/pdf) akan dianggap respons tidak valid.
        if (native) {
            return (
                <a href={href} className={fullClass} {...props}>
                    {children}
                </a>
            );
        }

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
