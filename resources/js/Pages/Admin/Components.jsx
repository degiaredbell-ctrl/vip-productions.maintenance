import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';

const EMPTY_COMPONENT = { name: '', category: '', spec: '' };

export default function AdminComponents({ auth, components = [], machines = [], categories = [] }) {
    const [tab, setTab] = useState('master');
    const [q, setQ] = useState('');
    const [editingId, setEditingId] = useState(null);
    const [confirmingId, setConfirmingId] = useState(null);
    const [busyId, setBusyId] = useState(null);

    const createForm = useForm({ ...EMPTY_COMPONENT });
    const editForm = useForm({ ...EMPTY_COMPONENT });

    // Penugasan per unit.
    const [machineId, setMachineId] = useState('');
    const [selected, setSelected] = useState([]);
    const [assignSearch, setAssignSearch] = useState('');

    const mesinList = useMemo(() => machines.filter((m) => !m.is_utility), [machines]);
    const utilityList = useMemo(() => machines.filter((m) => m.is_utility), [machines]);

    const selectedMachine = useMemo(
        () => machines.find((m) => String(m.id) === String(machineId)) ?? null,
        [machines, machineId],
    );

    const filteredComponents = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return components;
        return components.filter((c) => [c.name, c.category, c.spec]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(needle)));
    }, [components, q]);

    // Dikelompokkan per kategori supaya daftar panjang tetap terbaca.
    const groupByCategory = (list) => {
        const map = new Map();
        for (const item of list) {
            if (!map.has(item.category)) map.set(item.category, []);
            map.get(item.category).push(item);
        }
        return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]));
    };

    const masterGroups = useMemo(() => groupByCategory(filteredComponents), [filteredComponents]);

    const assignComponents = useMemo(() => {
        const needle = assignSearch.trim().toLowerCase();
        if (!needle) return components;
        return components.filter((c) => [c.name, c.category, c.spec]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(needle)));
    }, [components, assignSearch]);

    const assignGroups = useMemo(() => groupByCategory(assignComponents), [assignComponents]);

    const submitCreate = (e) => {
        e.preventDefault();
        createForm.post(route('admin.components.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const startEdit = (component) => {
        setConfirmingId(null);
        editForm.clearErrors();
        editForm.setData({ name: component.name, category: component.category, spec: component.spec ?? '' });
        setEditingId(component.id);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        editForm.put(route('admin.components.update', editingId), {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.clearErrors();
    };

    const confirmDelete = (component) => {
        setBusyId(component.id);
        router.delete(route('admin.components.destroy', component.id), {
            preserveScroll: true,
            onFinish: () => {
                setBusyId(null);
                setConfirmingId(null);
            },
        });
    };

    const chooseMachine = (id) => {
        setMachineId(id);
        const machine = machines.find((m) => String(m.id) === String(id));
        setSelected(machine ? [...machine.component_ids] : []);
    };

    const toggleComponent = (id) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const toggleCategory = (group) => {
        const ids = group[1].map((c) => c.id);
        const allSelected = ids.every((id) => selected.includes(id));
        setSelected((prev) => (allSelected
            ? prev.filter((id) => !ids.includes(id))
            : [...new Set([...prev, ...ids])]));
    };

    const saveAssignment = () => {
        if (!machineId) return;
        router.post(route('admin.components.assign'), {
            machine_id: machineId,
            component_ids: selected,
        }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Komponen / Parts</h1>
                    <p className="text-sm text-neu-sub">
                        {components.length} komponen terdaftar pada {machines.length} unit Mesin &amp; Utility
                    </p>
                </div>

                <div className="flex flex-wrap gap-2 mb-4">
                    <NeuChip active={tab === 'master'} onClick={() => setTab('master')}>Daftar Komponen</NeuChip>
                    <NeuChip active={tab === 'assign'} onClick={() => setTab('assign')}>Penugasan ke Unit</NeuChip>
                </div>

                {tab === 'master' && (
                    <>
                        <NeuCard className="mb-4">
                            <h2 className="text-sm font-semibold mb-3">Tambah Komponen</h2>
                            <form className="grid grid-cols-1 sm:grid-cols-[1.2fr_1fr_1fr_auto] gap-2 items-start" onSubmit={submitCreate}>
                                <div>
                                    <input
                                        type="text"
                                        placeholder="Nama komponen (mis. Power Input)"
                                        value={createForm.data.name}
                                        onChange={(e) => createForm.setData('name', e.target.value)}
                                        className="neu-input !min-h-[44px]"
                                        required
                                    />
                                    {createForm.errors.name && <p className="mt-1 text-[11px] font-bold text-neu-bad">{createForm.errors.name}</p>}
                                </div>
                                <div>
                                    <input
                                        type="text"
                                        placeholder="Kategori"
                                        list="component-categories"
                                        value={createForm.data.category}
                                        onChange={(e) => createForm.setData('category', e.target.value)}
                                        className="neu-input !min-h-[44px]"
                                        required
                                    />
                                    {createForm.errors.category && <p className="mt-1 text-[11px] font-bold text-neu-bad">{createForm.errors.category}</p>}
                                </div>
                                <div>
                                    <input
                                        type="text"
                                        placeholder="Spesifikasi (opsional)"
                                        value={createForm.data.spec}
                                        onChange={(e) => createForm.setData('spec', e.target.value)}
                                        className="neu-input !min-h-[44px]"
                                    />
                                    {createForm.errors.spec && <p className="mt-1 text-[11px] font-bold text-neu-bad">{createForm.errors.spec}</p>}
                                </div>
                                <NeuButton type="submit" variant="primary" disabled={createForm.processing}>
                                    {createForm.processing ? 'Menyimpan...' : 'Tambah'}
                                </NeuButton>
                            </form>
                            <datalist id="component-categories">
                                {categories.map((c) => <option key={c} value={c} />)}
                            </datalist>
                        </NeuCard>

                        <div className="mb-4">
                            <input
                                type="search"
                                placeholder="Cari nama, kategori, atau spesifikasi komponen…"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                className="neu-input"
                                aria-label="Cari komponen"
                            />
                        </div>

                        <div className="space-y-4">
                            {masterGroups.map((group) => (
                                <div key={group[0]}>
                                    <p className="text-[11px] font-bold uppercase tracking-wide text-neu-sub mb-2">{group[0]} · {group[1].length}</p>
                                    <div className="space-y-2">
                                        {group[1].map((component) => (
                                            <NeuCard key={component.id} className="!p-3.5">
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="min-w-0 flex-1">
                                                        <b className="text-sm">{component.name}</b>
                                                        {component.spec && <span className="text-xs text-neu-sub ml-2">· {component.spec}</span>}
                                                        <span className="block text-xs text-neu-sub mt-0.5">
                                                            Dipakai di {component.machines_count} unit
                                                        </span>
                                                    </div>
                                                    <NeuPill variant={component.machines_count > 0 ? 'done' : 'todo'}>
                                                        {component.machines_count > 0 ? 'Terpakai' : 'Belum dipakai'}
                                                    </NeuPill>
                                                    <div className="flex gap-2">
                                                        <NeuButton size="sm" variant="ghost" onClick={() => startEdit(component)}>Ubah</NeuButton>
                                                        <NeuButton
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => { setEditingId(null); setConfirmingId(component.id); }}
                                                        >
                                                            Hapus
                                                        </NeuButton>
                                                    </div>
                                                </div>

                                                {editingId === component.id && (
                                                    <form className="mt-3 pt-3 border-t border-neu-sub/20 grid grid-cols-1 sm:grid-cols-[1.2fr_1fr_1fr_auto] gap-2 items-start" onSubmit={submitEdit}>
                                                        <input
                                                            type="text"
                                                            value={editForm.data.name}
                                                            onChange={(e) => editForm.setData('name', e.target.value)}
                                                            className="neu-input !min-h-[40px] !py-2 text-sm"
                                                            required
                                                        />
                                                        <input
                                                            type="text"
                                                            list="component-categories"
                                                            value={editForm.data.category}
                                                            onChange={(e) => editForm.setData('category', e.target.value)}
                                                            className="neu-input !min-h-[40px] !py-2 text-sm"
                                                            required
                                                        />
                                                        <input
                                                            type="text"
                                                            placeholder="Spesifikasi"
                                                            value={editForm.data.spec}
                                                            onChange={(e) => editForm.setData('spec', e.target.value)}
                                                            className="neu-input !min-h-[40px] !py-2 text-sm"
                                                        />
                                                        <div className="flex gap-2">
                                                            <NeuButton type="button" size="sm" variant="ghost" onClick={cancelEdit}>Batal</NeuButton>
                                                            <NeuButton type="submit" size="sm" disabled={editForm.processing}>Simpan</NeuButton>
                                                        </div>
                                                    </form>
                                                )}

                                                {confirmingId === component.id && (
                                                    <div className="mt-3 pt-3 border-t border-neu-sub/20 flex flex-wrap items-center justify-between gap-2">
                                                        <p className="text-sm">
                                                            Hapus <b>{component.name}</b>? Komponen akan dilepas dari {component.machines_count} unit.
                                                        </p>
                                                        <div className="flex gap-2">
                                                            <NeuButton type="button" size="sm" variant="ghost" onClick={() => setConfirmingId(null)}>Batal</NeuButton>
                                                            <NeuButton
                                                                type="button"
                                                                size="sm"
                                                                variant="bad"
                                                                onClick={() => confirmDelete(component)}
                                                                disabled={busyId === component.id}
                                                            >
                                                                Hapus
                                                            </NeuButton>
                                                        </div>
                                                    </div>
                                                )}
                                            </NeuCard>
                                        ))}
                                    </div>
                                </div>
                            ))}

                            {masterGroups.length === 0 && (
                                <p className="text-neu-sub text-center py-6">Belum ada komponen yang cocok.</p>
                            )}
                        </div>
                    </>
                )}

                {tab === 'assign' && (
                    <>
                        <NeuCard className="mb-4">
                            <h2 className="text-sm font-semibold mb-3">Pilih Unit</h2>
                            <select
                                value={machineId}
                                onChange={(e) => chooseMachine(e.target.value)}
                                className="neu-input"
                                aria-label="Pilih unit"
                            >
                                <option value="">— Pilih Mesin / Utility —</option>
                                <optgroup label="Mesin">
                                    {mesinList.map((m) => (
                                        <option key={m.id} value={m.id}>{m.code} — {m.name}</option>
                                    ))}
                                </optgroup>
                                <optgroup label="Utility">
                                    {utilityList.map((m) => (
                                        <option key={m.id} value={m.id}>{m.code} — {m.name}</option>
                                    ))}
                                </optgroup>
                            </select>

                            {selectedMachine && (
                                <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-neu-sub">
                                    <NeuPill variant="progress">{selectedMachine.type_label}</NeuPill>
                                    <span>{selectedMachine.is_utility ? 'Utility' : 'Mesin'} · <b className="text-neu-text">{selected}</b> komponen dipilih</span>
                                </div>
                            )}
                        </NeuCard>

                        {!selectedMachine && (
                            <p className="text-neu-sub text-center py-6">Pilih unit dulu untuk mengatur komponennya.</p>
                        )}

                        {selectedMachine && (
                            <>
                                <div className="mb-4">
                                    <input
                                        type="search"
                                        placeholder="Cari komponen…"
                                        value={assignSearch}
                                        onChange={(e) => setAssignSearch(e.target.value)}
                                        className="neu-input"
                                        aria-label="Cari komponen"
                                    />
                                </div>

                                <div className="space-y-4 mb-4">
                                    {assignGroups.map((group) => {
                                        const ids = group[1].map((c) => c.id);
                                        const allSelected = ids.length > 0 && ids.every((id) => selected.includes(id));
                                        return (
                                            <div key={group[0]}>
                                                <div className="flex items-center justify-between mb-2">
                                                    <p className="text-[11px] font-bold uppercase tracking-wide text-neu-sub">
                                                        {group[0]} · {ids.filter((id) => selected.includes(id)).length}/{ids.length}
                                                    </p>
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleCategory(group)}
                                                        className="text-[11px] font-semibold text-neu-accent hover:underline"
                                                    >
                                                        {allSelected ? 'Kosongkan' : 'Pilih semua'}
                                                    </button>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {group[1].map((component) => {
                                                        const checked = selected.includes(component.id);
                                                        return (
                                                            <label
                                                                key={component.id}
                                                                className={`neu-chip cursor-pointer !normal-case ${checked ? 'neu-chip-active' : ''}`}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    className="sr-only"
                                                                    checked={checked}
                                                                    onChange={() => toggleComponent(component.id)}
                                                                />
                                                                {component.name}
                                                                {component.spec && <span className="opacity-70 font-normal">· {component.spec}</span>}
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        );
                                    })}

                                    {assignGroups.length === 0 && (
                                        <p className="text-neu-sub text-center py-6">Belum ada komponen. Tambahkan dulu di tab Daftar Komponen.</p>
                                    )}
                                </div>

                                <div className="sticky bottom-20 lg:bottom-4 z-10">
                                    <NeuCard className="flex items-center justify-between gap-3 !py-3">
                                        <span className="text-sm text-neu-sub">
                                            <b className="text-neu-text">{selected.length}</b> komponen untuk {selectedMachine.code}
                                        </span>
                                        <NeuButton type="button" variant="primary" onClick={saveAssignment}>
                                            Simpan Penugasan
                                        </NeuButton>
                                    </NeuCard>
                                </div>
                            </>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}