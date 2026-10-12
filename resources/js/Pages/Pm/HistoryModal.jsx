import { Link } from '@inertiajs/react';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';

const STATUS_LABELS = {
    draft: 'Menunggu Tanda Tangan',
    submitted: 'Sedang Approval (User PIC)',
    pic_approved: 'Sedang Approval (Atasan)',
    approved: 'Selesai',
    rejected: 'Perlu Revisi',
};

const STATUS_VARIANTS = {
    draft: 'todo',
    submitted: 'progress',
    pic_approved: 'progress',
    approved: 'done',
    rejected: 'issue',
};

export default function PmHistoryModal({ 
    machine, 
    record, 
    period, 
    year, 
    error,
    onClose 
}) {
    if (error && !record) {
        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <NeuCard className="max-w-md w-full">
                    <div className="text-center py-6">
                        <p className="text-neu-bad font-semibold">{error}</p>
                        <p className="text-xs text-neu-sub mt-1">Periode {period} {year}</p>
                        <NeuButton onClick={onClose} className="mt-4 w-full">Tutup</NeuButton>
                    </div>
                </NeuCard>
            </div>
        );
    }

    if (!record) {
        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <NeuCard className="max-w-md w-full">
                    <div className="text-center py-6">
                        <p className="text-neu-sub">Memuat riwayat...</p>
                    </div>
                </NeuCard>
            </div>
        );
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <NeuCard className="max-w-lg w-full max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-4">
                <div>
                    <h2 className="text-lg font-bold">{machine.name}</h2>
                    <p className="text-sm text-neu-sub">
                        {machine.code} · Periode {period} {year}
                    </p>
                </div>
                <NeuButton onClick={onClose} variant="ghost" className="!min-h-[36px] !px-3 !py-1.5 text-xs">
                    ×
                </NeuButton>
            </div>

            <div className="mb-4">
                <NeuPill variant={STATUS_VARIANTS[record.status] ?? 'todo'}>
                    {STATUS_LABELS[record.status] ?? record.status}
                </NeuPill>
                <span className="ml-2 text-xs text-neu-sub">
                    Rev: {record.revision_count}x · Teknisi: {record.technician_name || '-'}
                </span>
            </div>

            {record.general_note && (
                <NeuCard className="mb-4 !shadow-neu-in">
                    <p className="text-xs text-neu-sub">Catatan: {record.general_note}</p>
                </NeuCard>
            )}

            <div className="space-y-3">
                {record.timeline?.map((event, idx) => {
                    const colorClass = event.type === 'created' ? 'text-neu-accent' :
                                      event.type === 'rejected' ? 'text-neu-bad' :
                                      event.type === 'signed' ? 'text-neu-info' :
                                      'text-neu-sub';
                    return (
                        <div key={idx} className="flex flex-col gap-1.5">
                            <div className="flex items-start gap-3">
                                <span className="neu-inset w-7 h-7 flex-none grid place-items-center text-[10px] font-bold text-neu-sub">
                                    {idx + 1}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="font-semibold text-sm">[{event.time}]</span>
                                        <span className={`text-sm ${colorClass}`}>
                                            {event.label}
                                        </span>
                                        {event.by && (
                                            <span className="text-xs text-neu-sub">oleh {event.by}</span>
                                        )}
                                    </div>
                                    {event.detail && (
                                        <p className="text-xs text-neu-sub mt-1 whitespace-pre-wrap">{event.detail}</p>
                                    )}
                                    {event.note && (
                                        <p className="text-xs text-neu-sub mt-1 italic">Catatan: {event.note}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="mt-4 flex justify-end gap-2">
                <Link
                    href={route('machines.pm.create', {
                        machine: machine.id,
                        period,
                        year,
                    })}
                    className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs"
                >
                    Buka Form PM
                </Link>
                <NeuButton onClick={onClose} className="!min-h-[36px] !px-3 !py-1.5 text-xs">
                    Tutup
                </NeuButton>
            </div>
        </NeuCard>
    </div>
    );
}