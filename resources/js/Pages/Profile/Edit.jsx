import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import UpdateSignatureForm from './Partials/UpdateSignatureForm';

export default function Edit({ mustVerifyEmail, status, savedSignature }) {
    return (
        <AuthenticatedLayout>
            <Head title="Profile" />

            <div className="page-container">
                <div className="mb-6">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Profile Pengguna</h1>
                </div>

                <div className="space-y-4 max-w-2xl">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                    />
                    <UpdateSignatureForm savedSignature={savedSignature} />
                    <UpdatePasswordForm />
                    <DeleteUserForm />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}