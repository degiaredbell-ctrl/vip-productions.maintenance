import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import TextInput from '@/Components/TextInput';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <NeuCard>
            <header className="mb-4">
                <h2 className="text-lg font-bold text-neu-text">Informasi Profil</h2>
                <p className="mt-1 text-sm text-neu-sub">
                    Perbarui nama dan alamat email akun Anda.
                </p>
            </header>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="name" value="Nama" />

                    <TextInput
                        id="name"
                        className="mt-1"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <NeuCard className="!shadow-neu-in !bg-[#FEF3C7]">
                        <div className="flex items-start gap-3">
                            <span className="neu-inset w-9 h-9 flex-none grid place-items-center text-neu-warn font-bold">
                                !
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-bold text-neu-warn">
                                    Alamat email Anda belum diverifikasi.
                                </p>
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="text-sm text-neu-warn underline hover:text-neu-accent focus:outline-none focus:ring-2 focus:ring-neu-accent/40 rounded px-1"
                                >
                                    Klik di sini untuk mengirim ulang email verifikasi.
                                </Link>

                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-bold text-neu-accent">
                                        Link verifikasi baru telah dikirim ke email Anda.
                                    </div>
                                )}
                            </div>
                        </div>
                    </NeuCard>
                )}

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
