import { useEffect, useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';
import NeuChip from '@/Components/NeuChip';

export default function Roles({ auth, roles = [], permissions = [] }) {
    const createForm = useForm({ name: '' });
    const [editingId, setEditingId] = useState(null);
    const [confirmingId, setConfirmingId] = useState(null);
    const editForm = useForm({ name: '', permissions: [] });

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('admin.roles.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const startEdit = (role) => {
        setConfirmingId(null);
        editForm.clearErrors();
        editForm.setData({
            name: role.name,
            permissions: role.permissions || [],
        });
        setEditingId(role.id);
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.clearErrors();
    };

    const togglePermission = (perm) => {
        const current = editForm.data.permissions || [];
        if (current.includes(perm)) {
            editForm.setData('permissions', current.filter((p) => p !== perm));
        } else {
            editForm.setData('permissions', [...current, perm]);
        }
    };

    const submitEdit = (e) => {
        e.preventDefault();
        editForm.put(route('admin.roles.update', editingId), {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const destroyRole = (role) => {
        router.delete(route('admin.roles.destroy', role.id), {
            preserveScroll: true,
            onFinish: () => setConfirmingId(null),
        });
    };

    useEffect(() => {
        editForm.clearErrors();
    }, [editingId]);

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <h1 className="text-xl sm:text-2xl font-bold">Kelola Role</h1>
                    <p className="text-sm text-neu-sub">{roles.length} role terdaftar</p>
                </div>

                <NeuCard className="mb-5">
                    <h2 className="font-bold text-sm mb-3">Tambah Role</h2>
                    <form onSubmit={handleCreate} className="flex flex-col gap-3 sm:flex-row">
                        <input
                            type="text"
                            placeholder="Nama role (mis. supervisor)"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value.toLowerCase())}
                            className="neu-input flex-1"
                            required
                        />
                        <NeuButton type="submit" variant="primary" disabled={createForm.processing} className="self-start">
                            {createForm.processing ? 'Menambahkan...' : '+ Tambah Role'}
                        </NeuButton>
                    </form>
                    {createForm.errors.name && (
                        <p className="mt-1.5 text-[11px] font-bold text-neu-bad">{createForm.errors.name}</p>
                    )}
                </NeuCard>

                <div className="space-y-3">
                    {roles.map((role) => (
                        <NeuCard key={role.id} className="!p-3.5">
                            {editingId === role.id ? (
                                <form onSubmit={submitEdit} className="space-y-4">
                                    <div>
                                        <label className="block text-xs text-neu-sub mb-1.5">Nama Role</label>
                                        <input
                                            type="text"
                                            value={editForm.data.name}
                                            onChange={(e) => editForm.setData('name', e.target.value.toLowerCase())}
                                            className="neu-input"
                                            required
                                        />
                                        {editForm.errors.name && (
                                            <p className="mt-1 text-[11px] font-bold text-neu-bad">{editForm.errors.name}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs text-neu-sub mb-2">Permissions</label>
                                        <div className="flex flex-wrap gap-2 max-h-64 overflow-y-auto p-1.5 neu-inset">
                                            {permissions.map((perm) => {
                                                const active = editForm.data.permissions?.includes(perm);
                                                return (
                                                    <NeuChip
                                                        key={perm}
                                                        active={active}
                                                        onClick={() => togglePermission(perm)}
                                                        type="button"
                                                    >
                                                        {perm}
                                                    </NeuChip>
                                                );
                                            })}
                                        </div>
                                        {editForm.errors.permissions && (
                                            <p className="mt-1 text-[11px] font-bold text-neu-bad">{editForm.errors.permissions}</p>
                                        )}
                                    </div>

                                    <div className="flex gap-2">
                                        <NeuButton type="submit" variant="primary" disabled={editForm.processing}>
                                            {editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                        </NeuButton>
                                        <NeuButton type="button" onClick={cancelEdit} disabled={editForm.processing}>
                                            Batal
                                        </NeuButton>
                                    </div>
                                </form>
                            ) : (
                                <>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <NeuPill variant="todo">{role.name}</NeuPill>
                                            <div className="text-xs text-neu-sub mt-2">
                                                {role.permissions?.length || 0} permission
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2 justify-end">
                                            <NeuButton
                                                type="button"
                                                onClick={() => startEdit(role)}
                                                className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                            >
                                                Atur Permissions
                                            </NeuButton>
                                            {!role.is_system && (
                                                <NeuButton
                                                    type="button"
                                                    onClick={() => {
                                                        setEditingId(null);
                                                        setConfirmingId(role.id);
                                                    }}
                                                    className="!min-h-[36px] !px-3 !py-1.5 text-xs !text-neu-bad"
                                                >
                                                    Hapus
                                                </NeuButton>
                                            )}
                                        </div>
                                    </div>

                                    {confirmingId === role.id && (
                                        <div className="mt-3 neu-inset rounded-neu-sm px-3.5 py-3 flex flex-wrap items-center gap-2">
                                            <p className="text-xs font-bold text-neu-bad flex-1 min-w-[14rem]">
                                                Hapus role "{role.name}"? Pengguna yang memiliki role ini perlu diatur ulang.
                                            </p>
                                            <NeuButton
                                                type="button"
                                                onClick={() => destroyRole(role)}
                                                className="!text-neu-bad"
                                            >
                                                Ya, hapus
                                            </NeuButton>
                                            <NeuButton type="button" onClick={() => setConfirmingId(null)}>
                                                Batal
                                            </NeuButton>
                                        </div>
                                    )}

                                    {role.permissions?.length > 0 && (
                                        <div className="mt-3 flex flex-wrap gap-1.5">
                                            {role.permissions.slice(0, 12).map((perm) => (
                                                <span key={perm} className="neu-pill text-neu-sub">
                                                    {perm}
                                                </span>
                                            ))}
                                            {role.permissions.length > 12 && (
                                                <span className="neu-pill text-neu-sub">
                                                    +{role.permissions.length - 12} lainnya
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </NeuCard>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
