import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';

/**
 * Modal "Terapkan ke unit": pilih banyak unit sekaligus untuk menerima isi
 * template. Hanya unit dengan cakupan yang sama (Mesin/Utility) yang tampil.
 * Unit yang sudah memakai template ini dicentang sejak awal.
 */
export default function TemplateApplyModal({ show, onClose, template = null, units = [] }) {
    const [selected, setSelected] = useState(new Set());
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const eligible = useMemo(() => {
        if (!template) return [];
        return units.filter((u) => (template.scope === 'utility' ? u.is_utility : !u.is_utility));
    }, [units, template]);

    useEffect(() => {
        if (!show || !template) return;
        setSelected(new Set(eligible.filter((u) => u.template_id === template.id).map((u) => u.id)));
        setSearch('');
        setError('');
        // Template mesin bertipe tertentu: tampilkan tipe yang sama lebih dulu.
        setFilter(template.scope === 'machine' && template.machine_type && eligible.some((u) => u.type === template.machine_type) ? 'type' : 'all');
    }, [show, template, eligible]);

    const visible = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return eligible.filter((u) => {
            if (filter === 'type' && u.type !== template?.machine_type) return false;
            if (filter === 'using' && u.template_id !== template?.id) return false;
            return !needle || [u.code, u.name, u.type_label].some((v) => String(v ?? '').toLowerCase().includes(needle));
        });
    }, [eligible, search, filter, template]);

    if (!template) return null;

    const toggle = (id) => setSelected((prev) => {
        const next = new Set(prev);
        if (next.has(id)) next.delete(id); else next.add(id);
        return next;
    });

    const allVisibleSelected = visible.length > 0 && visible.every((u) => selected.has(u.id));
    const toggleVisible = () => setSelected((prev) => {
        const next = new Set(prev);
        visible.forEach((u) => (allVisibleSelected ? next.delete(u.id) : next.add(u.id)));
        return next;
    });

    const submit = () => {
        if (selected.size === 0) {
            setError('Pilih minimal satu unit.');
            return;
        }
        if (!window.confirm(`Komponen ${selected.size} unit akan diganti dengan ${template.components.length} komponen dari template "${template.name}". Lanjutkan?`)) return;

        setSaving(true);
        router.post(route('admin.templates.apply', template.id), { machine_ids: [...selected] }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errors) => setError(Object.values(errors)[0] ?? 'Gagal menerapkan template.'),
            onFinish: () => setSaving(false),
        });
    };

    const usingCount = eligible.filter((u) => u.template_id === template.id).length;

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <div className="p-5 text-left">
                <h2 className="text-lg font-bold">Terapkan ke Unit</h2>
                <p className="text-sm text-neu-sub mt-0.5">{template.name} · {template.components.length} komponen</p>

                <div className="mt-3 rounded-neu-sm bg-[#FFF5E6] px-3 py-2.5 text-xs text-neu-warn">
                    Komponen unit yang dipilih <b>diganti</b> dengan isi template. Standar unit yang sudah diisi tetap dipakai bila template tidak menentukan Standar. Riwayat PM tidak berubah.
                </div>

                <div className="flex flex-wrap gap-2 mt-3">
                    <NeuChip size="sm" active={filter === 'all'} onClick={() => setFilter('all')}>
                        Semua {template.scope === 'utility' ? 'Utility' : 'Mesin'} ({eligible.length})
                    </NeuChip>
                    {template.scope === 'machine' && template.machine_type && (
                        <NeuChip size="sm" active={filter === 'type'} onClick={() => setFilter('type')}>
                            Tipe {template.type_label} ({eligible.filter((u) => u.type === template.machine_type).length})
                        </NeuChip>
                    )}
                    <NeuChip size="sm" active={filter === 'using'} onClick={() => setFilter('using')}>
                        Memakai template ini ({usingCount})
                    </NeuChip>
                </div>

                <div className="flex items-center gap-2 mt-3">
                    <input type="search" className="neu-input" placeholder="Cari kode / nama unit…" value={search} onChange={(e) => setSearch(e.target.value)} aria-label="Cari unit" />
                    <button type="button" className="text-xs font-semibold text-neu-accent whitespace-nowrap px-1" onClick={toggleVisible} disabled={visible.length === 0}>
                        {allVisibleSelected ? 'Kosongkan' : 'Pilih semua'}
                    </button>
                </div>

                <ul className="mt-2 max-h-[46vh] overflow-y-auto pr-1 space-y-1.5">
                    {visible.length === 0 ? (
                        <li className="text-xs text-neu-sub text-center py-4">Tidak ada unit yang cocok.</li>
                    ) : visible.map((u) => {
                        const checked = selected.has(u.id);
                        return (
                            <li key={u.id}>
                                <label className={`flex items-center gap-2.5 rounded-neu-sm border px-3 py-2 cursor-pointer transition-colors ${checked ? 'border-neu-accent/50 bg-neu-active/5' : 'border-neu-dark/50'}`}>
                                    <input type="checkbox" className="rounded accent-neu-accent" checked={checked} onChange={() => toggle(u.id)} />
                                    <span className="min-w-0 flex-1">
                                        <b className="block text-sm truncate">{u.name}</b>
                                        <span className="block text-[11px] text-neu-sub truncate">
                                            {u.code} · {u.type_label} · {u.components_count} komponen
                                            {!u.is_active && ' · nonaktif'}
                                        </span>
                                    </span>
                                    {u.template_id === template.id && (
                                        <span className="neu-pill text-neu-accent text-[10px] flex-none">Memakai</span>
                                    )}
                                </label>
                            </li>
                        );
                    })}
                </ul>

                {error && <p className="mt-2 text-sm font-semibold text-neu-bad" role="alert">{error}</p>}

                <div className="flex flex-wrap items-center justify-between gap-2 mt-4">
                    <span className="text-xs text-neu-sub">{selected.size} unit dipilih</span>
                    <div className="flex gap-2">
                        <NeuButton type="button" onClick={onClose}>Batal</NeuButton>
                        <NeuButton type="button" variant="primary" onClick={submit} disabled={saving || selected.size === 0}>
                            {saving ? 'Menerapkan…' : `Terapkan ke ${selected.size} unit`}
                        </NeuButton>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
