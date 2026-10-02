import { useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import { Link } from '@inertiajs/react';

export default function Machines({ auth, machines, types, subCategories }) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        code: '',
        name: '',
        location: '',
        category: '',
        sub_category: '',
        type: 'generic',
        week_group: 1,
        template_id: '',
        is_active: true,
    });

    // Daftar mesin sekarang 120+ baris, jadi pencarian dan filter area
    // dijalankan di sisi klien supaya tidak perlu reload tiap ketikan.
    const [q, setQ] = useState('');
    const [sub, setSub] = useState('all');

    const typeLabels = Object.fromEntries(types.map((t) => [t.value, t.label]));

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

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('machines.store'), { preserveScroll: true });
    };

    // Seluruh 122 mesin hasil impor memakai konvensi C.x -> Minggu x, jadi
    // ketikan sub-kategori langsung menyesuaikan pilihan minggu. Nilainya
    // tetap bisa diubah manual lewat dropdown.
    const handleSubCategory = (value) => {
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
                    <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-3">
                        <input
                            type="text"
                            placeholder="Kode (mis. MC.7-LCS30-103)"
                            value={data.code}
                            onChange={(e) => { clearErrors('code'); setData('code', e.target.value); }}
                            className={`neu-input ${errors.code ? '!shadow-neu-in !ring-1 !ring-neu-bad/60' : ''}`}
                            aria-invalid={!!errors.code}
                            aria-describedby={errors.code ? 'err-code' : undefined}
                            required
                        />
                        <input
                            type="text"
                            placeholder="Nama mesin"
                            value={data.name}
                            onChange={(e) => { clearErrors('name'); setData('name', e.target.value); }}
                            className={`neu-input ${errors.name ? '!shadow-neu-in !ring-1 !ring-neu-bad/60' : ''}`}
                            aria-invalid={!!errors.name}
                            aria-describedby={errors.name ? 'err-name' : undefined}
                            required
                        />
                        <input
                            type="text"
                            placeholder="Lokasi (opsional)"
                            value={data.location}
                            onChange={(e) => setData('location', e.target.value)}
                            className="neu-input"
                        />
                        <input
                            type="text"
                            placeholder="Sub-kategori (opsional, mis. C.7)"
                            value={data.sub_category}
                            onChange={(e) => handleSubCategory(e.target.value)}
                            className="neu-input"
                        />
                        <select
                            value={data.type}
                            onChange={(e) => setData('type', e.target.value)}
                            className="neu-input"
                        >
                            {types.map((t) => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                        <select
                            value={data.week_group}
                            onChange={(e) => setData('week_group', parseInt(e.target.value))}
                            className="neu-input"
                        >
                            {[1,2,3,4,5,6,7,8,9].map((w) => (
                                <option key={w} value={w}>Minggu {w}</option>
                            ))}
                        </select>
                        <div className="col-span-2">
                            <NeuButton type="submit" variant="primary" disabled={processing}>
                                {processing ? 'Menambahkan...' : '+ Tambah Mesin'}
                            </NeuButton>
                        </div>

                        {/* Tanpa blok ini, submit yang gagal (mis. kode sudah
                            dipakai) terlihat seperti tidak terjadi apa-apa.
                            Pesan sukses sudah ditangani toast flash global. */}
                        {Object.keys(errors).length > 0 && (
                            <div className="col-span-2 neu-inset px-3.5 py-3" role="alert">
                                <p className="text-xs font-bold text-neu-bad mb-1">Mesin belum bisa ditambahkan:</p>
                                <ul className="text-xs text-neu-bad list-disc pl-4 space-y-0.5">
                                    {Object.values(errors).map((message, i) => (
                                        <li key={i}>{message}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
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
                        <div
                            key={machine.id}
                            className="flex items-center gap-3.5 p-3.5 rounded-[20px] shadow-neu-up mb-4"
                        >
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
                            <div className="flex items-center gap-2">
                                <NeuPill variant={machine.is_active ? 'done' : 'todo'}>
                                    {machine.is_active ? 'Aktif' : 'Nonaktif'}
                                </NeuPill>
                                <Link
                                    href={route('machines.history', { machine: machine.id })}
                                    className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs"
                                >
                                    Riwayat
                                </Link>
                            </div>
                        </div>
                    )) : (
                        <p className="text-neu-sub text-center py-6 col-span-2">Tidak ada mesin yang cocok.</p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
