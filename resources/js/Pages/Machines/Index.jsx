import { useMemo, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import MachineFields from '@/Components/MachineFields';

const EMPTY_MACHINE = {
    code: '',
    name: '',
    location: '',
    category: '',
    sub_category: '',
    type: 'generic',
    week_group: 1,
    template_id: '',
    is_active: 1,
};

/** Konvensi sub-kategori "C.7" sudah memuat nomor minggu, jadi angka yang
 *  diketik di situ langsung dipakai untuk mengisi pilihan minggu. */
const applySubCategory = (setData) => (value) => {
    setData((prev) => {
        const digits = value.match(/\d+/);

        return {
            ...prev,
            sub_category: value,
            week_group: digits
                ? Math.min(9, Math.max(1, parseInt(digits[0], 10)))
                : prev.week_group,
        };
    });
};

const machineToForm = (machine) => ({
    code: machine.code ?? '',
    name: machine.name ?? '',
    location: machine.location ?? '',
    category: machine.category ?? '',
    sub_category: machine.sub_category ?? '',
    type: machine.type ?? 'generic',
    week_group: machine.week_group ?? 1,
    template_id: machine.template_id ?? '',
    is_active: machine.is_active ? 1 : 0,
});

export default function Machines({ auth, machines, types, templates, categories, subCategories, trashed }) {
    const createForm = useForm({ ...EMPTY_MACHINE });
    const editForm = useForm({ ...EMPTY_MACHINE });

    // Daftar mesin sekarang 120+ baris, jadi pencarian dan filter area
    // dijalankan di sisi klien supaya tidak perlu reload tiap ketikan.
    const [q, setQ] = useState('');
    const [sub, setSub] = useState('all');

    // Hanya satu mesin yang bisa disunting / menunggu konfirmasi hapus pada
    // satu waktu, supaya daftar tetap ringkas dan tidak ada dua form bertumpuk.
    const [editingId, setEditingId] = useState(null);
    const [confirmingId, setConfirmingId] = useState(null);
    const [busyId, setBusyId] = useState(null);
    const [showTrashed, setShowTrashed] = useState(false);

    const typeLabels = useMemo(
        () => Object.fromEntries((types ?? []).map((t) => [t.value, t.label])),
        [types],
    );

    const visible = useMemo(() => {
        const needle = q.trim().toLowerCase();

        return machines.filter((m) => {
            if (sub !== 'all' && m.sub_category !== sub) return false;
            if (!needle) return true;

            return [m.code, m.name, m.location, m.sub_category, typeLabels[m.type]]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(needle));
        });
    }, [machines, q, sub, typeLabels]);

    const submitCreate = (e) => {
        e.preventDefault();

        createForm.post(route('machines.store'), {
            preserveScroll: true,
            // Tanpa reset, isian mesin yang barusan ditambahkan masih tertinggal
            // di formulir dan terlihat seperti gagal disimpan.
            onSuccess: () => createForm.reset(),
        });
    };

    const startEdit = (machine) => {
        setConfirmingId(null);
        editForm.clearErrors();
        editForm.setData(machineToForm(machine));
        setEditingId(machine.id);
    };

    const submitEdit = (e) => {
        e.preventDefault();

        editForm.put(route('machines.update', editingId), {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.clearErrors();
    };

    const askDelete = (machine) => {
        setEditingId(null);
        editForm.clearErrors();
        setConfirmingId(machine.id);
    };

    const destroyMachine = (machine) => {
        setBusyId(machine.id);

        router.delete(route('machines.destroy', machine.id), {
            preserveScroll: true,
            onFinish: () => {
                setBusyId(null);
                setConfirmingId(null);
            },
        });
    };

    const restoreMachine = (machine) => {
        setBusyId(machine.id);

        // router.patch signature-nya (url, data, options) -- beda dari
        // router.delete(url, options). Options-nya wajib di argumen ketiga,
        // kalau tidak onFinish malah terkirim sebagai data dan tombol tersangkut
        // di status "Memulihkan...".
        router.patch(route('machines.restore', machine.id), {}, {
            preserveScroll: true,
            onFinish: () => setBusyId(null),
        });
    };

    const trashedList = trashed ?? [];

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                <div className="flex items-center justify-between mb-5">
                    <div>
                        <h1 className="text-xl font-bold">Kelola Mesin</h1>
                        <p className="text-sm text-neu-sub">
                            {machines.length} mesin terdaftar
                            {visible.length !== machines.length && ` · ${visible.length} ditampilkan`}
                        </p>
                    </div>
                </div>

                {/* Add form */}
                <NeuCard className="mb-5">
                    <h2 className="font-bold text-sm mb-3">Tambah Mesin</h2>
                    <form onSubmit={submitCreate} className="grid grid-cols-2 gap-3">
                        <MachineFields
                            data={createForm.data}
                            setData={createForm.setData}
                            clearErrors={createForm.clearErrors}
                            errors={createForm.errors}
                            types={types}
                            templates={templates}
                            categories={categories}
                            subCategories={subCategories}
                            onSubCategoryChange={applySubCategory(createForm.setData)}
                            idPrefix="tambah"
                        />
                        <div className="col-span-2">
                            <NeuButton type="submit" variant="primary" disabled={createForm.processing}>
                                {createForm.processing ? 'Menambahkan...' : '+ Tambah Mesin'}
                            </NeuButton>
                        </div>
                    </form>
                </NeuCard>

                {/* Search + filter area */}
                <div className="mb-4">
                    <input
                        type="search"
                        placeholder="Cari kode, nama, atau lokasi mesin…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        className="neu-input"
                        aria-label="Cari mesin"
                    />
                </div>

                <div
                    className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-4"
                    role="group"
                    aria-label="Filter area"
                >
                    <NeuChip
                        active={sub === 'all'}
                        onClick={() => setSub('all')}
                        aria-label="Semua area"
                    >
                        Semua Area
                    </NeuChip>
                    {(subCategories ?? []).map((s) => (
                        <NeuChip
                            key={s}
                            active={sub === s}
                            onClick={() => setSub(s)}
                            aria-label={`Area ${s}`}
                        >
                            {s}
                        </NeuChip>
                    ))}
                </div>

                {/* Machine list */}
                <div className="grid gap-0 lg:grid-cols-2 lg:gap-x-5">
                    {visible.length > 0 ? visible.map((machine) => (
                        <div key={machine.id} data-machine-card={machine.id} className="mb-4">
                            {editingId === machine.id ? (
                                <div className="p-3.5 rounded-[20px] shadow-neu-in">
                                    <h3 className="font-bold text-sm mb-3">Ubah Mesin</h3>
                                    <form onSubmit={submitEdit} className="grid grid-cols-2 gap-3">
                                        <MachineFields
                                            data={editForm.data}
                                            setData={editForm.setData}
                                            clearErrors={editForm.clearErrors}
                                            errors={editForm.errors}
                                            types={types}
                                            templates={templates}
                                            categories={categories}
                                            subCategories={subCategories}
                                            onSubCategoryChange={applySubCategory(editForm.setData)}
                                            idPrefix={`ubah-${machine.id}`}
                                        />
                                        <div className="col-span-2 flex gap-2">
                                            <NeuButton type="submit" variant="primary" disabled={editForm.processing}>
                                                {editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                            </NeuButton>
                                            <NeuButton onClick={cancelEdit} disabled={editForm.processing}>
                                                Batal
                                            </NeuButton>
                                        </div>
                                    </form>
                                </div>
                            ) : (
                                <div className="p-3.5 rounded-[20px] shadow-neu-up">
                                    <div className="flex items-center gap-3.5">
                                        <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">
                                            {machine.sub_category ?? machine.code}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <b className="block text-sm truncate">{machine.name}</b>
                                            <span className="block text-xs text-neu-sub truncate">
                                                {machine.code} · {typeLabels[machine.type] ?? machine.type} · Minggu {machine.week_group}
                                            </span>
                                            <span className="block text-xs text-neu-sub truncate">
                                                {machine.location ?? 'Tanpa lokasi'}
                                                {machine.category && ` · ${machine.category}`}
                                                {machine.template_name && ` · ${machine.template_name}`}
                                            </span>
                                        </div>
                                        <NeuPill variant={machine.is_active ? 'done' : 'todo'}>
                                            {machine.is_active ? 'Aktif' : 'Nonaktif'}
                                        </NeuPill>
                                    </div>

                                    {confirmingId === machine.id ? (
                                        <div className="mt-3 neu-inset rounded-neu-sm px-3.5 py-3 flex flex-wrap items-center gap-2">
                                            <p className="text-xs font-bold text-neu-bad flex-1 min-w-[14rem]">
                                                Hapus mesin ini dari daftar? Riwayat PM tetap tersimpan dan bisa dipulihkan.
                                            </p>
                                            <NeuButton
                                                onClick={() => destroyMachine(machine)}
                                                disabled={busyId === machine.id}
                                                className="!text-neu-bad"
                                            >
                                                {busyId === machine.id ? 'Menghapus...' : 'Ya, hapus'}
                                            </NeuButton>
                                            <NeuButton onClick={() => setConfirmingId(null)} disabled={busyId === machine.id}>
                                                Batal
                                            </NeuButton>
                                        </div>
                                    ) : (
                                        <div className="mt-3 flex items-center gap-2 justify-end">
                                            <Link
                                                href={route('machines.history', { machine: machine.id })}
                                                className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs"
                                            >
                                                Riwayat
                                            </Link>
                                            <NeuButton
                                                onClick={() => startEdit(machine)}
                                                className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                            >
                                                Ubah
                                            </NeuButton>
                                            <NeuButton
                                                onClick={() => askDelete(machine)}
                                                className="!min-h-[36px] !px-3 !py-1.5 text-xs !text-neu-bad"
                                            >
                                                Hapus
                                            </NeuButton>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )) : (
                        <p className="text-neu-sub text-center py-6 col-span-2">Tidak ada mesin yang cocok.</p>
                    )}
                </div>

                {/* Soft delete disembunyikan dari daftar aktif, jadi mesin yang
                    sengaja dilepas harus tetap bisa ditemukan dan dikembalikan
                    dari halaman ini. */}
                {trashedList.length > 0 && (
                    <NeuCard className="mt-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-bold text-sm">Mesin Dihapus</h2>
                                <p className="text-xs text-neu-sub">
                                    {trashedList.length} mesin disembunyikan dari daftar aktif.
                                </p>
                            </div>
                            <NeuButton
                                onClick={() => setShowTrashed((v) => !v)}
                                className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                            >
                                {showTrashed ? 'Sembunyikan' : 'Lihat & Pulihkan'}
                            </NeuButton>
                        </div>

                        {showTrashed && (
                            <ul className="mt-3 space-y-2">
                                {trashedList.map((machine) => (
                                    <li
                                        key={machine.id}
                                        data-trashed-machine={machine.id}
                                        className="neu-inset rounded-neu-sm px-3.5 py-2.5 flex items-center gap-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <b className="block text-sm truncate">{machine.name}</b>
                                            <span className="block text-xs text-neu-sub truncate">
                                                {machine.code} · dihapus {machine.deleted_at}
                                            </span>
                                        </div>
                                        <NeuButton
                                            onClick={() => restoreMachine(machine)}
                                            disabled={busyId === machine.id}
                                            className="!min-h-[36px] !px-3 !py-1.5 text-xs"
                                        >
                                            {busyId === machine.id ? 'Memulihkan...' : 'Pulihkan'}
                                        </NeuButton>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </NeuCard>
                )}
            </div>
        </AuthenticatedLayout>
    );
}