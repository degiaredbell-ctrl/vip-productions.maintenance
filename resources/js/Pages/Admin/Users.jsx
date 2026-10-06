import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';
import { useState } from 'react';

const roleLabels = {
    admin: 'Admin',
    manager: 'Manager',
    technician: 'Teknisi',
    user: 'User',
    viewer: 'Viewer',
};

const labelOf = (role) => roleLabels[role] ?? role;

const initials = (name) =>
    name
        .split(' ')
        .filter(Boolean)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

export default function AdminUsers({ auth, users, roles }) {
    // Formulir tambah
    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'user',
    });

    // Satu form edit dipakai bergantian, supaya daftar tetap ringkas dan
    // tidak ada dua formulir bertumpuk untuk pengguna yang sama.
    const [editingId, setEditingId] = useState(null);
    const [passwordId, setPasswordId] = useState(null);
    const [confirmingId, setConfirmingId] = useState(null);
    const [busyId, setBusyId] = useState(null);
    const [q, setQ] = useState('');

    const editForm = useForm({ role: 'user' });
    const passForm = useForm({ password: '', password_confirmation: '' });

    const roleOptions = roles ?? [];

    const submitCreate = (e) => {
        e.preventDefault();

        createForm.post(route('admin.users.store'), {
            preserveScroll: true,
            // Tanpa reset, isian pengguna yang barusan ditambahkan masih
            // tertinggal dan terlihat seperti gagal disimpan.
            onSuccess: () => createForm.reset(),
        });
    };

    const startEdit = (user) => {
        setPasswordId(null);
        setConfirmingId(null);
        editForm.clearErrors();
        editForm.setData({ role: user.role });
        setEditingId(user.id);
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.clearErrors();
    };

    const submitEdit = (e) => {
        e.preventDefault();

        editForm.put(route('admin.users.update', editingId), {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const startPassword = (user) => {
        setEditingId(null);
        setConfirmingId(null);
        passForm.clearErrors();
        passForm.reset();
        setPasswordId(user.id);
    };

    const cancelPassword = () => {
        setPasswordId(null);
        passForm.clearErrors();
    };

    const submitPassword = (e) => {
        e.preventDefault();

        passForm.put(route('admin.users.update-password', passwordId), {
            preserveScroll: true,
            onSuccess: () => setPasswordId(null),
        });
    };

    const askDelete = (user) => {
        setEditingId(null);
        setPasswordId(null);
        setConfirmingId(user.id);
    };

    const cancelDelete = () => setConfirmingId(null);

    const destroyUser = (user) => {
        setBusyId(user.id);

        router.delete(route('admin.users.destroy', user.id), {
            preserveScroll: true,
            onFinish: () => {
                setBusyId(null);
                setConfirmingId(null);
            },
        });
    };

    const needle = q.trim().toLowerCase();
    const visible = needle
        ? users.filter((u) => `${u.name} ${u.email} ${labelOf(u.role)}`.toLowerCase().includes(needle))
        : users;

    const fieldError = (form, field) =>
        form.errors[field] ? <p className="text-xs text-neu-bad mt-1">{form.errors[field]}</p> : null;

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <h1 className="text-xl sm:text-2xl font-bold">Kelola Pengguna</h1>
                    <p className="text-sm text-neu-sub">
                        {users.length} pengguna terdaftar
                        {visible.length !== users.length && ` · ${visible.length} ditampilkan`}
                    </p>
                </div>

                {/* Tambah pengguna */}
                <NeuCard className="mb-5">
                    <h2 className="font-bold text-sm mb-3">Tambah Pengguna</h2>
                    <form onSubmit={submitCreate} className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <input
                                type="text"
                                placeholder="Nama"
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                className="neu-input"
                                aria-invalid={Boolean(createForm.errors.name)}
                                required
                            />
                            {fieldError(createForm, 'name')}
                        </div>
                        <div>
                            <input
                                type="email"
                                placeholder="Email"
                                value={createForm.data.email}
                                onChange={(e) => createForm.setData('email', e.target.value)}
                                className="neu-input"
                                aria-invalid={Boolean(createForm.errors.email)}
                                required
                            />
                            {fieldError(createForm, 'email')}
                        </div>
                        <div>
                            <input
                                type="password"
                                placeholder="Password (min. 8 karakter)"
                                value={createForm.data.password}
                                onChange={(e) => createForm.setData('password', e.target.value)}
                                className="neu-input"
                                aria-invalid={Boolean(createForm.errors.password)}
                                required
                            />
                            {fieldError(createForm, 'password')}
                        </div>
                        <div>
                            <input
                                type="password"
                                placeholder="Ulangi password"
                                value={createForm.data.password_confirmation}
                                onChange={(e) => createForm.setData('password_confirmation', e.target.value)}
                                className="neu-input"
                                required
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <select
                                value={createForm.data.role}
                                onChange={(e) => createForm.setData('role', e.target.value)}
                                className="neu-input"
                            >
                                {roleOptions.map((r) => (
                                    <option key={r} value={r}>{labelOf(r)}</option>
                                ))}
                            </select>
                        </div>
                        <div className="sm:col-span-2">
                            <NeuButton type="submit" variant="primary" disabled={createForm.processing}>
                                {createForm.processing ? 'Menambahkan...' : '+ Tambah Pengguna'}
                            </NeuButton>
                        </div>
                    </form>
                </NeuCard>

                {/* Pencarian */}
                <input
                    type="search"
                    placeholder="Cari nama, email, atau role…"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    className="neu-input mb-4"
                    aria-label="Cari pengguna"
                />

                {/* Daftar pengguna */}
                {visible.length === 0 ? (
                    <NeuCard className="mb-3 !shadow-neu-in">
                        <p className="text-sm text-neu-sub text-center">Tidak ada pengguna yang cocok.</p>
                    </NeuCard>
                ) : (
                    <div className="space-y-3">
                        {visible.map((user) => (
                            <NeuCard key={user.id} className="!p-3.5">
                                {editingId === user.id ? (
                                    <form onSubmit={submitEdit}>
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <label className="block text-xs text-neu-sub mb-1" htmlFor={`name-${user.id}`}>
                                                    Nama
                                                </label>
                                                <p className="neu-inset px-4 py-3 text-neu-text" id={`name-${user.id}`}>
                                                    {user.name}
                                                </p>
                                            </div>
                                            <div className="sm:col-span-2">
                                                <label className="block text-xs text-neu-sub mb-1" htmlFor={`email-${user.id}`}>
                                                    Email
                                                </label>
                                                <p className="neu-inset px-4 py-3 text-neu-text break-all" id={`email-${user.id}`}>
                                                    {user.email}
                                                </p>
                                            </div>
                                            <div className="sm:col-span-2">
                                                <label className="block text-xs text-neu-sub mb-1" htmlFor={`role-${user.id}`}>
                                                    Role
                                                </label>
                                                <select
                                                    id={`role-${user.id}`}
                                                    value={editForm.data.role}
                                                    onChange={(e) => editForm.setData('role', e.target.value)}
                                                    className="neu-input"
                                                    disabled={user.is_self}
                                                >
                                                    {roleOptions.map((r) => (
                                                        <option key={r} value={r}>{labelOf(r)}</option>
                                                    ))}
                                                </select>
                                                {/*
                                                    Menonaktifkan role sendiri mencegah admin mengunci
                                                    dirinya sendiri keluar dari halaman ini.
                                                */}
                                                {user.is_self && (
                                                    <p className="text-xs text-neu-sub mt-1">
                                                        Role akun Anda sendiri tidak bisa diubah.
                                                    </p>
                                                )}
                                                {fieldError(editForm, 'role')}
                                            </div>
                                        </div>
                                        <p className="text-xs text-neu-sub mt-3.5">
                                            Nama dan email hanya bisa diubah lewat seeder akun, bukan dari
                                            halaman ini.
                                        </p>
                                        <div className="flex gap-2.5 flex-wrap mt-3.5">
                                            <NeuButton type="submit" variant="primary" disabled={editForm.processing}>
                                                {editForm.processing ? 'Menyimpan...' : 'Simpan Role'}
                                            </NeuButton>
                                            <NeuButton onClick={cancelEdit}>Batal</NeuButton>
                                        </div>
                                    </form>
                                ) : passwordId === user.id ? (
                                    <form onSubmit={submitPassword}>
                                        <p className="text-xs text-neu-sub mb-2">
                                            Password baru untuk <b>{user.name}</b> ({user.email})
                                        </p>
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div>
                                                <label className="block text-xs text-neu-sub mb-1" htmlFor={`pw-${user.id}`}>
                                                    Password baru
                                                </label>
                                                <input
                                                    id={`pw-${user.id}`}
                                                    type="password"
                                                    value={passForm.data.password}
                                                    onChange={(e) => passForm.setData('password', e.target.value)}
                                                    className="neu-input"
                                                    aria-invalid={Boolean(passForm.errors.password)}
                                                    required
                                                />
                                                {fieldError(passForm, 'password')}
                                            </div>
                                            <div>
                                                <label className="block text-xs text-neu-sub mb-1" htmlFor={`pw2-${user.id}`}>
                                                    Ulangi password
                                                </label>
                                                <input
                                                    id={`pw2-${user.id}`}
                                                    type="password"
                                                    value={passForm.data.password_confirmation}
                                                    onChange={(e) => passForm.setData('password_confirmation', e.target.value)}
                                                    className="neu-input"
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div className="flex gap-2.5 flex-wrap mt-3.5">
                                            <NeuButton type="submit" variant="primary" disabled={passForm.processing}>
                                                {passForm.processing ? 'Menyimpan...' : 'Ubah Password'}
                                            </NeuButton>
                                            <NeuButton onClick={cancelPassword}>Batal</NeuButton>
                                        </div>
                                    </form>
                                ) : confirmingId === user.id ? (
                                    <div>
                                        <p className="text-sm text-neu-sub mb-3">
                                            Hapus <b>{user.name}</b> ({user.email})? Riwayat PM miliknya tetap
                                            tersimpan, tapi akun ini tidak bisa login lagi.
                                        </p>
                                        <div className="flex gap-2.5 flex-wrap">
                                            <NeuButton
                                                variant="primary"
                                                onClick={() => destroyUser(user)}
                                                disabled={busyId === user.id}
                                                className="!text-neu-bad"
                                            >
                                                {busyId === user.id ? 'Menghapus...' : 'Ya, Hapus'}
                                            </NeuButton>
                                            <NeuButton onClick={cancelDelete}>Batal</NeuButton>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-3">
                                        <span className="neu-inset w-10 h-10 flex-none grid place-items-center font-bold text-xs text-neu-accent">
                                            {initials(user.name)}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <b className="block text-sm truncate">
                                                {user.name}
                                                {user.is_self && <span className="text-neu-sub font-normal"> (Anda)</span>}
                                            </b>
                                            <span className="text-xs text-neu-sub truncate">{user.email}</span>
                                        </div>
                                        <NeuPill variant={user.role === 'admin' ? 'issue' : user.role === 'manager' ? 'todo' : 'done'}>
                                            {labelOf(user.role)}
                                        </NeuPill>
                                    </div>
                                )}

                                {/* Aksi tetap terlihat saat form terbuka agar tidak
                                    perlu menutup form dulu untuk pindah ke aksi lain. */}
                                {editingId !== user.id && passwordId !== user.id && confirmingId !== user.id && (
                                    <div className="flex gap-2 flex-wrap mt-3">
                                        <NeuButton onClick={() => startEdit(user)} className="!min-h-[36px] !py-1.5 !px-3 !text-xs">
                                            Ubah
                                        </NeuButton>
                                        <NeuButton
                                            onClick={() => startPassword(user)}
                                            className="!min-h-[36px] !py-1.5 !px-3 !text-xs"
                                        >
                                            Ubah Password
                                        </NeuButton>
                                        <NeuButton
                                            onClick={() => askDelete(user)}
                                            disabled={user.is_self}
                                            className="!min-h-[36px] !py-1.5 !px-3 !text-xs !text-neu-bad"
                                            title={user.is_self ? 'Tidak dapat menghapus akun sendiri' : undefined}
                                        >
                                            Hapus
                                        </NeuButton>
                                    </div>
                                )}
                            </NeuCard>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}