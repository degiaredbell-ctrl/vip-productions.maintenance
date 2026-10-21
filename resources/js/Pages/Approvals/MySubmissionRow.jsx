import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';

const STATUS_VARIANTS = { todo: 'todo', progress: 'progress', done: 'done', issue: 'issue' };

const CATEGORY_STYLES = {
    machine: 'text-neu-info bg-neu-info/15 ring-neu-info/30',
    utility: 'text-neu-warn bg-neu-warn/15 ring-neu-warn/30',
};

function stepState(stage, record) {
    if (stage.rejected) return 'rejected';
    if (stage.signed) return 'done';
    if (stage.value === record.awaiting) return 'current';
    return 'pending';
}

const DOT_STYLES = {
    done: 'text-neu-accent',
    current: 'text-neu-info ring-2 ring-neu-info/50',
    rejected: 'text-neu-bad ring-2 ring-neu-bad/40',
    pending: 'text-neu-sub/60',
};

const DOT_TEXT = { done: '✓', rejected: '✕' };

const STATE_CAPTION = {
    done: null,
    current: 'Sedang diproses',
    rejected: 'Ditolak',
    pending: 'Belum sampai',
};

/**
 * Progres persetujuan PM milik pembuatnya: Teknisi → User PIC → Atasan.
 * Hanya tampilan; tanda tangan tetap dilakukan di form checklist.
 */
function ApprovalProgress({ record }) {
    return (
        <ol className="flex items-start gap-1" aria-label="Progres persetujuan">
            {record.stages.map((stage, index) => {
                const state = stepState(stage, record);
                const isLast = index === record.stages.length - 1;
                const caption = stage.value === 'pic' && state === 'current' && record.assigned_pic
                    ? record.assigned_pic
                    : STATE_CAPTION[state];

                return (
                    <li key={stage.value} className="flex items-start gap-1 min-w-0 flex-1">
                        <div className="flex flex-col items-center text-center min-w-0 flex-1">
                            <span
                                className={`neu-inset w-7 h-7 flex-none grid place-items-center text-[11px] font-bold ${DOT_STYLES[state]}`}
                                aria-hidden="true"
                            >
                                {DOT_TEXT[state] ?? stage.order}
                            </span>
                            <b className={`mt-1 text-[11px] leading-tight ${state === 'pending' ? 'text-neu-sub' : 'text-neu-text'}`}>
                                {stage.short_label}
                            </b>
                            <span
                                className={`text-[10px] leading-tight break-words ${state === 'current' ? 'text-neu-info font-semibold' : state === 'rejected' ? 'text-neu-bad font-semibold' : 'text-neu-sub'}`}
                                title={stage.signed ? `${stage.signed_by_name} · ${stage.signed_at}` : undefined}
                            >
                                {stage.signed ? stage.signed_by_name : caption}
                            </span>
                            {stage.signed && (
                                <span className="text-[10px] leading-tight text-neu-sub/80">{stage.signed_at}</span>
                            )}
                            <span className="sr-only">
                                {stage.short_label}: {stage.signed ? `ditandatangani ${stage.signed_by_name}` : STATE_CAPTION[state]}
                            </span>
                        </div>
                        {!isLast && (
                            <span
                                className={`mt-3.5 h-0.5 w-4 flex-none rounded ${stage.signed ? 'bg-neu-accent/60' : 'bg-neu-dark/30'}`}
                                aria-hidden="true"
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

export default function MySubmissionRow({ record }) {
    return (
        <tr>
            <td className="neu-cell-lead">
                <div className="flex items-start gap-3">
                    <span className="neu-inset w-11 h-11 flex-none grid place-items-center font-bold text-[11px] text-neu-accent">
                        {record.sub_category ?? '—'}
                    </span>
                    <div className="min-w-0">
                        <b className="block text-sm break-words">{record.name}</b>
                        <span className="block text-xs text-neu-sub break-words">
                            {record.code}
                            {record.location && ` · ${record.location}`}
                        </span>
                        <span
                            className={`mt-1 inline-flex items-center rounded-[10px] px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset ${CATEGORY_STYLES[record.category] ?? 'text-neu-sub bg-neu-bg ring-neu-dark/40'}`}
                        >
                            {record.category_label}
                        </span>
                    </div>
                </div>
            </td>

            <td data-label="Periode">
                <span className="block text-xs">
                    {record.period_label} {record.year}
                </span>
                <span className="block text-xs text-neu-sub">Diperbarui {record.updated_at}</span>
            </td>

            <td data-label="Status">
                <NeuPill variant={STATUS_VARIANTS[record.status] ?? 'todo'}>
                    {record.status_label}
                </NeuPill>
                <span className="block text-xs text-neu-sub mt-1.5">
                    {record.is_rejected
                        ? 'Dikembalikan untuk revisi'
                        : record.is_final
                            ? 'Semua tahap selesai'
                            : <>Approval by <b className="text-neu-text">{record.awaiting_label}</b></>}
                </span>
                {record.revision_count > 0 && (
                    <span className="block text-xs text-neu-sub">Revisi {record.revision_count}×</span>
                )}
            </td>

            <td data-label="Progres Persetujuan" className="min-w-[230px]">
                <ApprovalProgress record={record} />
                {record.rejection && (
                    <p className="mt-2 text-[11px] text-neu-bad break-words">
                        Ditolak{record.rejection.by && ` oleh ${record.rejection.by}`}
                        {record.rejection.at && ` · ${record.rejection.at}`}
                        {record.rejection.reason && (
                            <span className="block text-neu-sub italic">“{record.rejection.reason}”</span>
                        )}
                    </p>
                )}
            </td>

            <td data-label="Aksi">
                <NeuButton
                    href={route('machines.pm.create', {
                        machine: record.machine_id,
                        year: record.year,
                        period: record.period,
                        return: 'approvals',
                        tab: 'mine',
                    })}
                >
                    {record.is_rejected ? 'Revisi Checklist' : 'Lihat Checklist'}
                </NeuButton>
            </td>
        </tr>
    );
}
