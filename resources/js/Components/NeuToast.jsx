/**
 * Notifikasi mengambang untuk pesan flash Laravel (sukses / gagal).
 * Ditaruh di atas navbar dan konten, hilang sendiri setelah beberapa detik.
 */
export default function NeuToast({ toast, onDismiss }) {
    if (!toast) return null;

    const isError = toast.variant === 'issue';

    return (
        <div
            role="status"
            aria-live="polite"
            data-toast
            className="fixed z-50 left-1/2 -translate-x-1/2 top-4 lg:top-6 w-[calc(100%-2rem)] max-w-sm"
        >
            <div
                className={`flex items-start gap-3 rounded-[20px] shadow-neu-up px-4 py-3.5 ${
                    isError ? 'text-neu-bad' : 'text-neu-accent'
                }`}
            >
                <span
                    className={`mt-0.5 h-5 w-5 flex-none grid place-items-center rounded-full text-xs font-bold ${
                        isError ? 'bg-neu-bad/15' : 'bg-neu-accent/15'
                    }`}
                    aria-hidden="true"
                >
                    {isError ? '!' : '✓'}
                </span>
                <p className="text-sm font-semibold flex-1 leading-snug">{toast.message}</p>
                <button
                    type="button"
                    onClick={onDismiss}
                    aria-label="Tutup notifikasi"
                    className="flex-none grid place-items-center h-6 w-6 rounded-full text-neu-sub transition-all duration-150 hover:shadow-neu-in focus:outline-none focus:ring-2 focus:ring-neu-accent/40"
                >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
