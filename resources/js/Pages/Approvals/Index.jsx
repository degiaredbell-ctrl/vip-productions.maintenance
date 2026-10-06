import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import NeuButton from '@/Components/NeuButton';
import SignatureActions from '@/Components/SignatureActions';

const STATUS_VARIANTS = { todo: 'todo', progress: 'progress', done: 'done', issue: 'issue' };

export default function Approvals({ auth, records, years, year, period, periods, myStages, hasStages }) {
    const [selectedYear, setSelectedYear] = useState(year);
    const [selectedPeriod, setSelectedPeriod] = useState(period);

    useEffect(() => {
        setSelectedYear(year);
        setSelectedPeriod(period);
    }, [year, period]);

    const applyFilters = (patch) => {
        router.get(route('approvals.index'), { year: selectedYear, period: selectedPeriod, ...patch }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Persetujuan</h1>
                </div>

                {!hasStages ? (
                    <NeuCard>
                        <p className="text-sm text-neu-sub text-center py-4">
                            Anda tidak memiliki kewenangan persetujuan pada rantai tanda tangan PM.
                        </p>
                    </NeuCard>
                ) : (
                    <>
                        {/*
                            Tahap milik user ini ditampilkan supaya daftar kosong
                            bisa dipahami: "menunggu apa", bukan sekadar tidak ada data.
                        */}
                        <NeuCard className="mb-4 !shadow-neu-in">
                            <p className="text-xs text-neu-sub mb-1.5">Tahap yang menunggu Anda</p>
                            <div className="flex flex-wrap gap-2">
                                {myStages.map((stage) => (
                                    <NeuPill key={stage.value} variant="progress">{stage.label}</NeuPill>
                                ))}
                            </div>
                        </NeuCard>

                        <div
                            className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-2"
                            role="group"
                            aria-label="Filter tahun"
                        >
                            {years.map((y) => (
                                <NeuChip
                                    key={y}
                                    active={selectedYear === y}
                                    onClick={() => {
                                        setSelectedYear(y);
                                        applyFilters({ year: y });
                                    }}
                                    aria-label={`Tahun ${y}`}
                                >
                                    {y}
                                </NeuChip>
                            ))}
                        </div>

                        <div
                            className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-4"
                            role="group"
                            aria-label="Filter periode"
                        >
                            <NeuChip
                                active={selectedPeriod === 'all'}
                                onClick={() => {
                                    setSelectedPeriod('all');
                                    applyFilters({ period: 'all' });
                                }}
                            >
                                Semua Periode
                            </NeuChip>
                            {periods.map((p) => (
                                <NeuChip
                                    key={p.value}
                                    active={selectedPeriod === p.value}
                                    onClick={() => {
                                        setSelectedPeriod(p.value);
                                        applyFilters({ period: p.value });
                                    }}
                                    aria-label={`Periode ${p.label}`}
                                >
                                    {p.label}
                                </NeuChip>
                            ))}
                        </div>

                        <p className="text-xs text-neu-sub mb-3">
                            {records.length} PM menunggu tindakan Anda
                        </p>

                        {records.length === 0 ? (
                            <NeuCard>
                                <p className="text-sm text-neu-sub text-center py-4">
                                    Tidak ada PM yang menunggu persetujuan Anda pada filter ini.
                                </p>
                            </NeuCard>
                        ) : (
                            <div className="grid grid-cols-1 gap-0 sm:grid-cols-2 sm:gap-x-4 xl:grid-cols-3 xl:gap-x-5">
                                {records.map((record) => (
                                    <NeuCard key={record.id} className="mb-4">
                                        <div className="flex items-start gap-3.5 mb-3">
                                            <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">
                                                {record.sub_category ?? '—'}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <b className="block text-sm truncate">{record.name}</b>
                                                <span className="block text-xs text-neu-sub truncate">
                                                    {record.code}
                                                    {record.location && ` · ${record.location}`}
                                                </span>
                                                <span className="block text-xs text-neu-sub mt-0.5">
                                                    {record.period_label} {record.year} · {record.technician_name ?? 'Tanpa teknisi'}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between gap-2 mb-3">
                                            <NeuPill variant={STATUS_VARIANTS[record.status] ?? 'todo'}>
                                                {record.status_label}
                                            </NeuPill>
                                            {record.revision_count > 0 && (
                                                <span className="text-[11px] text-neu-sub">
                                                    Revisi {record.revision_count}×
                                                </span>
                                            )}
                                        </div>

                                        <p className="text-xs text-neu-sub mb-3">
                                            Menunggu <b className="text-neu-text">{record.awaiting_label}</b>
                                            {' · '}
                                            {record.filled_percent}% checklist terisi
                                        </p>

                                        {/*
                                            Tanda tangan bisa diselesaikan langsung
                                            dari antrean ini: nama penanda tangan
                                            dan gambar tanda tangan diletakkan
                                            berdampingan di dalam kartu yang sama,
                                            dan keduanya wajib diisi.
                                        */}
                                        {record.can_sign && record.awaiting_stage && (
                                            <SignatureActions
                                                embedded
                                                idPrefix={`pm-${record.id}-`}
                                                recordId={record.id}
                                                stage={record.awaiting_stage}
                                                canSign={record.can_sign}
                                                canReject={record.can_reject}
                                            />
                                        )}

                                        <NeuButton
                                            href={route('machines.pm.create', {
                                                machine: record.machine_id,
                                                year: record.year,
                                                period: record.period,
                                            })}
                                            className="w-full mt-4"
                                        >
                                            Buka Checklist
                                        </NeuButton>
                                    </NeuCard>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
