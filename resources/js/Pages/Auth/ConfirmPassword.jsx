import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Konfirmasi Password" />

            <NeuCard className="w-full max-w-md">
                <p className="mb-4 text-sm text-neu-sub">
                    Area ini memerlukan keamanan tambahan. Silakan konfirmasi password Anda
                    sebelum melanjutkan.
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <InputLabel htmlFor="password" value="Password" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1"
                            isFocused={true}
                            onChange={(e) => setData('password', e.target.value)}
                        />

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="pt-2">
                        <NeuButton type="submit" variant="primary" disabled={processing} className="w-full">
                            Konfirmasi
                        </NeuButton>
                    </div>
                </form>
            </NeuCard>
        </GuestLayout>
    );
}
