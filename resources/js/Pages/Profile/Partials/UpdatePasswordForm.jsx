import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function UpdatePasswordForm({ className = '' }) {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                }
            },
        });
    };

    return (
        <NeuCard className={className}>
            <header className="mb-4">
                <h2 className="text-lg font-bold text-neu-text">Ubah Password</h2>
                <p className="mt-1 text-sm text-neu-sub">
                    Pastikan akun Anda menggunakan password yang panjang dan acak untuk keamanan.
                </p>
            </header>

            <form onSubmit={updatePassword} className="space-y-4">
                <div>
                    <InputLabel
                        htmlFor="current_password"
                        value="Password Saat Ini"
                    />

                    <TextInput
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                        type="password"
                        className="mt-1"
                        autoComplete="current-password"
                    />

                    <InputError
                        message={errors.current_password}
                        className="mt-2"
                    />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password Baru" />

                    <TextInput
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        className="mt-1"
                        autoComplete="new-password"
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Konfirmasi Password"
                    />

                    <TextInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        type="password"
                        className="mt-1"
                        autoComplete="new-password"
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <div className="flex items-center gap-4 pt-2">
                    <NeuButton type="submit" variant="primary" disabled={processing}>
                        Simpan
                    </NeuButton>

                    {recentlySuccessful && (
                        <span className="text-sm text-neu-accent font-medium transition-opacity duration-300">
                            Tersimpan.
                        </span>
                    )}
                </div>
            </form>
        </NeuCard>
    );
}
