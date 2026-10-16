import { useEffect, useState } from 'react';

/**
 * Peringatan unit yang melewati batas minggu PM-nya: banner tetap di atas
 * daftar + notifikasi mengambang sekali saat halaman dibuka.
 */
export default function OverdueAlert({ count = 0, unitLabel = 'mesin', currentWeek = null, periodLabel = '', active = false, onShow, onClear }) {
    const [toastOpen, setToastOpen] = useState(count > 0);

    useEffect(() => {
        if (!toastOpen) return undefined;
        const timer = setTimeout(() => setToastOpen(false), 7000);
        return () => clearTimeout(timer);
    }, [toastOpen]);

    if (count <= 0) return null;

    const message = `${count} ${unitLabel} melewati batas waktu maintenance`;

    return (
        <>
            {toastOpen && (
                <div role="alert" className="fixed z-50 left-1/2 -translate-x-1/2 top-4 md:top-6 w-[calc(100%-2rem)] max-w-sm">
                    <div className="flex items-start gap-3 rounded-[20px] bg-neu-bg shadow-neu-up px-4 py-3.5 text-neu-bad neu-card-overdue">
                        <span className="mt-0.5 h-5 w-5 flex-none grid place-items-center rounded-full bg-neu-bad text-white text-xs font-bold" aria-hidden="true">!</span>
                        <p className="text-sm font-semibold flex-1 leading-snug">{message}</p>
                        <button
                            type="button"
                            onClick={() => setToastOpen(false)}
                            aria-label="Tutup notifikasi"
                            className="flex-none grid place-items-center h-6 w-6 rounded-full text-neu-sub hover:shadow-neu-in"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
            )}

            <div role="status" className="neu-card-overdue rounded-[20px] shadow-neu-up-sm p-4 mb-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <span className="h-9 w-9 flex-none grid place-items-center rounded-full bg-neu-bad text-white font-bold" aria-hidden="true">!</span>
                <div className="flex-1 min-w-0">
                    <b className="block text-sm text-neu-bad">{message}</b>
                    <span className="block text-xs text-neu-sub">
                        {currentWeek ? `Saat ini Minggu ke-${currentWeek} periode ${periodLabel}. ` : `Periode ${periodLabel} sudah berakhir. `}
                        PM yang belum dikerjakan sesuai jadwal minggunya ditandai merah.
                    </span>
                </div>
                <button
                    type="button"
                    onClick={active ? onClear : onShow}
                    className="neu-btn !min-h-[36px] !py-1.5 !px-4 text-xs font-semibold text-neu-bad self-start sm:self-center"
                >
                    {active ? 'Tampilkan semua' : 'Lihat yang terlambat'}
                </button>
            </div>
        </>
    );
}
