import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

const digitsOnly = (value) => value.replace(/\D/g, '').slice(0, 6);

export default function UpdatePinForm({ hasPin, className = '' }) {
    const { data, setData, errors, put, delete: destroy, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        pin: '',
        pin_confirmation: '',
    });

    const savePin = (e) => {
        e.preventDefault();

        put(route('profile.pin.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: () => reset('current_password', 'pin', 'pin_confirmation'),
        });
    };

    const removePin = () => {
        destroy(route('profile.pin.destroy'), {
            preserveScroll: true,
            onFinish: () => reset(),
        });
    };

    return (
        <NeuCard className={className}>
            <header className="mb-4">
                <h2 className="text-lg font-bold text-neu-text">PIN Login</h2>
                <p className="mt-1 text-sm text-neu-sub">
                    Masuk cepat dengan 6 digit angka tanpa mengetik email. Status:{' '}
                    <b className={hasPin ? 'text-neu-accent' : ''}>{hasPin ? 'PIN aktif' : 'Belum diatur'}</b>
                </p>
            </header>

            <form onSubmit={savePin} className="space-y-4">
                <div>
                    <InputLabel htmlFor="pin_current_password" value="Password Saat Ini" />
                    <TextInput
                        id="pin_current_password"
                        type="password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        className="mt-1"
                        autoComplete="current-password"
                    />
                    <InputError message={errors.current_password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="pin" value={hasPin ? 'PIN Baru (6 digit)' : 'PIN (6 digit)'} />
                    <TextInput
                        id="pin"
                        type="password"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        maxLength={6}
                        value={data.pin}
                        onChange={(e) => setData('pin', digitsOnly(e.target.value))}
                        className="mt-1 tracking-[0.4em]"
                        autoComplete="off"
                    />
                    <InputError message={errors.pin} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="pin_confirmation" value="Ulangi PIN" />
                    <TextInput
                        id="pin_confirmation"
                        type="password"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        maxLength={6}
                        value={data.pin_confirmation}
                        onChange={(e) => setData('pin_confirmation', digitsOnly(e.target.value))}
                        className="mt-1 tracking-[0.4em]"
                        autoComplete="off"
                    />
                </div>

                <div className="flex items-center gap-3 flex-wrap pt-2">
                    <NeuButton type="submit" variant="primary" disabled={processing}>
                        {hasPin ? 'Ganti PIN' : 'Simpan PIN'}
                    </NeuButton>

                    {hasPin && (
                        <NeuButton
                            onClick={removePin}
                            disabled={processing || data.current_password === ''}
                            className="!text-neu-bad"
                            title="Isi password saat ini untuk menghapus PIN"
                        >
                            Hapus PIN
                        </NeuButton>
                    )}

                    {recentlySuccessful && (
                        <span className="text-sm text-neu-accent font-medium">Tersimpan.</span>
                    )}
                </div>
            </form>
        </NeuCard>
    );
}
