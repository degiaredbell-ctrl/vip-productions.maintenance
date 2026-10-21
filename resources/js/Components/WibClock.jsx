import { useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';

export const WIB = 'Asia/Jakarta';

const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: WIB,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
});

function parts(ms) {
    const p = Object.fromEntries(formatter.formatToParts(new Date(ms)).map((x) => [x.type, x.value]));

    return {
        date: `${p.day}/${p.month}/${p.year}`,
        time: `${p.hour}:${p.minute}:${p.second}`,
    };
}

/**
 * Jam WIB berjalan: "Tanggal DD/MM/YYYY : Waktu HH:MM:SS".
 *
 * Dihitung dari jam server (`serverNow`) ditambah selisih waktu sejak halaman
 * dimuat, jadi jam perangkat yang meleset tidak ikut ditampilkan. State detik
 * hanya ada di komponen ini supaya layout tidak dirender ulang tiap detik.
 */
export default function WibClock({ className = '' }) {
    const { serverNow } = usePage().props;
    const offset = useMemo(() => (serverNow ? serverNow - Date.now() : 0), [serverNow]);
    const [now, setNow] = useState(() => Date.now() + offset);

    useEffect(() => {
        setNow(Date.now() + offset);
        const timer = setInterval(() => setNow(Date.now() + offset), 1000);

        return () => clearInterval(timer);
    }, [offset]);

    const { date, time } = parts(now);

    return (
        <span className={`tabular-nums ${className}`} aria-label={`Tanggal ${date}, waktu ${time} WIB`}>
            {date} {time}
        </span>
    );
}
