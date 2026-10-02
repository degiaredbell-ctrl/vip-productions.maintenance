import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuPill from '@/Components/NeuPill';
import NeuButton from '@/Components/NeuButton';

export default function History({ auth, machine, records }) {
    const statusLabels = {
        draft: 'Draft',
        submitted: 'Terkirim',
        approved: 'Disetujui',
        rejected: 'Ditolak',
    };
    const statusVariants = {
        draft: 'todo',
        submitted: 'todo',
        approved: 'done',
        rejected: 'issue',
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                <div className="flex items-center gap-3 mb-5">
                    <NeuButton href={route('machines.index')} className="!px-3">
                        ‹ Kembali
                    </NeuButton>
                    <div>
                        <h1 className="text-xl font-bold">{machine.name}</h1>
                        <p className="text-sm text-neu-sub">
                            {machine.code}
                            {machine.sub_category && ` · ${machine.sub_category}`}
                            {machine.location && ` · ${machine.location}`}
                            {` · Minggu ${machine.week_group} · Riwayat PM`}
                        </p>
                    </div>
                </div>

                {records.length === 0 ? (
                    <NeuCard>
                        <p className="text-neu-sub text-center py-4">Belum ada riwayat PM.</p>
                    </NeuCard>
                ) : (
                    <div className="space-y-3">
                        {records.map((record) => (
                            <NeuCard key={record.id}>
                                <div className="flex items-center justify-between mb-2">
                                    <div>
                                        <b className="text-sm">{record.period} {record.year}</b>
                                        <span className="text-xs text-neu-sub ml-2">
                                            {record.technician_name || '-'}
                                        </span>
                                    </div>
                                    <NeuPill variant={statusVariants[record.status]}>
                                        {statusLabels[record.status]}
                                    </NeuPill>
                                </div>
                                <div className="text-xs text-neu-sub space-y-0.5">
                                    <p>Revisi: {record.revision_count}x</p>
                                    {record.submitted_at && <p>Submit: {record.submitted_at}</p>}
                                    {record.approved_at && <p>Approved: {record.approved_at}</p>}
                                </div>
                                {record.items.length > 0 && (
                                    <div className="mt-3 space-y-1.5">
                                        {record.items.map((item, i) => (
                                            <div key={i} className="flex items-center justify-between text-xs">
                                                <span className="text-neu-text">{item.item_name}</span>
                                                <span className="text-neu-sub">
                                                    {item.actual}
                                                    {item.act_replace && ` · ${item.parts_replaced} part`}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </NeuCard>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
