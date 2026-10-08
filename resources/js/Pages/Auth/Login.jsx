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

            <NeuCard className="w-full max-w-md p-6 sm:p-8">
                {status && (
                    <div className="neu-inset mb-4 px-3 py-2 rounded-neu-sm">
                        <p className="text-sm font-medium text-neu-accent">{status}</p>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5">
                    <div>
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1.5"
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
                            className="mt-1.5"
                            autoComplete="current-password"
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="flex items-center">
                        <label className="flex items-center cursor-pointer gap-2">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) =>
                                    setData('remember', e.target.checked)
                                }
                            />
                            <span className="text-sm text-neu-sub">Ingat saya</span>
                        </label>
                    </div>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between pt-1">
                        {canResetPassword ? (
                            <Link
                                href={route('password.request')}
                                className="text-sm text-neu-accent hover:text-neu-warn focus:outline-none focus:ring-2 focus:ring-neu-accent/40 rounded px-1"
                            >
                                Lupa password?
                            </Link>
                        ) : (
                            <span className="hidden sm:block" />
                        )}

                        <NeuButton type="submit" variant="primary" disabled={processing} className="w-full sm:w-auto sm:min-w-[120px]">
                            Masuk
                        </NeuButton>
                    </div>
                </form>
            </NeuCard>
        </GuestLayout>
    );
}
