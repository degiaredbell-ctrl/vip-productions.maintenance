import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';

const STATUS_VARIANTS = { todo: 'todo', progress: 'progress', done: 'done', issue: 'issue' };

// Kategori unit dibedakan lewat warna teks sekaligus warna latar: mesin
// produksi biru, unit utility oranye. Approver jadi bisa memindai antrean
// tanpa harus membaca kode unit satu per satu.
const CATEGORY_STYLES = {
    machine: 'text-neu-info bg-neu-info/15 ring-neu-info/30',
    utility: 'text-neu-warn bg-neu-warn/15 ring-neu-warn/30',
};

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

            <td data-label="Kategori">
                <span
                    className={`inline-flex items-center rounded-[10px] px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset ${CATEGORY_STYLES[record.category] ?? 'text-neu-sub bg-neu-bg ring-neu-dark/40'}`}
                >
                    {record.category_label}
                </span>
            </td>

            <td data-label="Periode & Teknisi">
                <span className="block text-xs">
                    {record.period_label} {record.year}
                </span>
                <span className="block text-xs text-neu-sub break-words">
                    Teknisi:{' '}
                    <span className={record.technician_name ? 'text-neu-accent font-semibold' : ''}>
                        {record.technician_name ?? 'Tanpa teknisi'}
                    </span>
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
                        return: 'approvals',
                        tab: 'queue',
                    })}
                >
                    Buka Checklist
                </NeuButton>
            </td>
        </tr>
    );
}