import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';

const FIELD = 'neu-input !min-h-[40px] !py-2 text-sm';

function Field({ label, children, error }) {
    return (
        <label className="block">
            <span className="block text-xs font-semibold text-neu-sub mb-1">{label}</span>
            {children}
            {error ? <span className="block text-xs text-neu-bad mt-1">{error}</span> : null}
        </label>
    );
}

/**
 * Form Maintenance - Suku Cadang: catat pemakaian suku cadang ke unit
 * (Mesin/Utility) per periode PM, plus riwayat pemakaian per tahun/periode.
 * Hanya pemegang `sparepart.manage` yang melihat form pencatatan.
 */
export default function SukuCadang({ auth, units = [], parts = [], periods = [], years = [], year, period, currentPeriod, usages = [], can = {} }) {
    const [partQuery, setPartQuery] = useState('');

    const form = useForm({
        machine_id: '',
        spare_part_id: '',
        year: year,
        period: period === 'all' ? currentPeriod : period,
        qty: 1,
        used_at: '',
        note: '',
    });

    const filteredParts = useMemo(() => {
        const q = partQuery.trim().toLowerCase();
        if (q === '') return parts;

        return parts.filter((part) => `${part.code} ${part.name}`.toLowerCase().includes(q));
    }, [parts, partQuery]);

    const selectedPart = parts.find((part) => String(part.id) === String(form.data.spare_part_id));

    const submit = (e) => {
        e.preventDefault();
        form.post(route('form-maintenance.suku-cadang.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset('spare_part_id', 'qty', 'used_at', 'note'),
        });
    };

    const applyFilter = (next) => {
        router.get(
            route('form-maintenance.suku-cadang'),
            { year: next.year ?? year, period: next.period ?? period },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <div className="flex items-center gap-3 mb-2">
                        <NeuButton href={route('form-maintenance')} className="!px-3 !min-h-[36px] !py-1.5 text-xs">
                            ‹ Kembali
                        </NeuButton>
                        <h1 className="text-xl sm:text-2xl font-bold">Form Maintenance - Suku Cadang</h1>
                    </div>
                    <p className="text-xs text-neu-sub">Path: /form-maintenance/suku-cadang</p>
                </div>

                {can.manage && (
                    <NeuCard className="mb-4">
                        <h2 className="text-base font-bold mb-1">Catat Pemakaian</h2>
                        <p className="text-xs text-neu-sub mb-4">Pemakaian dicatat sebagai barang keluar dan langsung mengurangi stok.</p>

                        <form onSubmit={submit} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <Field label="Unit (Mesin / Utility)" error={form.errors.machine_id}>
                                <select
                                    className={FIELD}
                                    value={form.data.machine_id}
                                    onChange={(e) => form.setData('machine_id', e.target.value)}
                                    required
                                >
                                    <option value="">— Pilih unit —</option>
                                    {units.map((unit) => (
                                        <option key={unit.id} value={unit.id}>
                                            {unit.code} — {unit.name} ({unit.type_label})
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Cari suku cadang" error={null}>
                                <input
                                    className={FIELD}
                                    value={partQuery}
                                    onChange={(e) => setPartQuery(e.target.value)}
                                    placeholder="Ketik kode / nama…"
                                />
                            </Field>

                            <Field label="Suku Cadang" error={form.errors.spare_part_id}>
                                <select
                                    className={FIELD}
                                    value={form.data.spare_part_id}
                                    onChange={(e) => form.setData('spare_part_id', e.target.value)}
                                    required
                                >
                                    <option value="">— Pilih suku cadang —</option>
                                    {filteredParts.map((part) => (
                                        <option key={part.id} value={part.id}>
                                            {part.code} — {part.name} (stok {part.stock}{part.unit ? ` ${part.unit}` : ''})
                                        </option>
                                    ))}
                                </select>
                                {selectedPart ? (
                                    <span className="block text-[11px] text-neu-sub mt-1">
                                        Stok tersedia: {selectedPart.stock}{selectedPart.unit ? ` ${selectedPart.unit}` : ''}
                                    </span>
                                ) : null}
                            </Field>

                            <Field label="Jumlah" error={form.errors.qty}>
                                <input
                                    type="number"
                                    min="1"
                                    className={FIELD}
                                    value={form.data.qty}
                                    onChange={(e) => form.setData('qty', e.target.value)}
                                    required
                                />
                            </Field>

                            <Field label="Tanggal Pemakaian" error={form.errors.used_at}>
                                <input
                                    type="date"
                                    className={FIELD}
                                    value={form.data.used_at}
                                    onChange={(e) => form.setData('used_at', e.target.value)}
                                />
                            </Field>

                            <Field label="Periode" error={form.errors.period}>
                                <select
                                    className={FIELD}
                                    value={form.data.period}
                                    onChange={(e) => form.setData('period', e.target.value)}
                                    required
                                >
                                    {periods.map((p) => (
                                        <option key={p.value} value={p.value}>{p.label}</option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Tahun" error={form.errors.year}>
                                <select
                                    className={FIELD}
                                    value={form.data.year}
                                    onChange={(e) => form.setData('year', Number(e.target.value))}
                                    required
                                >
                                    {years.map((y) => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Catatan" error={form.errors.note}>
                                <input
                                    className={FIELD}
                                    value={form.data.note}
                                    onChange={(e) => form.setData('note', e.target.value)}
                                    placeholder="Opsional"
                                />
                            </Field>

                            <div className="flex items-end">
                                <NeuButton type="submit" disabled={form.processing} className="w-full justify-center">
                                    {form.processing ? 'Menyimpan…' : 'Catat Pemakaian'}
                                </NeuButton>
                            </div>
                        </form>
                    </NeuCard>
                )}

                <NeuCard>
                    <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 className="text-base font-bold">Riwayat Pemakaian</h2>
                        <div className="flex items-center gap-2">
                            <select
                                className="neu-input !min-h-[36px] !py-1.5 text-sm"
                                value={year}
                                onChange={(e) => applyFilter({ year: Number(e.target.value), period: 'all' })}
                                aria-label="Filter tahun"
                            >
                                {years.map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                            <select
                                className="neu-input !min-h-[36px] !py-1.5 text-sm"
                                value={period}
                                onChange={(e) => applyFilter({ period: e.target.value })}
                                aria-label="Filter periode"
                            >
                                <option value="all">Semua Periode</option>
                                {periods.map((p) => (
                                    <option key={p.value} value={p.value}>{p.label}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-neu-sub text-xs">
                                    <th className="py-2 pr-3 font-semibold">Tanggal</th>
                                    <th className="py-2 pr-3 font-semibold">Unit</th>
                                    <th className="py-2 pr-3 font-semibold">Suku Cadang</th>
                                    <th className="py-2 pr-3 font-semibold text-right">Jumlah</th>
                                    <th className="py-2 pr-3 font-semibold">Periode</th>
                                    <th className="py-2 pr-3 font-semibold">Catatan</th>
                                    <th className="py-2 font-semibold">Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                {usages.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="py-6 text-center text-neu-sub text-sm">
                                            Belum ada pemakaian dicatat untuk filter ini.
                                        </td>
                                    </tr>
                                ) : (
                                    usages.map((usage) => (
                                        <tr key={usage.id} className="border-t border-neu-sub/15 align-top">
                                            <td className="py-2 pr-3 whitespace-nowrap">{usage.moved_at ?? '—'}</td>
                                            <td className="py-2 pr-3">
                                                {usage.machine ? (
                                                    <>
                                                        <span className="font-semibold">{usage.machine.code}</span>
                                                        <span className="block text-xs text-neu-sub">{usage.machine.name}</span>
                                                    </>
                                                ) : '—'}
                                            </td>
                                            <td className="py-2 pr-3">
                                                {usage.part ? (
                                                    <>
                                                        <span className="font-semibold">{usage.part.code}</span>
                                                        <span className="block text-xs text-neu-sub">{usage.part.name}</span>
                                                    </>
                                                ) : '—'}
                                            </td>
                                            <td className="py-2 pr-3 text-right whitespace-nowrap">
                                                {usage.qty}{usage.unit_label ? ` ${usage.unit_label}` : ''}
                                            </td>
                                            <td className="py-2 pr-3 whitespace-nowrap">{usage.period_label} {usage.year}</td>
                                            <td className="py-2 pr-3 text-neu-sub">{usage.note ?? '—'}</td>
                                            <td className="py-2 whitespace-nowrap">{usage.user_name ?? '—'}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </NeuCard>
            </div>
        </AuthenticatedLayout>
    );
}
