import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuPill from '@/Components/NeuPill';

const slug = (value) => value.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 30);

/**
 * Konfigurasi per jenis master data. Satu halaman dipakai untuk Tipe Mesin
 * dan Lokasi / Site karena alurnya sama: tambah, ubah nama/keterangan, hapus
 * bila tidak dipakai.
 */
const KINDS = {
    types: {
        title: 'Tipe Mesin',
        intro: 'Tipe unit yang dipilih saat menambah Mesin dan membuat Template. Kode dipakai sistem dan tidak bisa diubah setelah dibuat; nama tampil bebas diubah.',
        singular: 'tipe',
        routes: { store: 'admin.machine-types.store', update: 'admin.machine-types.update', destroy: 'admin.machine-types.destroy' },
        empty: { name: '', code: '', description: '' },
        namePlaceholder: 'Nama tipe (mis. Packing)',
        usage: (item) => [
            `${item.units_count} unit`,
            item.templates_count > 0 ? `${item.templates_count} template` : null,
        ].filter(Boolean).join(' · '),
        lockReason: (item) => {
            if (item.is_system) return 'Tipe sistem tidak bisa dihapus';
            if (item.units_count > 0 || item.templates_count > 0) return 'Masih dipakai — pindahkan unit/template dulu';
            return null;
        },
    },
    locations: {
        title: 'Lokasi / Site',
        intro: 'Lokasi / site yang dipilih untuk unit Mesin dan Utility. Mengganti nama lokasi ikut memperbarui semua unit yang memakainya.',
        singular: 'lokasi',
        routes: { store: 'admin.locations.store', update: 'admin.locations.update', destroy: 'admin.locations.destroy' },
        empty: { name: '', description: '' },
        namePlaceholder: 'Nama lokasi / site (mis. Factory Milenium)',
        usage: (item) => `${item.units_count} unit`,
        lockReason: (item) => (item.units_count > 0 ? 'Masih dipakai — pindahkan unit dulu' : null),
    },
};

function FieldError({ message }) {
    return message ? <p className="mt-1 text-[11px] font-bold text-neu-bad">{message}</p> : null;
}

export default function MasterData({ auth, kind, items = [] }) {
    const config = KINDS[kind];
    const isTypes = kind === 'types';

    const createForm = useForm({ ...config.empty });
    const editForm = useForm({ name: '', description: '' });
    const [editingId, setEditingId] = useState(null);
    const [busyId, setBusyId] = useState(null);
    const [q, setQ] = useState('');
    const [codeTouched, setCodeTouched] = useState(false);

    const visible = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return items;
        return items.filter((item) => [item.name, item.code, item.description].some((v) => String(v ?? '').toLowerCase().includes(needle)));
    }, [items, q]);

    const submitCreate = (e) => {
        e.preventDefault();
        createForm.post(route(config.routes.store), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setCodeTouched(false);
            },
        });
    };

    const startEdit = (item) => {
        editForm.clearErrors();
        editForm.setData({ name: item.name, description: item.description ?? '' });
        setEditingId(item.id);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        editForm.put(route(config.routes.update, editingId), {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const destroy = (item) => {
        if (!window.confirm(`Hapus ${config.singular} "${item.name}"?`)) return;
        setBusyId(item.id);
        router.delete(route(config.routes.destroy, item.id), {
            preserveScroll: true,
            onFinish: () => setBusyId(null),
        });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="flex flex-wrap items-end justify-between gap-3 mb-5">
                    <div>
                        <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                        <h1 className="text-xl sm:text-2xl font-bold">{config.title}</h1>
                        <p className="text-sm text-neu-sub mt-0.5 max-w-2xl">{config.intro}</p>
                    </div>
                    <NeuButton href={route('settings')} className="!min-h-[40px] !py-2 !text-sm">‹ Settings</NeuButton>
                </div>

                {/* Tambah */}
                <NeuCard className="mb-4">
                    <b className="text-sm block mb-3">Tambah {config.title}</b>
                    <form onSubmit={submitCreate} className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <input
                                className="neu-input"
                                placeholder={config.namePlaceholder}
                                value={createForm.data.name}
                                onChange={(e) => {
                                    const name = e.target.value;
                                    createForm.setData((d) => ({ ...d, name, ...(isTypes && !codeTouched ? { code: slug(name) } : {}) }));
                                }}
                                maxLength={isTypes ? 50 : 100}
                                required
                                aria-label="Nama"
                            />
                            <FieldError message={createForm.errors.name} />
                        </div>
                        {isTypes && (
                            <div>
                                <input
                                    className="neu-input font-mono"
                                    placeholder="kode_tipe (otomatis)"
                                    value={createForm.data.code}
                                    onChange={(e) => {
                                        setCodeTouched(true);
                                        createForm.setData('code', slug(e.target.value));
                                    }}
                                    maxLength={30}
                                    aria-label="Kode tipe"
                                />
                                <FieldError message={createForm.errors.code} />
                            </div>
                        )}
                        <div className={isTypes ? '' : 'sm:col-span-2'}>
                            <input
                                className="neu-input"
                                placeholder="Keterangan (opsional)"
                                value={createForm.data.description}
                                onChange={(e) => createForm.setData('description', e.target.value)}
                                maxLength={255}
                                aria-label="Keterangan"
                            />
                            <FieldError message={createForm.errors.description} />
                        </div>
                        <div className="sm:col-span-3 flex justify-end">
                            <NeuButton type="submit" variant="primary" disabled={createForm.processing}>
                                {createForm.processing ? 'Menyimpan…' : `+ Tambah ${config.singular}`}
                            </NeuButton>
                        </div>
                    </form>
                </NeuCard>

                <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <p className="text-xs text-neu-sub">{items.length} {config.singular} terdaftar</p>
                    {items.length > 6 && (
                        <input type="search" className="neu-input max-w-xs" placeholder="Cari…" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Cari" />
                    )}
                </div>

                {visible.length === 0 ? (
                    <NeuCard>
                        <p className="text-sm text-neu-sub text-center py-6">
                            {items.length === 0 ? `Belum ada ${config.singular}.` : 'Tidak ada yang cocok.'}
                        </p>
                    </NeuCard>
                ) : (
                    <div className="neu-table-wrap">
                        <table className="neu-table">
                            <thead>
                                <tr>
                                    <th scope="col">Nama</th>
                                    {isTypes && <th scope="col">Kode</th>}
                                    <th scope="col">Dipakai</th>
                                    <th scope="col" className="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((item) => {
                                    const editing = editingId === item.id;
                                    const lock = config.lockReason(item);

                                    if (editing) {
                                        return (
                                            <tr key={item.id}>
                                                <td colSpan={isTypes ? 4 : 3} className="neu-cell-lead">
                                                    <form onSubmit={submitEdit} className="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-start">
                                                        <div>
                                                            <input className="neu-input" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} required maxLength={isTypes ? 50 : 100} aria-label="Nama" autoFocus />
                                                            <FieldError message={editForm.errors.name} />
                                                        </div>
                                                        <div>
                                                            <input className="neu-input" value={editForm.data.description} onChange={(e) => editForm.setData('description', e.target.value)} placeholder="Keterangan (opsional)" maxLength={255} aria-label="Keterangan" />
                                                            <FieldError message={editForm.errors.description} />
                                                        </div>
                                                        <div className="flex gap-2">
                                                            <NeuButton type="button" onClick={() => setEditingId(null)} className="!min-h-[40px] !py-2 !text-sm">Batal</NeuButton>
                                                            <NeuButton type="submit" variant="primary" disabled={editForm.processing} className="!min-h-[40px] !py-2 !text-sm">Simpan</NeuButton>
                                                        </div>
                                                    </form>
                                                    {isTypes && <p className="mt-1.5 text-[11px] text-neu-sub">Kode <span className="font-mono">{item.code}</span> tetap.</p>}
                                                    {!isTypes && item.units_count > 0 && (
                                                        <p className="mt-1.5 text-[11px] text-neu-sub">Mengganti nama akan memperbarui {item.units_count} unit.</p>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    }

                                    return (
                                        <tr key={item.id}>
                                            <td className="neu-cell-lead">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <b className="text-sm break-words">{item.name}</b>
                                                    {item.is_system && <NeuPill variant="progress">Sistem</NeuPill>}
                                                </div>
                                                {item.description && <span className="block text-xs text-neu-sub break-words">{item.description}</span>}
                                            </td>
                                            {isTypes && (
                                                <td data-label="Kode"><span className="font-mono text-xs">{item.code}</span></td>
                                            )}
                                            <td data-label="Dipakai"><span className="text-xs">{config.usage(item)}</span></td>
                                            <td data-label="Aksi">
                                                <div className="flex gap-2 lg:justify-end">
                                                    <NeuButton onClick={() => startEdit(item)} className="!min-h-[36px] !py-1.5 !px-3 !text-xs">Ubah</NeuButton>
                                                    <NeuButton
                                                        onClick={() => destroy(item)}
                                                        disabled={Boolean(lock) || busyId === item.id}
                                                        title={lock ?? undefined}
                                                        className="!min-h-[36px] !py-1.5 !px-3 !text-xs text-neu-bad"
                                                    >
                                                        Hapus
                                                    </NeuButton>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
