import { useMemo, useState } from 'react';
import NeuRing from '@/Components/NeuRing';

/**
 * Nama tampil + penjelasan tiap permission. Permission baru yang belum
 * terdaftar di sini tetap tampil memakai kodenya, di grup "Lainnya".
 */
const PERMISSION_INFO = {
    'dashboard.view': ['Lihat Beranda', 'Dashboard report PM Mesin & Utility'],
    'pm.fill': ['Isi checklist PM', 'Mengisi checklist di lapangan'],
    'pm.sign': ['Tanda tangan Teknisi', 'Tahap 1: submit checklist'],
    'pm.acknowledge': ['Persetujuan User PIC', 'Tahap 2 rantai persetujuan'],
    'pm.approve': ['Persetujuan Atasan', 'Tahap 3: menyelesaikan PM'],
    'pm.history.view': ['Lihat riwayat PM', 'Riwayat checklist per unit'],
    'pm.manage': ['Kelola PM Records', 'Ubah/hapus semua record PM (admin)'],
    'report.view': ['Lihat laporan', 'Ringkasan tindakan & komponen'],
    'report.export': ['Ekspor laporan', 'Unduh Excel / PDF'],
    'machine.view': ['Lihat Mesin', 'Halaman & daftar Mesin (baca saja)'],
    'machine.manage': ['Kelola Mesin', 'Tambah, ubah, hapus Mesin'],
    'utility.view': ['Lihat Utility', 'Halaman & daftar Utility (baca saja)'],
    'utility.manage': ['Kelola Utility', 'Tambah, ubah, hapus Utility'],
    'template.manage': ['Kelola Template', 'Template komponen & penerapannya'],
    'component.manage': ['Kelola Komponen / Parts', 'Master komponen'],
    'masterdata.manage': ['Kelola Tipe Mesin & Lokasi', 'Master data Settings'],
    'sparepart.view': ['Lihat Suku Cadang', 'Stok & dashboard suku cadang'],
    'sparepart.manage': ['Kelola Suku Cadang', 'Master, stok masuk/keluar, impor CSV'],
    'user.manage': ['Kelola Pengguna', 'Tambah/ubah akun & role pengguna'],
    'activity.view': ['Lihat Activity Log', 'Jejak aktivitas pengguna'],
    'backup.manage': ['Backup & Restore', 'Cadangkan & pulihkan database'],
    'integration.manage': ['Integrate', 'Email Microsoft 365 & kredensial'],
};

const GROUPS = [
    { key: 'pm', label: 'Preventive Maintenance', color: '#0F6E56', match: (p) => p.startsWith('pm.') },
    { key: 'report', label: 'Beranda & Laporan', color: '#1F6FEB', match: (p) => p.startsWith('dashboard.') || p.startsWith('report.') },
    { key: 'asset', label: 'Aset Mesin & Utility', color: '#A86B0B', match: (p) => /^(machine|utility|template|component|masterdata)\./.test(p) },
    { key: 'sparepart', label: 'Suku Cadang', color: '#7C4DCC', match: (p) => p.startsWith('sparepart.') },
    { key: 'admin', label: 'Administrasi Sistem', color: '#B93A2E', match: (p) => /^(user|activity|backup|integration)\./.test(p) },
];

const OTHER = { key: 'other', label: 'Lainnya', color: '#5A6475' };

function Check({ checked, partial = false, color }) {
    return (
        <span
            className={`grid place-items-center w-6 h-6 flex-none rounded-lg transition-all duration-150 ${checked || partial ? 'text-white shadow-neu-up-sm' : 'shadow-neu-in text-transparent'}`}
            style={checked || partial ? { backgroundColor: color } : undefined}
            aria-hidden="true"
        >
            {partial && !checked ? (
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round"><path d="M6 12h12" /></svg>
            ) : (
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
            )}
        </span>
    );
}

/**
 * Pemilih permission: daftar per grup dengan multi-select, grafik progres per
 * grup, dan cincin total. Tema neumorphism mengikuti komponen Neu*.
 *
 * @param {string[]} permissions  Semua permission yang tersedia.
 * @param {string[]} value        Permission terpilih.
 * @param {(next: string[]) => void} onChange
 */
export default function PermissionPicker({ permissions = [], value = [], onChange, disabled = false }) {
    const [q, setQ] = useState('');
    const [collapsed, setCollapsed] = useState({});
    const selected = useMemo(() => new Set(value), [value]);

    const groups = useMemo(() => {
        const used = new Set();
        const list = GROUPS.map((g) => {
            const items = permissions.filter((p) => g.match(p));
            items.forEach((p) => used.add(p));
            return { ...g, items };
        });
        const rest = permissions.filter((p) => !used.has(p));
        if (rest.length) list.push({ ...OTHER, items: rest });
        return list.filter((g) => g.items.length > 0);
    }, [permissions]);

    const needle = q.trim().toLowerCase();
    const matches = (p) => !needle || [p, ...(PERMISSION_INFO[p] ?? [])].some((v) => v.toLowerCase().includes(needle));

    const set = (next) => !disabled && onChange([...next].filter((p) => permissions.includes(p)).sort());
    const toggle = (perm) => {
        const next = new Set(selected);
        if (next.has(perm)) next.delete(perm); else next.add(perm);
        set(next);
    };
    const toggleGroup = (items) => {
        const all = items.every((p) => selected.has(p));
        const next = new Set(selected);
        items.forEach((p) => (all ? next.delete(p) : next.add(p)));
        set(next);
    };

    const total = permissions.length;
    const count = permissions.filter((p) => selected.has(p)).length;
    const percent = total ? Math.round((count / total) * 100) : 0;

    return (
        <div className={disabled ? 'opacity-60 pointer-events-none' : ''}>
            {/* Ringkasan + grafik per grup */}
            <div className="neu-inset rounded-neu p-4 mb-4 flex flex-col sm:flex-row gap-5 items-center">
                <NeuRing percentage={percent} size={110} strokeWidth={9} value={`${count}/${total}`} label="permission" />
                <div className="flex-1 w-full space-y-2.5">
                    {groups.map((g) => {
                        const n = g.items.filter((p) => selected.has(p)).length;
                        return (
                            <button
                                key={g.key}
                                type="button"
                                onClick={() => document.getElementById(`perm-group-${g.key}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })}
                                className="w-full text-left group"
                                aria-label={`${g.label}: ${n} dari ${g.items.length} dipilih`}
                            >
                                <div className="flex items-center justify-between text-[11px] mb-1">
                                    <span className="font-semibold text-neu-text group-hover:underline">{g.label}</span>
                                    <span className="tabular-nums text-neu-sub">{n}/{g.items.length}</span>
                                </div>
                                <div className="h-2.5 rounded-full shadow-neu-in overflow-hidden">
                                    <div
                                        className="h-full rounded-full transition-all duration-300"
                                        style={{ width: `${(n / g.items.length) * 100}%`, backgroundColor: g.color }}
                                    />
                                </div>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Toolbar */}
            <div className="flex flex-wrap items-center gap-2 mb-3">
                <input
                    type="search"
                    className="neu-input flex-1 min-w-[12rem]"
                    placeholder="Cari permission…"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    aria-label="Cari permission"
                />
                <button type="button" className="neu-btn !min-h-[38px] !py-1.5 !px-3 !text-xs" onClick={() => set(permissions)}>Pilih semua</button>
                <button type="button" className="neu-btn !min-h-[38px] !py-1.5 !px-3 !text-xs !text-neu-bad" onClick={() => set([])}>Kosongkan</button>
            </div>

            {/* Daftar per grup */}
            <div className="space-y-3 max-h-[60vh] overflow-y-auto pr-1 -mr-1 p-1">
                {groups.map((g) => {
                    const visible = g.items.filter(matches);
                    if (visible.length === 0) return null;

                    const n = g.items.filter((p) => selected.has(p)).length;
                    const all = n === g.items.length;
                    const isOpen = needle || !collapsed[g.key];

                    return (
                        <section key={g.key} id={`perm-group-${g.key}`} className="rounded-neu shadow-neu-up p-3 scroll-mt-2">
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => toggleGroup(g.items)}
                                    className="flex items-center gap-3 flex-1 min-w-0 text-left"
                                    aria-pressed={all}
                                    aria-label={`${all ? 'Kosongkan' : 'Pilih semua'} ${g.label}`}
                                >
                                    <Check checked={all} partial={n > 0} color={g.color} />
                                    <span className="min-w-0">
                                        <b className="block text-sm truncate">{g.label}</b>
                                        <span className="block text-[11px] text-neu-sub">{n} dari {g.items.length} dipilih · klik untuk {all ? 'kosongkan' : 'pilih semua'}</span>
                                    </span>
                                </button>
                                {!needle && (
                                    <button
                                        type="button"
                                        className="neu-btn !min-h-[32px] !w-8 !p-0 !text-xs flex-none"
                                        onClick={() => setCollapsed((c) => ({ ...c, [g.key]: !c[g.key] }))}
                                        aria-expanded={isOpen}
                                        aria-label={isOpen ? `Tutup ${g.label}` : `Buka ${g.label}`}
                                    >
                                        {isOpen ? '▾' : '▸'}
                                    </button>
                                )}
                            </div>

                            {isOpen && (
                                <ul className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2" role="group" aria-label={g.label}>
                                    {visible.map((perm) => {
                                        const on = selected.has(perm);
                                        const [label, hint] = PERMISSION_INFO[perm] ?? [perm, null];
                                        return (
                                            <li key={perm}>
                                                <button
                                                    type="button"
                                                    role="checkbox"
                                                    aria-checked={on}
                                                    onClick={() => toggle(perm)}
                                                    className={`w-full flex items-center gap-3 rounded-neu-sm px-3 py-2.5 text-left transition-all duration-150 ${on ? 'shadow-neu-in' : 'shadow-neu-up-sm hover:shadow-neu-up'}`}
                                                >
                                                    <Check checked={on} color={g.color} />
                                                    <span className="min-w-0 flex-1">
                                                        <span className={`block text-[13px] font-semibold truncate ${on ? 'text-neu-text' : 'text-neu-sub'}`}>{label}</span>
                                                        <span className="block text-[10.5px] text-neu-sub truncate">
                                                            <code className="font-mono">{perm}</code>
                                                            {hint && <> · {hint}</>}
                                                        </span>
                                                    </span>
                                                    <span
                                                        className="w-1.5 h-8 rounded-full flex-none transition-opacity"
                                                        style={{ backgroundColor: g.color, opacity: on ? 1 : 0.15 }}
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </section>
                    );
                })}
                {groups.every((g) => g.items.filter(matches).length === 0) && (
                    <p className="text-sm text-neu-sub text-center py-6">Tidak ada permission yang cocok.</p>
                )}
            </div>
        </div>
    );
}
