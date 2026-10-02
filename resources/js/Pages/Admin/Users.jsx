import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';

export default function AdminUsers({ auth, users, roles }) {
    const { data, setData, post, processing } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'user',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.users.store'), { preserveScroll: true });
    };

    const roleLabels = { admin: 'Admin', manager: 'Manager', technician: 'Teknisi', user: 'User', viewer: 'Viewer' };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <h1 className="text-xl sm:text-2xl font-bold">Kelola Pengguna</h1>
                    <p className="text-sm text-neu-sub">{users.length} pengguna terdaftar</p>
                </div>

                {/* Add form */}
                <NeuCard className="mb-5">
                    <h2 className="font-bold text-sm mb-3">Tambah Pengguna</h2>
                    <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input
                            type="text"
                            placeholder="Nama"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="neu-input"
                            required
                        />
                        <input
                            type="email"
                            placeholder="Email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="neu-input"
                            required
                        />
                        <input
                            type="password"
                            placeholder="Password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="neu-input"
                            required
                        />
                        <select
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                            className="neu-input"
                        >
                            {roles.map((r) => (
                                <option key={r} value={r}>{roleLabels[r]}</option>
                            ))}
                        </select>
                        <div className="sm:col-span-2">
                            <NeuButton type="submit" variant="primary" disabled={processing}>
                                {processing ? 'Menambahkan...' : '+ Tambah Pengguna'}
                            </NeuButton>
                        </div>
                    </form>
                </NeuCard>

                {/* User list */}
                <div className="space-y-3">
                    {users.map((user) => (
                        <NeuCard key={user.id} className="!p-3.5">
                            <div className="flex items-center gap-3">
                                <span className="neu-inset w-10 h-10 flex-none grid place-items-center font-bold text-xs text-neu-accent">
                                    {user.name.split(' ').map(w => w[0]).join('').slice(0, 2)}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <b className="block text-sm truncate">{user.name}</b>
                                    <span className="text-xs text-neu-sub">{user.email}</span>
                                </div>
                                <NeuPill variant={user.role === 'admin' ? 'issue' : user.role === 'manager' ? 'todo' : 'done'}>
                                    {roleLabels[user.role] || user.role}
                                </NeuPill>
                            </div>
                        </NeuCard>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
