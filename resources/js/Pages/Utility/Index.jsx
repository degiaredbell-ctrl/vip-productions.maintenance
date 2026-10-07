import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';

/**
 * Daftar komponen dikelompokkan per kategori dengan mempertahankan urutan
 * kemunculan pertama, supaya daftar rapi tapi tetap mengikuti CSV aslinya.
 */
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

export default function UtilityIndex({ auth, utilities, subCategories, can }) {
    const [q, setQ] = useState('');
    const [sub, setSub] = useState('all');
    const [expandedId, setExpandedId] = useState(null);

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

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="flex items-center justify-between mb-5">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold">Utility</h1>
                        <p className="text-sm text-neu-sub">
                            {utilities.length} unit utility terdaftar
                            {visible.length !== utilities.length && ` · ${visible.length} ditampilkan`}
                        </p>
                    </div>
                </div>

                {/* Search + filter area */}
                <div className="mb-4">
                    <input
                        type="search"
                        placeholder="Cari kode, nama, atau lokasi unit…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        className="neu-input"
                        aria-label="Cari unit utility"
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

                {/* Utility list */}
                <div className="grid grid-cols-1 gap-0 sm:grid-cols-2 sm:gap-x-4 xl:grid-cols-3 xl:gap-x-5">
                    {visible.length > 0 ? visible.map((unit) => {
                        const expanded = expandedId === unit.id;
                        const sections = groupComponents(unit.components);

                        return (
                            <div key={unit.id} data-utility-card={unit.id} className="mb-4">
                                <div className={`p-3.5 rounded-[20px] ${expanded ? 'shadow-neu-in' : 'shadow-neu-up'}`}>
                                    <div className="flex items-center gap-3.5">
                                        <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">
                                            {unit.sub_category ?? unit.code}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <b className="block text-sm truncate">{unit.name}</b>
                                            <span className="block text-xs text-neu-sub truncate">
                                                {unit.code} · Minggu {unit.week_group}
                                            </span>
                                            <span className="block text-xs text-neu-sub truncate">
                                                {unit.location ?? 'Tanpa lokasi'}
                                                {unit.category && ` · ${unit.category}`}
                                                {unit.component_count > 0 && ` · ${unit.component_count} komponen`}
                                            </span>
                                        </div>
                                        <NeuPill variant={unit.is_active ? 'done' : 'todo'}>
                                            {unit.is_active ? 'Aktif' : 'Nonaktif'}
                                        </NeuPill>
                                    </div>

                                    {expanded && (
                                        <div className="mt-3 space-y-2.5">
                                            {sections.length > 0 ? sections.map((section) => (
                                                <div key={section.category}>
                                                    <p className="text-[11px] font-bold uppercase tracking-wide text-neu-sub mb-1">
                                                        {section.category}
                                                    </p>
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {section.items.map((name) => (
                                                            <span
                                                                key={name}
                                                                className="neu-inset rounded-neu-sm px-2.5 py-1 text-xs"
                                                            >
                                                                {name}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </div>
                                            )) : (
                                                <p className="text-xs text-neu-sub">
                                                    Belum ada komponen maintenance.
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    <div className="mt-3 flex items-center gap-2 justify-end">
                                        {can.fill && (
                                            <Link
                                                href={route('machines.pm.create', { machine: unit.id })}
                                                className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs"
                                            >
                                                Isi PM
                                            </Link>
                                        )}
                                        {can.history && (
                                            <Link
                                                href={route('machines.history', { machine: unit.id })}
                                                className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs"
                                            >
                                                Riwayat
                                            </Link>
                                        )}
                                        <NeuChip
                                            active={expanded}
                                            onClick={() => setExpandedId(expanded ? null : unit.id)}
                                            aria-label="Lihat komponen maintenance"
                                        >
                                            {expanded ? 'Tutup' : `Komponen${unit.component_count > 0 ? ` (${unit.component_count})` : ''}`}
                                        </NeuChip>
                                    </div>
                                </div>
                            </div>
                        );
                    }) : (
                        <p className="text-neu-sub text-center py-6 sm:col-span-2 xl:col-span-3">
                            Tidak ada unit utility yang cocok.
                        </p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}