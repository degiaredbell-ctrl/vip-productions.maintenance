import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import { useForm, useRef, useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <NeuCard className={className}>
            <header className="mb-4">
                <h2 className="text-lg font-bold text-neu-text">Hapus Akun</h2>
                <p className="mt-1 text-sm text-neu-sub">
                    Saat akun Anda dihapus, semua data dan resource-nya akan dihapus permanen.
                    Sebelum menghapus akun, silakan unduh data yang ingin Anda simpan.
                </p>
            </header>

            <NeuButton onClick={confirmUserDeletion} className="!text-neu-bad !shadow-neu-up">
                Hapus Akun
            </NeuButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-bold text-neu-text">
                        Apakah Anda yakin ingin menghapus akun ini?
                    </h2>

                    <p className="mt-1 text-sm text-neu-sub">
                        Semua resource dan data akan dihapus permanen. Masukkan password
                        Anda untuk mengonfirmasi penghapusan akun.
                    </p>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="password"
                            value="Password"
                            className="sr-only"
                        />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="mt-1 block w-3/4"
                            isFocused
                            placeholder="Password"
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <NeuButton type="button" onClick={closeModal}>
                            Batal
                        </NeuButton>

                        <NeuButton type="submit" variant="primary" disabled={processing} className="!text-neu-bad">
                            {processing ? 'Menghapus...' : 'Hapus Akun'}
                        </NeuButton>
                    </div>
                </form>
            </Modal>
        </NeuCard>
    );
}
