import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function Guest({ children }) {
    return (
        <div className="min-h-screen flex flex-col items-center px-4 py-10 bg-neu-bg">
            <div className="mb-6">
                <Link href="/">
                    <ApplicationLogo className="w-20 h-20 fill-current text-neu-accent" />
                </Link>
            </div>

            <div className="w-full sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
