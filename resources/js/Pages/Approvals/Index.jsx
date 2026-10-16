import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import ApprovalRow from '@/Pages/Approvals/ApprovalRow';
import MySubmissionRow from '@/Pages/Approvals/MySubmissionRow';

export default function Approvals({ auth, records, mySubmissions = [], canSubmit = false, years, year, period, periods, myStages, hasStages }) {
    const [selectedYear, setSelectedYear] = useState(year);
    const [selectedPeriod, setSelectedPeriod] = useState(period);
    // Switch kategori PM: default "Mesin". Daftar difilter di sisi klien
    // supaya perpindahan Mesin/Utility terasa instan — semua record untuk
    // tahun/periode ini sudah terkirim dalam satu muatan.
    const [category, setCategory] = useState('machine');
    const showMine = canSubmit || mySubmissions.length > 0;
    // Tab "PM Saya" dibuka langsung kalau tidak ada yang menunggu tindakan
    // tetapi ada PM buatan user ini yang sedang diproses.
    const [view, setView] = useState(() =>
        !hasStages || (records.length === 0 && mySubmissions.length > 0) ? 'mine' : 'queue'
    );

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

    const activeList = view === 'mine' ? mySubmissions : records;
    const machineCount = activeList.filter((record) => record.category === 'machine').length;
    const utilityCount = activeList.filter((record) => record.category === 'utility').length;
    const visibleRecords = activeList.filter((record) => record.category === category);
    const categoryLabel = category === 'utility' ? 'Utility' : 'Mesin';
    const inProgressCount = mySubmissions.filter((record) => record.status === 'progress').length;

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl sm:text-2xl font-bold">Persetujuan</h1>
                </div>

                {!hasStages && !showMine ? (
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
                        {hasStages && showMine && (
                            <div className="flex flex-wrap gap-2.5 px-1.5 py-2 mb-2" role="tablist" aria-label="Tampilan persetujuan">
                                <NeuChip
                                    role="tab"
                                    aria-selected={view === 'queue'}
                                    active={view === 'queue'}
                                    onClick={() => setView('queue')}
                                >
                                    Perlu Tindakan Saya ({records.length})
                                </NeuChip>
                                <NeuChip
                                    role="tab"
                                    aria-selected={view === 'mine'}
                                    active={view === 'mine'}
                                    onClick={() => setView('mine')}
                                >
                                    Status PM Saya ({mySubmissions.length})
                                </NeuChip>
                            </div>
                        )}

                        {view === 'queue' ? (
                            <NeuCard className="mb-4 !shadow-neu-in">
                                <p className="text-xs text-neu-sub mb-1.5">Tahap yang menunggu Anda</p>
                                <div className="flex flex-wrap gap-2">
                                    {myStages.map((stage) => (
                                        <NeuPill key={stage.value} variant="progress">{stage.label}</NeuPill>
                                    ))}
                                </div>
                            </NeuCard>
                        ) : (
                            <NeuCard className="mb-4 !shadow-neu-in">
                                <p className="text-xs text-neu-sub">
                                    PM yang Anda isi dan tanda tangani. Pantau apakah sudah
                                    sampai tahap <b className="text-neu-text">Approval by User PIC</b> atau{' '}
                                    <b className="text-neu-text">Approval by Atasan</b>.
                                </p>
                                {inProgressCount > 0 && (
                                    <p className="text-xs mt-1.5">
                                        <NeuPill variant="progress">{inProgressCount} sedang dalam proses approval</NeuPill>
                                    </p>
                                )}
                            </NeuCard>
                        )}

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

                        <div
                            className="flex flex-wrap gap-2.5 px-1.5 py-2 mb-1 lg:flex-nowrap lg:overflow-x-auto"
                            role="group"
                            aria-label="Filter kategori"
                        >
                            <NeuChip
                                active={category === 'machine'}
                                onClick={() => setCategory('machine')}
                            >
                                Mesin ({machineCount})
                            </NeuChip>
                            <NeuChip
                                active={category === 'utility'}
                                onClick={() => setCategory('utility')}
                            >
                                Utility ({utilityCount})
                            </NeuChip>
                        </div>

                        <p className="text-xs text-neu-sub mb-3">
                            {view === 'mine'
                                ? `${visibleRecords.length} PM ${categoryLabel} buatan Anda`
                                : `${visibleRecords.length} PM ${categoryLabel} menunggu tindakan Anda`}
                        </p>

                        {visibleRecords.length === 0 ? (
                            <NeuCard>
                                <p className="text-sm text-neu-sub text-center py-4">
                                    {view === 'mine'
                                        ? `Belum ada PM ${categoryLabel} buatan Anda yang dikirim ke persetujuan pada filter ini.`
                                        : `Tidak ada PM ${categoryLabel} yang menunggu persetujuan Anda pada filter ini.`}
                                </p>
                            </NeuCard>
                        ) : view === 'mine' ? (
                            <div className="neu-table-wrap">
                                <table className="neu-table">
                                    <caption className="sr-only">
                                        Status persetujuan PM yang Anda buat.
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Mesin</th>
                                            <th scope="col">Periode</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Progres Persetujuan</th>
                                            <th scope="col">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleRecords.map((record) => (
                                            <MySubmissionRow key={record.id} record={record} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            /*
                                Lima kolom ini muat di layar sempit, tapi tetap
                                dirakit ulang jadi kartu di bawah 1024px oleh
                                `.neu-table-wrap`: `thead` disembunyikan, tiap sel
                                jadi blok penuh, dan nama kolomnya dipindahkan ke
                                atas sel lewat `data-label`. Hasilnya halaman
                                turun ke bawah dan tidak pernah melebar ke kanan.

                                Penandatanganan sengaja tidak ada di sini: daftar ini
                                hanya memberi jalan masuk ke form checklist tempat
                                approve/reject dilakukan.
                            */
                            <div className="neu-table-wrap">
                                <table className="neu-table">
                                    <caption className="sr-only">
                                        PM yang menunggu tindakan Anda. Buka checklist
                                        untuk memeriksa isinya lalu menyetujui atau
                                        menolak dari sana.
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Mesin</th>
                                            <th scope="col">Kategori</th>
                                            <th scope="col">Periode</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleRecords.map((record) => (
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
