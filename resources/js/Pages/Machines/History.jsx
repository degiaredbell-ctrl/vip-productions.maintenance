import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuPill from '@/Components/NeuPill';
import NeuButton from '@/Components/NeuButton';

export default function History({ auth, machine, records }) {
    const statusLabels = {
        draft: 'Menunggu Tanda Tangan',
        submitted: 'On Progress Approval by User PIC',
        pic_approved: 'On Progress Approval by Atasan',
        approved: 'Selesai',
        rejected: 'Perlu Revisi',
    };
    const statusVariants = {
        draft: 'todo',
        submitted: 'progress',
        pic_approved: 'progress',
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
                                    {record.approved_at && <p>Disetujui penuh: {record.approved_at}</p>}
                                </div>

                                {record.reject_reason && (
                                    <p className="text-xs text-neu-bad mt-2">
                                        Alasan ditolak: {record.reject_reason}
                                    </p>
                                )}

                                {/* Jejak tanda tangan per tahap, supaya riwayat
                                    menunjukkan siapa sudah menyetujui dan kapan. */}
                                {record.signatures?.some((s) => s.signed_by_name) && (
                                    <div className="mt-3 pt-3 border-t border-neu-dark/40 space-y-1">
                                        {record.signatures.filter((s) => s.signed_by_name).map((s) => (
                                            <div key={s.stage} className="flex items-center justify-between text-xs gap-2">
                                                <span className="text-neu-sub">TT {s.label}</span>
                                                <span className="text-neu-text text-right truncate">
                                                    {s.signed_by_name}
                                                    {s.signed_at && ` · ${s.signed_at}`}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
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
