import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import NeuButton from '@/Components/NeuButton';

/**
 * Modal "Edit Komponen": pilih beberapa komponen sekaligus (multi-select) dan
 * isi nilai Standar untuk unit yang sedang disunting.
 *
 * Standar bersifat per unit (kolom pivot `standard`). Bila dikosongkan, form PM
 * dan daftar komponen akan memakai spesifikasi master komponen (`spec`) sebagai
 * nilai bawaan — karena itu `spec` dipakai sebagai placeholder, bukan nilai awal.
 *
 * @param {object}   props
 * @param {boolean}  props.show
 * @param {Function} props.onClose
 * @param {object}   props.unit          Unit Mesin/Utility yang disunting.
 * @param {Array}    props.allComponents Master komponen {id,name,category,spec}.
 * @param {string}   props.action        URL endpoint penyimpanan (PUT).
 */
export default function ComponentAssignModal({ show, onClose, unit, allComponents = [], action }) {
    const [search, setSearch] = useState('');
    // selected: { [componentId]: standardString }. Adanya key = komponen dipilih.
    const [selected, setSelected] = useState({});
    const [saving, setSaving] = useState(false);

    // Setel ulang centang + standar tiap kali modal dibuka untuk unit lain.
    useEffect(() => {
        if (!show || !unit) return;

        const init = {};
        for (const component of unit.components ?? []) {
            if (component.id == null) continue;
            init[component.id] = component.standard ?? '';
        }
        // Jaga komponen yang ditugaskan namun (karena alasan apa pun) tidak ada
        // di daftar `components` agar tidak diam-diam terlepas saat disimpan.
        for (const id of unit.component_ids ?? []) {
            if (!(id in init)) init[id] = '';
        }

        setSelected(init);
        setSearch('');
    }, [show, unit]);

    const grouped = useMemo(() => {
        const needle = search.trim().toLowerCase();
        const filtered = needle
            ? allComponents.filter((c) => [c.name, c.category, c.spec]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(needle)))
            : allComponents;

        const map = new Map();
        for (const component of filtered) {
            const key = component.category || 'Umum';
            if (!map.has(key)) map.set(key, []);
            map.get(key).push(component);
        }
        return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]));
    }, [allComponents, search]);

    const selectedCount = Object.keys(selected).length;

    const toggle = (id) => setSelected((prev) => {
        const next = { ...prev };
        if (id in next) delete next[id];
        else next[id] = '';
        return next;
    });

    const setStandard = (id, value) => setSelected((prev) => ({ ...prev, [id]: value }));

    const toggleCategory = (items) => setSelected((prev) => {
        const ids = items.map((c) => c.id);
        const allSelected = ids.length > 0 && ids.every((id) => id in prev);
        const next = { ...prev };
        if (allSelected) ids.forEach((id) => delete next[id]);
        else ids.forEach((id) => { if (!(id in next)) next[id] = ''; });
        return next;
    });

    const save = () => {
        setSaving(true);
        router.put(action, {
            items: Object.entries(selected).map(([id, standard]) => ({ id: Number(id), standard })),
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <div className="p-5 text-left">
                <h2 className="text-lg font-bold">Edit Komponen</h2>
                {unit && (
                    <p className="text-sm text-neu-sub mt-0.5">
                        {unit.code} — {unit.name}
                    </p>
                )}
                <p className="text-xs text-neu-sub mt-1">
                    Centang komponen yang diperiksa, lalu isi <b>Standar</b>. Kosongkan standar untuk memakai spesifikasi master komponen.
                </p>

                <div className="mt-3">
                    <input
                        type="search"
                        placeholder="Cari komponen…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="neu-input"
                        aria-label="Cari komponen"
                    />
                </div>

                <div className="mt-3 max-h-[52vh] overflow-y-auto pr-1 space-y-4">
                    {grouped.map(([category, items]) => {
                        const allSelected = items.every((c) => c.id in selected);
                        return (
                            <div key={category}>
                                <div className="flex items-center justify-between mb-2">
                                    <p className="text-[11px] font-bold uppercase tracking-wide text-neu-sub">
                                        {category} · {items.filter((c) => c.id in selected).length}/{items.length}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => toggleCategory(items)}
                                        className="text-[11px] font-semibold text-neu-accent hover:underline"
                                    >
                                        {allSelected ? 'Kosongkan' : 'Pilih semua'}
                                    </button>
                                </div>
                                <div className="space-y-1.5">
                                    {items.map((component) => {
                                        const checked = component.id in selected;
                                        return (
                                            <div
                                                key={component.id}
                                                className={`rounded-neu-sm border px-3 py-2 transition-colors ${checked ? 'border-neu-accent/50 bg-neu-active/5' : 'border-neu-dark/50'}`}
                                            >
                                                <label className="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={() => toggle(component.id)}
                                                        className="rounded"
                                                    />
                                                    <span className="text-sm font-medium">{component.name}</span>
                                                    {component.spec && (
                                                        <span className="ml-auto text-[11px] text-neu-sub truncate max-w-[45%]">master: {component.spec}</span>
                                                    )}
                                                </label>
                                                {checked && (
                                                    <div className="mt-2 pl-6">
                                                        <input
                                                            type="text"
                                                            value={selected[component.id] ?? ''}
                                                            onChange={(e) => setStandard(component.id, e.target.value)}
                                                            placeholder={component.spec ? `Standar (default: ${component.spec})` : 'Standar…'}
                                                            className="neu-input !min-h-[38px] !py-1.5 text-sm"
                                                            aria-label={`Standar ${component.name}`}
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}

                    {grouped.length === 0 && (
                        <p className="text-neu-sub text-center py-6">Belum ada komponen yang cocok.</p>
                    )}
                </div>

                <div className="mt-4 pt-3 border-t border-neu-border/70 flex items-center justify-between gap-2">
                    <span className="text-sm text-neu-sub">
                        <b className="text-neu-text">{selectedCount}</b> komponen dipilih
                    </span>
                    <div className="flex gap-2">
                        <NeuButton type="button" variant="ghost" onClick={onClose} className="!min-h-[40px]">
                            Batal
                        </NeuButton>
                        <NeuButton type="button" variant="primary" onClick={save} disabled={saving} className="!min-h-[40px]">
                            {saving ? 'Menyimpan…' : 'Simpan Komponen'}
                        </NeuButton>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
