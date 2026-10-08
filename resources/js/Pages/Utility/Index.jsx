import { useMemo, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import MachineFields from '@/Components/MachineFields';

const EMPTY_UTILITY = {
    code: '',
    name: '',
    location: '',
    category: '',
    sub_category: '',
    type: 'utility',
    week_group: 1,
    template_id: '',
    is_active: 1,
};

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
    type: machine.type ?? 'utility',
    week_group: machine.week_group ?? 1,
    template_id: machine.template_id ?? '',
    is_active: machine.is_active ? 1 : 0,
});

const groupComponents = (components) => {
    const sections = [];
    const seen = new Map();
    for (const component of components ?? []) {
        if (!seen.has(component.category)) {
            seen.set(component.category, sections.length);
            sections.push({ category: component.category, items: [] });
        }
        sections[seen.get(component.category)].items.push(component.name);
    }
    return sections;
};

export default function UtilityIndex({ auth, utilities = [], types = [], templates = [], categories = [], subCategories = [], trashed = [], can = {} }) {
    const createForm = useForm({ ...EMPTY_UTILITY });
    const editForm = useForm({ ...EMPTY_UTILITY });

    const [q, setQ] = useState('');
    const [sub, setSub] = useState('all');
    const [expandedId, setExpandedId] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [confirmingId, setConfirmingId] = useState(null);
    const [busyId, setBusyId] = useState(null);
    const [showTrashed, setShowTrashed] = useState(false);

    const typeLabels = useMemo(() => Object.fromEntries((types ?? []).map((t) => [t.value, t.label])), [types]);

    const visible = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return utilities.filter((u) => {
            if (sub !== 'all' && u.sub_category !== sub) return false;
            if (!needle) return true;
            return [u.code, u.name, u.location, u.sub_category]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(needle));
        });
    }, [utilities, q, sub]);

    const submitCreate = (e) => {
        e.preventDefault();
        createForm.post(route('machines.store'), {
            preserveScroll: true,
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

    const restore = (machine) => {
        setBusyId(machine.id);
        router.patch(route('machines.restore', machine.id), {}, {
            preserveScroll: true,
            onFinish: () => setBusyId(null),
        });
    };

    const trashedList = useMemo(() => trashed, [trashed]);

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="flex items-center justify-between mb-5">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold">Utility</h1>
                        <p className="text-sm text-neu-sub">{utilities.length} unit utility terdaftar{visible.length !== utilities.length && ` · ${visible.length} ditampilkan`}</p>
                    </div>
                    <NeuButton onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })} variant="ghost">Kembali ke atas</NeuButton>
                </div>

                <div className="mb-4">
                    <input type="search" placeholder="Cari kode, nama, atau lokasi unit…" value={q} onChange={(e) => setQ(e.target.value)} className="neu-input" aria-label="Cari unit utility" />
                </div>

                <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-4" role="group" aria-label="Filter area">
                    <NeuChip active={sub === 'all'} onClick={() => setSub('all')} aria-label="Semua area">Semua Area</NeuChip>
                    {(subCategories ?? []).map((s) => (
                        <NeuChip key={s} active={sub === s} onClick={() => setSub(s)} aria-label={`Area ${s}`}>{s}</NeuChip>
                    ))}
                </div>

                {auth.can?.create && (
                    <NeuCard className="mb-4">
                        <h2 className="text-sm font-semibold mb-3">Tambah Utility</h2>
                        <form className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3" onSubmit={submitCreate}>
                            <MachineFields form={createForm} types={types} templates={templates} categories={categories} subCategories={subCategories} applySubCategory={applySubCategory} />
                            <div className="sm:col-span-2 flex justify-end">
                                <NeuButton type="submit" disabled={createForm.processing}>Simpan Utility</NeuButton>
                            </div>
                        </form>
                    </NeuCard>
                )}

                <div className="grid grid-cols-1 gap-0 sm:grid-cols-2 sm:gap-x-4 xl:grid-cols-3 xl:gap-x-5">
                    {visible.length > 0 ? visible.map((unit) => {
                        const expanded = expandedId === unit.id;
                        const sections = groupComponents(unit.components);
                        const isEditing = editingId === unit.id;
                        const isConfirming = confirmingId === unit.id;

                        return (
                            <div key={unit.id} data-utility-card={unit.id} className="mb-4">
                                <NeuCard className={`transition-all ${expanded ? 'shadow-neu-in' : ''}`}>
                                    <div className="flex items-center gap-3.5">
                                        <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">{unit.sub_category ?? unit.code}</span>
                                        <div className="min-w-0 flex-1">
                                            <b className="block text-sm truncate">{unit.name}</b>
                                            <span className="block text-xs text-neu-sub truncate">{unit.code} · {typeLabels[unit.type] ?? unit.type} · Minggu {unit.week_group}</span>
                                            <span className="block text-xs text-neu-sub truncate">{unit.location ?? 'Tanpa lokasi'}{unit.category && ` · ${unit.category}`}{unit.component_count > 0 && ` · ${unit.component_count} komponen`}</span>
                                        </div>
                                        <NeuPill variant={unit.is_active ? 'done' : 'todo'}>{unit.is_active ? 'Aktif' : 'Nonaktif'}</NeuPill>
                                    </div>

                                    {expanded && (
                                        <div className="mt-3 space-y-2.5">
                                            {sections.length > 0 ? sections.map((section) => (
                                                <div key={section.category}>
                                                    <p className="text-[11px] font-bold uppercase tracking-wide text-neu-sub mb-1">{section.category}</p>
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {section.items.map((name) => (
                                                            <span key={name} className="neu-inset rounded-neu-sm px-2.5 py-1 text-xs">{name}</span>
                                                        ))}
                                                    </div>
                                                </div>
                                            )) : (
                                                <p className="text-xs text-neu-sub">Belum ada komponen maintenance.</p>
                                            )}
                                        </div>
                                    )}

                                    <div className="mt-3 flex flex-wrap items-center gap-2 justify-end">
                                        {can.fill && (
                                            <Link href={route('machines.pm.create', { machine: unit.id })} className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs">Isi PM</Link>
                                        )}
                                        {can.history && (
                                            <Link href={route('machines.history', { machine: unit.id })} className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs">Riwayat</Link>
                                        )}
                                        {auth.can?.update && (
                                            <NeuButton size="sm" variant="ghost" onClick={() => startEdit(unit)}>Ubah</NeuButton>
                                        )}
                                        {auth.can?.delete && (
                                            <NeuButton size="sm" variant="ghost" onClick={() => askDelete(unit)}>Hapus</NeuButton>
                                        )}
                                        <NeuChip active={expanded} onClick={() => setExpandedId(expanded ? null : unit.id)} aria-label="Lihat komponen maintenance">
                                            {expanded ? 'Tutup' : `Komponen${unit.component_count > 0 ? ` (${unit.component_count})` : ''}`}
                                        </NeuChip>
                                    </div>

                                    {isEditing && (
                                        <div className="mt-4 pt-4 border-t border-neu-border/70">
                                            <h3 className="text-sm font-semibold mb-3">Ubah Utility</h3>
                                            <form className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3" onSubmit={submitEdit}>
                                                <MachineFields form={editForm} types={types} templates={templates} categories={categories} subCategories={subCategories} applySubCategory={applySubCategory} isEditing />
                                                <div className="sm:col-span-2 flex flex-wrap gap-2 justify-end">
                                                    <NeuButton type="button" variant="ghost" onClick={cancelEdit}>Batal</NeuButton>
                                                    <NeuButton type="submit" disabled={editForm.processing}>Simpan Perubahan</NeuButton>
                                                </div>
                                            </form>
                                        </div>
                                    )}

                                    {isConfirming && (
                                        <div className="mt-4 pt-4 border-t border-neu-border/70">
                                            <p className="text-sm mb-3">Yakin ingin menghapus utility <b>{unit.code}</b> — {unit.name}?</p>
                                            <div className="flex gap-2 justify-end">
                                                <NeuButton type="button" variant="ghost" onClick={cancelDelete}>Batal</NeuButton>
                                                <NeuButton type="button" variant="bad" onClick={() => confirmDelete(unit)} disabled={busyId === unit.id}>Hapus</NeuButton>
                                            </div>
                                        </div>
                                    )}
                                </NeuCard>
                            </div>
                        );
                    }) : (
                        <p className="text-neu-sub text-center py-6 sm:col-span-2 xl:col-span-3">Tidak ada unit utility yang cocok.</p>
                    )}
                </div>

                {trashedList.length > 0 && (
                    <div className="mt-6">
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-sm font-semibold">Utility yang dihapus</h2>
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
                                                <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">{machine.code?.slice(-4) ?? '—'}</span>
                                                <div className="min-w-0 flex-1">
                                                    <b className="block text-sm truncate">{machine.name}</b>
                                                    <span className="block text-xs text-neu-sub truncate">{machine.code} · Dihapus {machine.deleted_at}</span>
                                                </div>
                                                {auth.can?.restore && (
                                                    <NeuButton type="button" size="sm" variant="ghost" onClick={() => restore(machine)} disabled={busyId === machine.id}>Pulihkan</NeuButton>
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
        </AuthenticatedLayout>
    );
}
