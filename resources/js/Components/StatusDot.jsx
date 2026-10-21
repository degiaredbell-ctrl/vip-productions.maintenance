/**
 * Indikator status unit: dot hijau berdenyut untuk Aktif, merah berdenyut
 * untuk Nonaktif. Teks status tetap ada untuk pembaca layar dan tooltip.
 */
export default function StatusDot({ active, className = '' }) {
    const label = active ? 'Aktif' : 'Nonaktif';

    return (
        <span
            className={`neu-status-dot ${active ? 'neu-status-dot-on' : 'neu-status-dot-off'} ${className}`}
            title={label}
            role="img"
            aria-label={`Status: ${label}`}
        />
    );
}
