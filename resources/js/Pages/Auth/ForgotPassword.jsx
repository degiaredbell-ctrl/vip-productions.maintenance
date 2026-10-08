import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Lupa Password" />

            <NeuCard className="w-full max-w-md">
                <p className="mb-4 text-sm text-neu-sub">
                    Lupa password? Tidak masalah. Masukkan alamat email Anda dan kami akan
                    mengirimkan tautan untuk memilih password baru.
                </p>

                {status && (
                    <NeuCard className="mb-4 !bg-[#DCFCE7] !shadow-neu-in">
                        <p className="text-sm font-medium text-neu-accent">{status}</p>
                    </NeuCard>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <InputLabel htmlFor="email" value="Email" />

                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1"
                            isFocused={true}
                            onChange={(e) => setData('email', e.target.value)}
                        />

                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div className="pt-2">
                        <NeuButton type="submit" variant="primary" disabled={processing} className="w-full">
                            Kirim Tautan Reset Password
                        </NeuButton>
                    </div>
                </form>

                <div className="mt-4 text-center">
                    <Link
                        href={route('login')}
                        className="text-sm text-neu-accent underline hover:text-neu-warn"
                    >
                        Kembali ke Masuk
                    </Link>
                </div>
            </NeuCard>
        </GuestLayout>
    );
}
