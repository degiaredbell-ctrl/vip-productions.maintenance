import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState } from 'react';

/**
 * Pad tanda tangan tulisan tangan di atas <canvas>.
 *
 * Pakai Pointer Events (bukan onMouseDown) supaya mouse, sentuhan, dan stylus
 * bisa dipakai dengan satu jalur kode, dan supaya pena tidak who'd_scroll halaman
 * saat dipakai menggambar di layar sentuh.
 *
 * Parent memakai ref untuk mengambil gambar lewat getDataUrl() ketika tombol
 * submit ditekan.
 *
 * `id` harus unik per halaman: daftar Persetujuan bisa memuat banyak pad dalam
 * satu layar, dan `htmlFor` yang semuanya menunjuk ke satu id membuat label
 * hanya menempel ke pad pertama.
 */
const NeuSignaturePad = forwardRef(function NeuSignaturePad(
    { disabled = false, label = 'Tanda Tangan', id = 'signature-pad', onChange, error = false },
    ref,
) {
    const canvasRef = useRef(null);
    const drawing = useRef(false);
    const last = useRef(null);
    const hasInk = useRef(false);
    const [empty, setEmpty] = useState(true);

    // Memperhitungkan ukuran buffer canvas mengikuti devicePixelRatio supaya garis
    // tidak pecah di layar retina/tablet. Memanggil ulang fungsi ini menghapus
    // isi kanvas (set width/height me-reset bitmap), makanya pemanggil harus
    // menyimpan lalu menggambar ulang tintanya.
    const prepareCanvas = useCallback(() => {
        const canvas = canvasRef.current;
        if (!canvas) return null;

        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();

        // Kanvas yang sudah lepas dari DOM (panel aksi langsung hilang begitu
        // tanda tangan tersimpan) berukuran 0x0. Kembalikan null, bukan object
        // dengan ctx/rect kosong, supaya pemanggil cukup memeriksa satu nilai.
        if (rect.width === 0 || rect.height === 0) return null;

        canvas.width = Math.round(rect.width * dpr);
        canvas.height = Math.round(rect.height * dpr);

        const ctx = canvas.getContext('2d');
        if (!ctx) return null;

        ctx.scale(dpr, dpr);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#2B3445';

        return { ctx, rect };
    }, []);

    // Menyesuaikan buffer canvas saat ukuran berubah. Isi kanvas yang sudah
    // digambar ikut dipertahankan lewat salinan bitmap, karena menyetel ulang
    // width/height akan menghapus semua tinta.
    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        prepareCanvas();

        const observer = new ResizeObserver(() => {
            const previous = hasInk.current ? canvas.toDataURL('image/png') : null;
            const prepared = prepareCanvas();

            if (!previous || !prepared) return;

            const { ctx, rect } = prepared;
            const image = new Image();
            image.onload = () => ctx.drawImage(image, 0, 0, rect.width, rect.height);
            image.src = previous;
        });

        observer.observe(canvas);

        return () => observer.disconnect();
    }, [prepareCanvas]);

    useImperativeHandle(ref, () => ({
        getDataUrl: () => {
            const canvas = canvasRef.current;
            return canvas && hasInk.current ? canvas.toDataURL('image/png') : null;
        },
        clear: () => {
            const canvas = canvasRef.current;
            const ctx = canvas?.getContext('2d');
            if (canvas && ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasInk.current = false;
            setEmpty(true);
            onChange?.(null);
        },
        isEmpty: () => !hasInk.current,
    }), [onChange]);

    const pointFromEvent = (event) => {
        const rect = canvasRef.current?.getBoundingClientRect();
        if (!rect) return { x: 0, y: 0 };

        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    };

    const handlePointerDown = (event) => {
        if (disabled) return;
        // Cegah browser membuat gulir/zoom saat jari bergerak menggambar.
        event.preventDefault();
        canvasRef.current?.setPointerCapture?.(event.pointerId);
        drawing.current = true;
        last.current = pointFromEvent(event);
        hasInk.current = true;
        setEmpty(false);
    };

    const handlePointerMove = (event) => {
        if (!drawing.current || disabled) return;
        event.preventDefault();

        const ctx = canvasRef.current?.getContext('2d');
        if (!ctx || !last.current) return;

        const point = pointFromEvent(event);

        ctx.beginPath();
        ctx.moveTo(last.current.x, last.current.y);
        ctx.lineTo(point.x, point.y);
        ctx.stroke();

        last.current = point;
    };

    const stopDrawing = (event) => {
        if (!drawing.current) return;
        drawing.current = false;
        last.current = null;
        canvasRef.current?.releasePointerCapture?.(event.pointerId);
    };

    const handleClear = () => {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d');
        if (canvas && ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasInk.current = false;
        setEmpty(true);
        onChange?.(null);
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-1.5">
                <label htmlFor={id} className="block text-xs text-neu-sub">
                    {label} <span className="text-neu-bad">*</span>
                </label>
                <button
                    type="button"
                    onClick={handleClear}
                    disabled={disabled || empty}
                    className="text-[11px] font-semibold text-neu-sub transition-colors duration-150 hover:text-neu-bad focus:outline-none focus:ring-2 focus:ring-neu-accent/40 rounded px-1 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Bersihkan
                </button>
            </div>

            <div className={`neu-inset relative ${error ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]' : ''}`}>
                <canvas
                    id={id}
                    ref={canvasRef}
                    onPointerDown={handlePointerDown}
                    onPointerMove={handlePointerMove}
                    onPointerUp={stopDrawing}
                    onPointerCancel={stopDrawing}
                    onPointerLeave={stopDrawing}
                    className={`block w-full h-[140px] rounded-neu-sm touch-none ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-crosshair'}`}
                    role="img"
                    aria-label="Area menggambar tanda tangan"
                />
                {empty && (
                    <span className="absolute inset-0 grid place-items-center text-xs text-neu-sub/70 pointer-events-none">
                        Tanda tangani di sini
                    </span>
                )}
            </div>

            {/* Garis dasar, memberi kesan seperti kertas bertanda tangan. */}
            <div className="h-px bg-neu-dark/50 mt-3 mx-6" aria-hidden="true" />
        </div>
    );
});

export default NeuSignaturePad;
