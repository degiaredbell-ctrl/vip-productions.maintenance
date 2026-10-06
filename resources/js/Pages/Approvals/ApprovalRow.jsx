import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';

const STATUS_VARIANTS = { todo: 'todo', progress: 'progress', done: 'done', issue: 'issue' };

/**
 * Satu baris antrean persetujuan.
 *
 * Baris ini murni daftar: tidak ada input, canvas tanda tangan, maupun catatan.
 * Antrean menjawab pertanyaan "apa yang menunggu saya", lalu persetujuan dilakukan
 * di form checklist PM-nya sendiri — di situ checklist yang disetujui bisa
 * dibaca bersamaan dengan tanda tangannya, dan nama penandatanganannya sudah
 * otomatis dari akun yang login. Menaruh canvas di sini memaksa tabel melebar
 * di ponsel dan memaksa approver menyetujui tanpa membaca isinya dulu.
 *
 * Karena tidak ada state per baris, komponen ini tidak memakai
 * `useSignatureActions` dan tidak punya `id`/`htmlFor` yang bisa bentrok antar
 * baris.
 *
 * Setiap `<td>` punya `data-label` karena di layar sempit tabel ini dirakit
 * ulang jadi kartu: `thead` disembunyikan, tiap sel jadi blok penuh dengan
 * nama kolomnya di atas (lihat `.neu-table` di `app.css`).
 */
export default function ApprovalRow({ record }) {
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
                    </div>
                </div>
            </td>

            <td data-label="Periode & Teknisi">
                <span className="block text-xs">
                    {record.period_label} {record.year}
                </span>
                <span className="block text-xs text-neu-sub break-words">
                    {record.technician_name ?? 'Tanpa teknisi'}
                </span>
                {/*
                    Technician menunjuk User PIC sebelum submit. Ditampilkan di
                    bawah nama teknisi karena dua-duanya pertanyaan yang sama:
                    "siapa yang sudah menangani, siapa yang menunggu".
                */}
                <span className="block text-xs text-neu-sub break-words">
                    PIC:{' '}
                    <span className={record.assigned_pic ? 'text-neu-accent font-semibold' : ''}>
                        {record.assigned_pic?.name ?? 'belum ditunjuk'}
                    </span>
                </span>
            </td>

            <td data-label="Status">
                <NeuPill variant={STATUS_VARIANTS[record.status] ?? 'todo'}>
                    {record.status_label}
                </NeuPill>
                <span className="block text-xs text-neu-sub mt-1.5">
                    Menunggu {record.awaiting_label}
                </span>
                <span className="block text-xs text-neu-sub">
                    {record.filled_percent}% terisi
                    {record.revision_count > 0 && ` · Revisi ${record.revision_count}×`}
                </span>
            </td>

            <td data-label="Aksi">
                <NeuButton
                    href={route('machines.pm.create', {
                        machine: record.machine_id,
                        year: record.year,
                        period: record.period,
                    })}
                >
                    Buka Checklist
                </NeuButton>
            </td>
        </tr>
    );
}