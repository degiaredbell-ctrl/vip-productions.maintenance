import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuRing from '@/Components/NeuRing';
import NeuPill from '@/Components/NeuPill';
import { Link } from '@inertiajs/react';

export default function Dashboard({ auth, machines, stats, periods, currentPeriod, currentYear, search, statusFilter, isFuturePeriod }) {
    const { data, get, processing } = useForm({
        period: currentPeriod,
        year: currentYear,
        q: search,
        status: statusFilter,
    });

    const handleSearch = (e) => {
        data.q = e.target.value;
        get(route('dashboard'), { data: { ...data, q: e.target.value }, preserveState: true, replace: true });
    };

    const handlePeriodChange = (period) => {
        get(route('dashboard'), { data: { ...data, period }, preserveState: true, replace: true });
    };

    const handleStatusFilter = (status) => {
        get(route('dashboard'), { data: { ...data, status }, preserveState: true, replace: true });
    };

    const percentage = stats.total > 0 ? Math.round(stats.done / stats.total * 100) : 0;

    const statusLabels = { done: 'Selesai', todo: 'Belum', issue: 'Kendala' };
    const statusVariants = { done: 'done', todo: 'todo', issue: 'issue' };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                {/* Header */}
                <div className="mb-5">
                    <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                    <h1 className="text-xl font-bold">Preventive Maintenance</h1>
                </div>

                {/* Summary */}
                <NeuCard className="mb-4">
                    <div className="flex items-center gap-5">
                        <NeuRing percentage={percentage} />
                        <div className="flex-1 grid grid-cols-3 gap-2.5">
                            {['done', 'todo', 'issue'].map((key) => (
                                <div key={key} className="neu-inset py-3 px-2 text-center">
                                    <span className={`text-xl font-bold block ${key === 'done' ? 'text-neu-accent' : key === 'todo' ? 'text-neu-warn' : 'text-neu-bad'}`}>
                                        {stats[key]}
                                    </span>
                                    <span className="text-[11px] text-neu-sub">{statusLabels[key]}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </NeuCard>

                {/* Period chips */}
                <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-2" role="group" aria-label="Periode">
                    {periods.map((p) => (
                        <NeuChip
                            key={p.value}
                            active={data.period === p.value}
                            onClick={() => handlePeriodChange(p.value)}
                        >
                            {p.label}
                        </NeuChip>
                    ))}
                </div>

                {/* Search */}
                <div className="mb-3">
                    <input
                        type="search"
                        placeholder="Cari kode atau nama mesin…"
                        value={data.q}
                        onChange={handleSearch}
                        className="neu-input"
                        aria-label="Cari mesin"
                    />
                </div>

                {/* Status filter */}
                <div className="flex gap-2.5 overflow-x-auto px-1.5 py-2 mb-3" role="group" aria-label="Filter status">
                    {[
                        { value: 'all', label: 'Semua' },
                        { value: 'todo', label: 'Belum' },
                        { value: 'done', label: 'Selesai' },
                        { value: 'issue', label: 'Kendala' },
                    ].map((f) => (
                        <NeuChip
                            key={f.value}
                            active={data.status === f.value}
                            onClick={() => handleStatusFilter(f.value)}
                        >
                            {f.label}
                        </NeuChip>
                    ))}
                </div>

                {/* Future period warning */}
                {isFuturePeriod && (
                    <NeuCard className="mb-4 !shadow-neu-in">
                        <p className="text-sm text-neu-warn font-semibold">Periode ini belum tiba dan tidak dapat diisi.</p>
                    </NeuCard>
                )}

                {/* Machine list */}
                <div className="grid gap-0 lg:grid-cols-2 lg:gap-x-5">
                    {machines.length > 0 ? machines.map((machine) => (
                        <Link
                            key={machine.id}
                            href={route('machines.pm.create', { machine: machine.id, period: data.period, year: data.year })}
                            className="flex items-center gap-3.5 w-full text-left p-3.5 rounded-[20px] shadow-neu-up mb-4 active:shadow-neu-in transition-shadow"
                        >
                            <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">
                                {machine.code}
                            </span>
                            <div className="min-w-0">
                                <b className="block text-sm truncate">{machine.name}</b>
                                <span className="text-xs text-neu-sub">Minggu {machine.week_group} · {machine.type}</span>
                            </div>
                            <NeuPill variant={statusVariants[machine.status]} className="ml-auto">
                                {statusLabels[machine.status]}
                            </NeuPill>
                        </Link>
                    )) : (
                        <p className="text-neu-sub text-center py-6 col-span-2">Tidak ada mesin yang cocok.</p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
