import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verifikasi Email" />

            <NeuCard className="w-full max-w-md">
                <p className="mb-4 text-sm text-neu-sub">
                    Terima kasih sudah mendaftar! Sebelum memulai, silakan verifikasi
                    alamat email Anda dengan mengklik tautan yang baru kami kirim.
                    Jika belum menerima email, kami akan mengirim ulang dengan senang hati.
                </p>

                {status === 'verification-link-sent' && (
                    <NeuCard className="mb-4 !bg-[#DCFCE7] !shadow-neu-in">
                        <p className="text-sm font-medium text-neu-accent">
                            Tautan verifikasi baru telah dikirim ke alamat email yang Anda
                            daftarkan.
                        </p>
                    </NeuCard>
                )}

                <form onSubmit={submit} className="space-y-3">
                    <div className="flex items-center justify-between">
                        <NeuButton type="submit" variant="primary" disabled={processing} className="flex-1">
                            Kirim Ulang Verifikasi
                        </NeuButton>

                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="text-sm text-neu-accent underline hover:text-neu-warn focus:outline-none focus:ring-2 focus:ring-neu-accent/40 rounded px-1"
                        >
                            Keluar
                        </Link>
                    </div>
                </form>
            </NeuCard>
        </GuestLayout>
    );
}
