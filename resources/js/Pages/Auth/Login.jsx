import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Masuk" />

            <NeuCard className="w-full max-w-md">
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
                            autoComplete="username"
                            isFocused={true}
                            onChange={(e) => setData('email', e.target.value)}
                        />

                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password" value="Password" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1"
                            autoComplete="current-password"
                            onChange={(e) => setData('password', e.target.value)}
                        />

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="flex items-center">
                        <label className="flex items-center cursor-pointer">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) =>
                                    setData('remember', e.target.checked)
                                }
                            />
                            <span className="ms-2 text-sm text-neu-sub">Ingat saya</span>
                        </label>
                    </div>

                    <div className="flex items-center justify-between pt-2">
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-sm text-neu-accent underline hover:text-neu-warn focus:outline-none focus:ring-2 focus:ring-neu-accent/40 rounded px-1"
                            >
                                Lupa password?
                            </Link>
                        )}

                        <NeuButton type="submit" variant="primary" disabled={processing} className="w-full sm:w-auto">
                            Masuk
                        </NeuButton>
                    </div>
                </form>
            </NeuCard>
        </GuestLayout>
    );
}
