import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';
import MachineFields from '@/Components/MachineFields';
import StatusDot from '@/Components/StatusDot';
import ComponentAssignModal from '@/Components/ComponentAssignModal';

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

/** Konvensi sub-kategori "W.7" sudah memuat nomor minggu, jadi angka yang
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

/** Kelompokkan komponen per kategori supaya tampilannya sama dengan halaman
 *  Utility (judul kategori + daftar komponen beserta standarnya). */
const groupComponents = (components) => {
    const sections = [];
    const seen = new Map();

    for (const component of components ?? []) {
        if (!seen.has(component.category)) {
            seen.set(component.category, sections.length);
            sections.push({ category: component.category, items: [] });
        }

        sections[seen.get(component.category)].items.push(component);
    }

    return sections;
};

export default function Machines({ auth, machines = [], allComponents = [], types = [], templates = [], locations = [], categories = [], subCategories = [], trashed = [], can = {} }) {
    const createForm = useForm({ ...EMPTY_MACHINE });
    const editForm = useForm({ ...EMPTY_MACHINE });
    // Unit yang sedang disunting komponennya (modal Edit Komponen).
    const [componentsTarget, setComponentsTarget] = useState(null);

    // Daftar mesin 120+ baris, jadi pencarian dan filter area dijalankan di
    // sisi klien supaya tidak perlu reload tiap ketikan.
    const [q, setQ] = useState('');
    const [sub, setSub] = useState('all');
    const [showCreate, setShowCreate] = useState(false);
    const [expandedId, setExpandedId] = useState(null);

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
            onSuccess: () => {
                createForm.reset();
                setShowCreate(false);
            },
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

    const confirmDelete = (machine) => {
        setBusyId(machine.id);

        router.delete(route('machines.destroy', machine.id), {
            preserveScroll: true,
            onFinish: () => {
                setBusyId(null);
                setConfirmingId(null);
            },
        });
    };

    const cancelDelete = () => {
        setConfirmingId(null);
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
            <div className="page-container">
                <div className="flex items-center justify-between gap-3 mb-5">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold">Machines</h1>
                        <p className="text-sm text-neu-sub">
                            {machines.length} mesin terdaftar
                            {visible.length !== machines.length && ` · ${visible.length} ditampilkan`}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        {can.manage && (
                            <NeuButton
                                variant="primary"
                                onClick={() => {
                                    if (showCreate) createForm.reset();
                                    setShowCreate((v) => !v);
                                }}
                            >
                                {showCreate ? 'Tutup' : 'Tambah Mesin'}
                            </NeuButton>
                        )}
                    </div>
                </div>

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
                    <NeuChip active={sub === 'all'} onClick={() => setSub('all')} aria-label="Semua area">
                        SemuaKelola Mesin Area
                    </NeuChip>
                    {(subCategories ?? []).map((s) => (
                        <NeuChip key={s} active={sub === s} onClick={() => setSub(s)} aria-label={`Area ${s}`}>
                            {s}
                        </NeuChip>
                    ))}
                </div>

                {can.manage && showCreate && (
                    <NeuCard className="mb-4">
                        <h2 className="text-sm font-semibold mb-3">Tambah Mesin</h2>
                        <form className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3" onSubmit={submitCreate}>
                            <MachineFields
                                data={createForm.data}
                                setData={createForm.setData}
                                clearErrors={createForm.clearErrors}
                                errors={createForm.errors}
                                types={types}
                                templates={templates}
                                locations={locations}
                                categories={categories}
                                subCategories={subCategories}
                                onSubCategoryChange={applySubCategory(createForm.setData)}
                                idPrefix="mesin-baru"
                            />
                            <div className="sm:col-span-2 flex flex-wrap justify-end gap-2">
                                <NeuButton
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        createForm.reset();
                                        setShowCreate(false);
                                    }}
                                >
                                    Batal
                                </NeuButton>
                                <NeuButton type="submit" size="sm" disabled={createForm.processing}>
                                    {createForm.processing ? 'Menyimpan...' : 'Simpan Mesin'}
                                </NeuButton>
                            </div>
                        </form>
                    </NeuCard>
                )}

                <div className="grid grid-cols-1 gap-0 sm:grid-cols-2 sm:gap-x-4 xl:grid-cols-3 xl:gap-x-5">
                    {visible.length > 0 ? visible.map((machine) => {
                        const expanded = expandedId === machine.id;
                        const sections = groupComponents(machine.components);
                        const isEditing = editingId === machine.id;
                        const isConfirming = confirmingId === machine.id;

                        return (
                            <div key={machine.id} data-machine-card={machine.id} className="mb-4">
                                <NeuCard className={`transition-all ${expanded ? 'shadow-neu-in' : ''}`}>
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
                                        <StatusDot active={machine.is_active} className="mx-2" />
                                    </div>

                                    {expanded && (
                                        <div className="mt-3 space-y-2.5">
                                            {sections.length > 0 ? sections.map((section) => (
                                                <div key={section.category}>
                                                    <p className="text-[11px] font-bold uppercase tracking-wide text-neu-sub mb-1">{section.category}</p>
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {section.items.map((component) => {
                                                            const standard = component.standard || component.spec;
                                                            return (
                                                                <span key={component.id ?? component.name} className="neu-inset rounded-neu-sm px-2.5 py-1 text-xs">
                                                                    {component.name}
                                                                    {standard && <span className="text-neu-sub"> · {standard}</span>}
                                                                </span>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            )) : (
                                                <p className="text-xs text-neu-sub">Belum ada komponen maintenance.</p>
                                            )}
                                            {can.manage && (
                                                <div className="flex justify-end">
                                                    <NeuButton size="sm" variant="ghost" onClick={() => setComponentsTarget(machine)}>
                                                        Edit Komponen
                                                    </NeuButton>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    <div className="mt-3 flex flex-wrap items-center gap-2 justify-end">
                                        {can.fill && (
                                            <NeuButton href={route('machines.pm.create', { machine: machine.id })} size="sm">Isi PM</NeuButton>
                                        )}
                                        {can.history && (
                                            <NeuButton href={route('machines.history', { machine: machine.id })} size="sm">Riwayat</NeuButton>
                                        )}
                                        {can.manage && (
                                            <NeuButton size="sm" variant="ghost" onClick={() => startEdit(machine)}>Ubah</NeuButton>
                                        )}
                                        {can.manage && (
                                            <NeuButton size="sm" variant="ghost" className="!text-neu-bad" onClick={() => askDelete(machine)}>Hapus</NeuButton>
                                        )}
                                        <NeuChip size="sm" active={expanded} onClick={() => setExpandedId(expanded ? null : machine.id)} aria-label="Lihat komponen maintenance">
                                            {expanded ? 'Tutup' : `Komponen${machine.component_count > 0 ? ` (${machine.component_count})` : ''}`}
                                        </NeuChip>
                                    </div>

                                    {isEditing && (
                                        <div className="mt-4 pt-4 border-t border-neu-border/70">
                                            <h3 className="text-sm font-semibold mb-3">Ubah Mesin</h3>
                                            <form className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3" onSubmit={submitEdit}>
                                                <MachineFields
                                                    data={editForm.data}
                                                    setData={editForm.setData}
                                                    clearErrors={editForm.clearErrors}
                                                    errors={editForm.errors}
                                                    types={types}
                                                    templates={templates}
                                                    locations={locations}
                                                    categories={categories}
                                                    subCategories={subCategories}
                                                    onSubCategoryChange={applySubCategory(editForm.setData)}
                                                    idPrefix={`mesin-ubah-${machine.id}`}
                                                    originalTemplateId={machine.template_id ?? null}
                                                />
                                                <div className="sm:col-span-2 flex flex-wrap gap-2 justify-end">
                                                    <NeuButton type="button" size="sm" variant="ghost" onClick={cancelEdit}>Batal</NeuButton>
                                                    <NeuButton type="submit" size="sm" disabled={editForm.processing}>
                                                        {editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                                    </NeuButton>
                                                </div>
                                            </form>
                                        </div>
                                    )}

                                    {isConfirming && (
                                        <div className="mt-4 pt-4 border-t border-neu-border/70">
                                            <p className="text-sm mb-3">Yakin ingin menghapus mesin <b>{machine.code}</b> — {machine.name}?</p>
                                            <div className="flex gap-2 justify-end">
                                                <NeuButton type="button" size="sm" variant="ghost" onClick={cancelDelete}>Batal</NeuButton>
                                                <NeuButton type="button" size="sm" variant="bad" className="!text-neu-bad" onClick={() => confirmDelete(machine)} disabled={busyId === machine.id}>Hapus</NeuButton>
                                            </div>
                                        </div>
                                    )}
                                </NeuCard>
                            </div>
                        );
                    }) : (
                        <p className="text-neu-sub text-center py-6 sm:col-span-2 xl:col-span-3">Tidak ada mesin yang cocok.</p>
                    )}
                </div>

                {trashedList.length > 0 && (
                    <div className="mt-6">
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-sm font-semibold">Mesin yang dihapus</h2>
                            <NeuButton type="button" variant="ghost" size="sm" onClick={() => setShowTrashed((v) => !v)}>
                                {showTrashed ? 'Sembunyikan' : `Tampilkan (${trashedList.length})`}
                            </NeuButton>
                        </div>
                        {showTrashed && (
                            <div className="grid grid-cols-1 gap-0 sm:grid-cols-2 sm:gap-x-4 xl:grid-cols-3 xl:gap-x-5">
                                {trashedList.map((machine) => (
                                    <div key={machine.id} className="mb-4">
                                        <NeuCard className="opacity-80">
                                            <div className="flex items-start gap-3.5">
                                                <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">
                                                    {machine.code?.slice(-4) ?? '—'}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <b className="block text-sm truncate">{machine.name}</b>
                                                    <span className="block text-xs text-neu-sub truncate">{machine.code} · Dihapus {machine.deleted_at}</span>
                                                </div>
                                                {can.manage && (
                                                    <NeuButton type="button" size="sm" variant="ghost" onClick={() => restoreMachine(machine)} disabled={busyId === machine.id}>
                                                        {busyId === machine.id ? 'Memulihkan...' : 'Pulihkan'}
                                                    </NeuButton>
                                                )}
                                            </div>
                                        </NeuCard>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>

            <ComponentAssignModal
                show={Boolean(componentsTarget)}
                onClose={() => setComponentsTarget(null)}
                unit={componentsTarget}
                allComponents={allComponents}
                action={componentsTarget ? route('machines.components.update', componentsTarget.id) : '#'}
            />
        </AuthenticatedLayout>
    );
}
