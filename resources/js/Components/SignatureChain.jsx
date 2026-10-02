import NeuPill from '@/Components/NeuPill';

/**
 * Stepper tiga tahap persetujuan: technisi -> User PIC -> Atasan.
 *
 * Menampilkan tahap yang sudah ditandatangani beserta nama, waktu, dan
 * gambarannya. Komponen ini murni presentational; aksi tanda tangan ada di
 * halaman form PM.
 */
export default function SignatureChain({ stages, awaiting, statusLabel, awaitingLabel }) {
    if (!stages?.length) return null;

    const awaitingOrder = stages.find((s) => s.value === awaiting)?.order;

    return (
        <div className="space-y-3">
            {statusLabel && (
                <div className="neu-inset px-3.5 py-3">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-[11px] font-bold text-neu-sub">STATUS</span>
                        <NeuPill variant={awaiting ? 'progress' : 'done'}>{statusLabel}</NeuPill>
                    </div>
                    {awaiting && awaitingLabel && (
                        <p className="text-[11px] text-neu-sub mt-2">
                            Menunggu <b className="text-neu-text">{awaitingLabel}</b>
                        </p>
                    )}
                </div>
            )}

            <ol className="space-y-2.5" aria-label="Rantai persetujuan">
                {stages.map((stage) => {
                    const isDone = stage.signed;
                    const isCurrent = stage.value === awaiting;
                    const isPassed = isDone && !isCurrent;

                    return (
                        <li key={stage.value} className="flex items-start gap-3">
                            <span
                                className={[
                                    'neu-inset w-7 h-7 flex-none grid place-items-center text-[11px] font-bold',
                                    isDone ? 'text-neu-accent' : 'text-neu-sub',
                                    isCurrent ? 'ring-2 ring-neu-accent/40' : '',
                                ].filter(Boolean).join(' ')}
                                aria-hidden="true"
                            >
                                {isDone ? '✓' : stage.order}
                            </span>

                            <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between gap-2">
                                    <b className={`text-xs ${isDone ? 'text-neu-text' : 'text-neu-sub'}`}>
                                        {stage.label}
                                    </b>
                                    {isCurrent && <NeuPill variant="progress">Menunggu</NeuPill>}
                                    {isPassed && <NeuPill variant="done">Selesai</NeuPill>}
                                </div>

                                {isDone && (
                                    <>
                                        <p className="text-[11px] text-neu-sub mt-0.5">
                                            {stage.signed_by_name}
                                            {stage.signed_at && ` · ${stage.signed_at}`}
                                        </p>
                                        {stage.image_url && (
                                            <img
                                                src={stage.image_url}
                                                alt={`Tanda tangan ${stage.short_label}`}
                                                className="mt-1.5 h-11 rounded-neu-sm bg-neu-light shadow-neu-in object-contain"
                                                loading="lazy"
                                            />
                                        )}
                                        {stage.note && (
                                            <p className="text-[11px] text-neu-sub mt-1 italic">“{stage.note}”</p>
                                        )}
                                    </>
                                )}

                                {!isDone && !isCurrent && awaitingOrder && stage.order > awaitingOrder && (
                                    <p className="text-[11px] text-neu-sub/60 mt-0.5">Belum sampai</p>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
