import { useEffect, useMemo, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';

const EMPTY = { name: '', description: '', scope: 'machine', machine_type: '', is_default: false, components: [] };

/**
 * Modal tambah/ubah Template: identitas template + daftar komponen berurutan
 * dengan Standar default per komponen.
 *
 * Komponen diambil dari master Komponen / Parts. Standar dikosongkan berarti
 * memakai spesifikasi master komponen (ditampilkan sebagai placeholder).
 */
export default function TemplateFormModal({ show, onClose, template = null, allComponents = [], machineTypes = [] }) {
    const form = useForm({ ...EMPTY });
    const [search, setSearch] = useState('');
    const isEdit = Boolean(template);
    const unitSpecific = Boolean(template?.is_unit_specific);

    useEffect(() => {
        if (!show) return;
        form.clearErrors();
        setSearch('');
        form.setData(template
            ? {
                name: template.name,
                description: template.description ?? '',
                scope: template.scope,
                machine_type: template.scope === 'machine' ? template.machine_type : '',
                is_default: Boolean(template.is_default),
                components: template.components.map((c) => ({ id: c.id, standard: c.standard ?? '' })),
            }
            : { ...EMPTY });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, template]);

    const byId = useMemo(() => new Map(allComponents.map((c) => [c.id, c])), [allComponents]);
    const selectedIds = useMemo(() => new Set(form.data.components.map((c) => c.id)), [form.data.components]);

    const available = useMemo(() => {
        const needle = search.trim().toLowerCase();
        const list = allComponents.filter((c) => !selectedIds.has(c.id) && (!needle || [c.name, c.category, c.spec]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(needle))));

        const map = new Map();
        for (const c of list) {
            const key = c.category || 'Umum';
            if (!map.has(key)) map.set(key, []);
            map.get(key).push(c);
        }
        return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]));
    }, [allComponents, selectedIds, search]);

    const setComponents = (next) => {
        form.clearErrors('components');
        form.setData('components', next);
    };
    const add = (ids) => setComponents([...form.data.components, ...ids.filter((id) => !selectedIds.has(id)).map((id) => ({ id, standard: '' }))]);
    const remove = (id) => setComponents(form.data.components.filter((c) => c.id !== id));
    const move = (index, delta) => {
        const next = [...form.data.components];
        const target = index + delta;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        setComponents(next);
    };
    const setStandard = (id, standard) => setComponents(form.data.components.map((c) => (c.id === id ? { ...c, standard } : c)));

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };
        if (isEdit) form.put(route('admin.templates.update', template.id), options);
        else form.post(route('admin.templates.store'), options);
    };

    const err = (name) => form.errors[name] && <p className="mt-1 text-[11px] font-bold text-neu-bad">{form.errors[name]}</p>;
    const componentError = Object.entries(form.errors).find(([k]) => k.startsWith('components'))?.[1];

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <form onSubmit={submit} className="p-5 text-left">
                <h2 className="text-lg font-bold">{isEdit ? 'Ubah Template' : 'Template Baru'}</h2>
                <p className="text-xs text-neu-sub mt-0.5">
                    Template berisi komponen dari master <b>Komponen / Parts</b>. Simpan, lalu gunakan <b>Terapkan ke unit</b> agar komponen unit ikut berubah.
                </p>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                    <div className="sm:col-span-2">
                        <label htmlFor="tpl-name" className="block text-xs text-neu-sub mb-1.5">Nama template *</label>
                        <input id="tpl-name" className="neu-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required maxLength={255} />
                        {err('name')}
                    </div>

                    <div className="sm:col-span-2">
                        <label htmlFor="tpl-desc" className="block text-xs text-neu-sub mb-1.5">Keterangan (opsional)</label>
                        <input id="tpl-desc" className="neu-input" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={255} placeholder="Mis. komponen standar mesin filling lini 2" />
                    </div>

                    {unitSpecific ? (
                        <p className="sm:col-span-2 text-xs text-neu-sub neu-inset px-3 py-2">
                            Template per-unit hasil impor CSV ({template.type_label}). Cakupan dan tipenya tetap.
                        </p>
                    ) : (
                        <>
                            <div>
                                <span className="block text-xs text-neu-sub mb-1.5">Untuk *</span>
                                <div className="flex gap-2" role="radiogroup" aria-label="Cakupan template">
                                    {[['machine', 'Mesin'], ['utility', 'Utility']].map(([value, label]) => (
                                        <NeuChip
                                            key={value}
                                            size="sm"
                                            role="radio"
                                            aria-checked={form.data.scope === value}
                                            active={form.data.scope === value}
                                            onClick={() => form.setData((d) => ({ ...d, scope: value, machine_type: value === 'utility' ? '' : d.machine_type }))}
                                        >
                                            {label}
                                        </NeuChip>
                                    ))}
                                </div>
                                {err('scope')}
                            </div>

                            {form.data.scope === 'machine' && (
                                <div>
                                    <label htmlFor="tpl-type" className="block text-xs text-neu-sub mb-1.5">Tipe mesin *</label>
                                    <select id="tpl-type" className="neu-input" value={form.data.machine_type} onChange={(e) => form.setData('machine_type', e.target.value)}>
                                        <option value="">— Pilih tipe —</option>
                                        {machineTypes.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                    </select>
                                    {err('machine_type')}
                                </div>
                            )}

                            <label className="sm:col-span-2 flex items-start gap-2 text-xs text-neu-sub cursor-pointer">
                                <input type="checkbox" className="mt-0.5 accent-neu-accent" checked={form.data.is_default} onChange={(e) => form.setData('is_default', e.target.checked)} />
                                <span>Jadikan template <b>default</b> untuk tipe ini (dipakai form PM bila unit belum punya komponen).</span>
                            </label>
                        </>
                    )}
                </div>

                {/* Komponen terpilih */}
                <div className="mt-5">
                    <div className="flex items-center justify-between mb-2">
                        <b className="text-sm">Komponen template ({form.data.components.length})</b>
                        <Link href={route('admin.components')} className="text-[11px] font-semibold text-neu-accent hover:underline">
                            Kelola master Komponen
                        </Link>
                    </div>
                    {componentError && <p className="mb-2 text-[11px] font-bold text-neu-bad">{componentError}</p>}
                    {form.data.components.length === 0 ? (
                        <p className="text-xs text-neu-sub neu-inset px-3 py-3 text-center">Belum ada komponen. Tambahkan dari daftar di bawah.</p>
                    ) : (
                        <ol className="space-y-1.5 max-h-[34vh] overflow-y-auto pr-1">
                            {form.data.components.map((row, index) => {
                                const c = byId.get(row.id);
                                return (
                                    <li key={row.id} className="rounded-neu-sm border border-neu-accent/40 bg-neu-active/5 px-2.5 py-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-[11px] font-bold text-neu-sub w-5 text-right flex-none">{index + 1}.</span>
                                            <div className="min-w-0 flex-1">
                                                <b className="block text-sm truncate">{c?.name ?? `Komponen #${row.id}`}</b>
                                                <span className="block text-[11px] text-neu-sub truncate">{c?.category}</span>
                                            </div>
                                            <div className="flex items-center gap-0.5 flex-none">
                                                <button type="button" className="px-1.5 py-1 text-neu-sub disabled:opacity-30" onClick={() => move(index, -1)} disabled={index === 0} aria-label="Naikkan">↑</button>
                                                <button type="button" className="px-1.5 py-1 text-neu-sub disabled:opacity-30" onClick={() => move(index, 1)} disabled={index === form.data.components.length - 1} aria-label="Turunkan">↓</button>
                                                <button type="button" className="px-1.5 py-1 text-neu-bad font-bold" onClick={() => remove(row.id)} aria-label={`Hapus ${c?.name ?? ''}`}>✕</button>
                                            </div>
                                        </div>
                                        <input
                                            className="neu-input mt-1.5 !py-1.5 !text-xs"
                                            value={row.standard}
                                            onChange={(e) => setStandard(row.id, e.target.value)}
                                            placeholder={c?.spec ? `Standar (kosong = ${c.spec})` : 'Standar default (opsional)'}
                                            maxLength={255}
                                            aria-label={`Standar ${c?.name ?? ''}`}
                                        />
                                    </li>
                                );
                            })}
                        </ol>
                    )}
                </div>

                {/* Tambah komponen */}
                <div className="mt-4">
                    <b className="text-sm block mb-2">Tambah komponen</b>
                    <input type="search" className="neu-input" placeholder="Cari komponen…" value={search} onChange={(e) => setSearch(e.target.value)} aria-label="Cari komponen" />
                    <div className="mt-2 max-h-[26vh] overflow-y-auto pr-1 space-y-3">
                        {available.length === 0 ? (
                            <p className="text-xs text-neu-sub text-center py-3">Tidak ada komponen lain yang cocok.</p>
                        ) : available.map(([category, items]) => (
                            <div key={category}>
                                <div className="flex items-center justify-between mb-1">
                                    <p className="text-[11px] font-bold uppercase tracking-wide text-neu-sub">{category} · {items.length}</p>
                                    <button type="button" className="text-[11px] font-semibold text-neu-accent hover:underline" onClick={() => add(items.map((c) => c.id))}>
                                        Tambah semua
                                    </button>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {items.map((c) => (
                                        <button
                                            key={c.id}
                                            type="button"
                                            onClick={() => add([c.id])}
                                            className="rounded-neu-sm border border-neu-dark/50 px-2.5 py-1 text-xs hover:border-neu-accent/60 hover:text-neu-accent"
                                            title={c.spec ? `Spesifikasi: ${c.spec}` : undefined}
                                        >
                                            + {c.name}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex justify-end gap-2 mt-5">
                    <NeuButton type="button" onClick={onClose}>Batal</NeuButton>
                    <NeuButton type="submit" variant="primary" disabled={form.processing}>
                        {form.processing ? 'Menyimpan…' : 'Simpan Template'}
                    </NeuButton>
                </div>
            </form>
        </Modal>
    );
}
