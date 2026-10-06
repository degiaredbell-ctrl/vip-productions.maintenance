import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import ApprovalRow from '@/Pages/Approvals/ApprovalRow';

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
                            className="flex flex-wrap gap-2.5 px-1.5 py-2 mb-2 lg:flex-nowrap lg:overflow-x-auto"
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
                            className="flex flex-wrap gap-2.5 px-1.5 py-2 mb-4 lg:flex-nowrap lg:overflow-x-auto"
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
                            /*
                                Tabel, bukan kartu di layar lebar: nama penanda
                                tangan dan gambar tanda tangannya harus berdiri
                                sebagai kolom terpisah supaya jelas kolom mana nama
                                dan kolom mana tanda tangan, dan supaya yang sudah
                                lewat tahap bisa dibandingkan berdampingan dalam
                                satu baris.

                                Di layar sempit `.neu-table-wrap` mengubah tabel ini
                                jadi satu kartu per record yang menumpuk ke bawah,
                                jadi tidak ada yang perlu digeser ke samping. Karena
                                itu `thead` disembunyikan lewat CSS dan nama
                                kolomnya dipindahkan ke atas tiap sel.
                            */
                            <div className="neu-table-wrap">
                                <table className="neu-table">
                                    <caption className="sr-only">
                                        PM yang menunggu tindakan Anda. Isi kolom Nama
                                        Penanda Tangan dan Tanda Tangan pada baris yang
                                        akan disetujui.
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Mesin</th>
                                            <th scope="col">Periode</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Nama Penanda Tangan</th>
                                            <th scope="col">Tanda Tangan</th>
                                            <th scope="col">Catatan</th>
                                            <th scope="col">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {records.map((record) => (
                                            <ApprovalRow key={record.id} record={record} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
