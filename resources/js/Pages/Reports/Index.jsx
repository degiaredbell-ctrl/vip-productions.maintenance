import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuBars from '@/Components/NeuBars';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';

export default function Reports({ auth, compliance, topParts, actions, totalPm, totalApproved, year, period, periods }) {
    const [selectedPeriod, setSelectedPeriod] = useState(period || '');

    // Selaraskan dengan props setiap kali server merespons.
    useEffect(() => {
        setSelectedPeriod(period || '');
    }, [year, period]);

    const handlePeriodChange = (e) => {
        const value = e.target.value;
        if (value === selectedPeriod) return;
        setSelectedPeriod(value);
        router.get(route('reports.index'), { year, period: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const chartData = compliance.map(c => ({ value: c.percentage, label: c.label.slice(0, 3) }));

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                <div className="flex items-center justify-between mb-5">
                    <div>
                        <h1 className="text-xl font-bold">Laporan PM</h1>
                        <p className="text-sm text-neu-sub">Tahun {year}</p>
                    </div>
                    <select
                        value={selectedPeriod}
                        onChange={handlePeriodChange}
                        className="neu-input !w-auto"
                        aria-label="Filter periode"
                    >
                        <option value="">Semua Periode</option>
                        {periods.map((p) => (
                            <option key={p.value} value={p.value}>{p.label}</option>
                        ))}
                    </select>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-3.5 mb-4">
                    <NeuCard className="text-center !p-3.5">
                        <span className="text-xl font-bold text-neu-accent block">{totalPm}</span>
                        <span className="text-[11px] text-neu-sub">Total PM</span>
                    </NeuCard>
                    <NeuCard className="text-center !p-3.5">
                        <span className="text-xl font-bold text-neu-accent block">{totalApproved}</span>
                        <span className="text-[11px] text-neu-sub">Disetujui</span>
                    </NeuCard>
                    <NeuCard className="text-center !p-3.5">
                        <span className="text-xl font-bold text-neu-bad block">{actions.replace}</span>
                        <span className="text-[11px] text-neu-sub">Part Diganti</span>
                    </NeuCard>
                </div>

                {/* Compliance chart */}
                <NeuCard className="mb-4">
                    <b className="text-sm">Kepatuhan PM per Periode</b>
                    <p className="text-xs text-neu-sub mt-0.5 mb-4">Tahun {year} (%)</p>
                    <NeuBars data={chartData} />
                </NeuCard>

                {/* Action summary */}
                <NeuCard className="mb-4">
                    <b className="text-sm mb-3 block">Ringkasan Tindakan</b>
                    <div className="space-y-2">
                        {[
                            { label: 'Bersihkan', value: actions.clean },
                            { label: 'Perbaiki', value: actions.repair },
                            { label: 'Lumasi', value: actions.lubricate },
                            { label: 'Ganti', value: actions.replace },
                        ].map((a) => (
                            <div key={a.label} className="flex items-center justify-between">
                                <span className="text-sm">{a.label}</span>
                                <NeuPill variant="done">{a.value}x</NeuPill>
                            </div>
                        ))}
                    </div>
                </NeuCard>

                {/* Top parts */}
                <NeuCard className="mb-4">
                    <b className="text-sm mb-3 block">Komponen Paling Sering Diganti</b>
                    {topParts.length > 0 ? (
                        <div className="space-y-2">
                            {topParts.map((p, i) => (
                                <div key={i} className="flex items-center justify-between">
                                    <span className="text-sm">{p.item_name}</span>
                                    <NeuPill variant="done">{p.total}x</NeuPill>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-neu-sub">Belum ada data.</p>
                    )}
                </NeuCard>

                {/* Export */}
                <div className="flex gap-3">
                    <NeuButton
                        href={route('reports.export', { year, period: selectedPeriod, format: 'xlsx' })}
                        variant="primary"
                        className="flex-1"
                    >
                        Ekspor Excel
                    </NeuButton>
                    <NeuButton
                        href={route('reports.export', { year, period: selectedPeriod, format: 'pdf' })}
                        className="flex-1"
                    >
                        Cetak PDF
                    </NeuButton>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
