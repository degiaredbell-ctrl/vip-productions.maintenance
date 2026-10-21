import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import TemplateFormModal from '@/Pages/Admin/Templates/TemplateFormModal';
import TemplateApplyModal from '@/Pages/Admin/Templates/TemplateApplyModal';

const SCOPE_STYLES = {
    machine: 'text-neu-info bg-neu-info/15 ring-neu-info/30',
    utility: 'text-neu-warn bg-neu-warn/15 ring-neu-warn/30',
};

/**
 * Settings → Template: template komponen untuk Mesin & Utility.
 *
 * Template umum tampil lebih dulu; template per-unit (dibuat otomatis dari
 * impor CSV utility) disembunyikan sampai filternya dinyalakan.
 */
export default function AdminTemplates({ auth, templates = [], units = [], allComponents = [], machineTypes = [] }) {
    const [scope, setScope] = useState('all');
    const [q, setQ] = useState('');
    const [showUnitSpecific, setShowUnitSpecific] = useState(false);
    const [formState, setFormState] = useState({ show: false, template: null });
    const [applyTemplate, setApplyTemplate] = useState(null);
    const [busyId, setBusyId] = useState(null);

    const unitSpecificCount = templates.filter((t) => t.is_unit_specific).length;

    const visible = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return templates.filter((t) => {
            if (!showUnitSpecific && t.is_unit_specific) return false;
            if (scope !== 'all' && t.scope !== scope) return false;
            if (!needle) return true;
            return [t.name, t.description, t.type_label, ...t.components.map((c) => c.name)]
                .some((v) => String(v ?? '').toLowerCase().includes(needle));
        });
    }, [templates, scope, q, showUnitSpecific]);

    const count = (s) => templates.filter((t) => (showUnitSpecific || !t.is_unit_specific) && (s === 'all' || t.scope === s)).length;

    const destroy = (template) => {
        const usage = template.units_count > 0
            ? `\n\n${template.units_count} unit memakai template ini. Komponen unit tersebut TIDAK berubah, hanya tautan templatenya yang dilepas.`
            : '';
        if (!window.confirm(`Hapus template "${template.name}"?${usage}`)) return;

        setBusyId(template.id);
        router.delete(route('admin.templates.destroy', template.id), {
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
                        <h1 className="text-xl sm:text-2xl font-bold">Template</h1>
                        <p className="text-sm text-neu-sub mt-0.5">
                            Kumpulan komponen siap pakai untuk Mesin &amp; Utility. Pilih template saat menambah/mengubah unit, atau terapkan ke banyak unit sekaligus.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <NeuButton href={route('settings')} className="!min-h-[40px] !py-2 !text-sm">‹ Settings</NeuButton>
                        <NeuButton variant="primary" className="!min-h-[40px] !py-2 !text-sm" onClick={() => setFormState({ show: true, template: null })}>
                            + Template Baru
                        </NeuButton>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2.5 mb-3">
                    {[['all', 'Semua'], ['machine', 'Mesin'], ['utility', 'Utility']].map(([key, label]) => (
                        <NeuChip key={key} active={scope === key} onClick={() => setScope(key)}>
                            {label} ({count(key)})
                        </NeuChip>
                    ))}
                    {unitSpecificCount > 0 && (
                        <label className="flex items-center gap-2 text-xs text-neu-sub cursor-pointer ml-1">
                            <input type="checkbox" className="accent-neu-accent" checked={showUnitSpecific} onChange={(e) => setShowUnitSpecific(e.target.checked)} />
                            Tampilkan template per-unit Utility ({unitSpecificCount})
                        </label>
                    )}
                </div>

                <input
                    type="search"
                    className="neu-input mb-4"
                    placeholder="Cari nama template, tipe, atau komponen…"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    aria-label="Cari template"
                />

                {visible.length === 0 ? (
                    <NeuCard>
                        <p className="text-sm text-neu-sub text-center py-6">
                            {templates.length === 0 ? 'Belum ada template. Buat template pertama Anda.' : 'Tidak ada template yang cocok dengan filter.'}
                        </p>
                    </NeuCard>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        {visible.map((t) => (
                            <NeuCard key={t.id} className="flex flex-col">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <b className="block text-sm break-words">{t.name}</b>
                                        {t.description && <span className="block text-xs text-neu-sub mt-0.5 break-words">{t.description}</span>}
                                    </div>
                                    {t.is_default && <NeuPill variant="done">Default</NeuPill>}
                                </div>

                                <div className="flex flex-wrap items-center gap-1.5 mt-2">
                                    <span className={`inline-flex items-center rounded-[10px] px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset ${SCOPE_STYLES[t.scope]}`}>
                                        {t.scope === 'utility' ? 'Utility' : 'Mesin'}
                                    </span>
                                    <span className="text-[11px] text-neu-sub">{t.type_label}</span>
                                </div>

                                <p className="text-xs mt-3">
                                    <b>{t.components.length}</b> komponen · dipakai <b>{t.units_count}</b> unit
                                </p>

                                <ul className="mt-2 space-y-0.5 text-[11px] text-neu-sub flex-1">
                                    {t.components.slice(0, 5).map((c) => (
                                        <li key={c.id} className="truncate">
                                            • {c.name}
                                            {(c.standard || c.spec) && <span className="text-neu-sub/70"> — {c.standard || c.spec}</span>}
                                        </li>
                                    ))}
                                    {t.components.length > 5 && <li className="italic">+ {t.components.length - 5} komponen lainnya</li>}
                                    {t.components.length === 0 && <li className="italic text-neu-warn">Belum ada komponen</li>}
                                </ul>

                                <div className="flex flex-wrap gap-2 mt-4">
                                    <NeuButton size="sm" className="!min-h-[36px] !py-1.5 !px-3 !text-xs" onClick={() => setFormState({ show: true, template: t })}>
                                        Ubah
                                    </NeuButton>
                                    <NeuButton
                                        size="sm"
                                        variant="primary"
                                        className="!min-h-[36px] !py-1.5 !px-3 !text-xs"
                                        onClick={() => setApplyTemplate(t)}
                                        disabled={t.components.length === 0}
                                        title={t.components.length === 0 ? 'Tambahkan komponen dulu' : undefined}
                                    >
                                        Terapkan ke unit
                                    </NeuButton>
                                    <NeuButton
                                        size="sm"
                                        className="!min-h-[36px] !py-1.5 !px-3 !text-xs text-neu-bad ml-auto"
                                        onClick={() => destroy(t)}
                                        disabled={busyId === t.id}
                                    >
                                        Hapus
                                    </NeuButton>
                                </div>
                            </NeuCard>
                        ))}
                    </div>
                )}
            </div>

            <TemplateFormModal
                show={formState.show}
                template={formState.template}
                onClose={() => setFormState({ show: false, template: null })}
                allComponents={allComponents}
                machineTypes={machineTypes}
            />

            <TemplateApplyModal
                show={applyTemplate !== null}
                template={applyTemplate}
                onClose={() => setApplyTemplate(null)}
                units={units}
            />
        </AuthenticatedLayout>
    );
}
