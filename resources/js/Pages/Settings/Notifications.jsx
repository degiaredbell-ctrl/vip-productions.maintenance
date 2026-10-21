import { useMemo, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';

const DAYS = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const TYPE_LABELS = {
    reminder: 'Reminder Minggu ke-2',
    overdue: 'Keterlambatan PM',
    'approval.submitted': '3.1 Create PM → User PIC',
    'approval.pic_approved': '3.2 Approve PIC → Atasan',
    'approval.approved': '3.3 Approve Atasan → Teknisi',
    'approval.revision': '3.4 Revisi → Teknisi',
    'report.weekly': 'Laporan Mingguan',
    'report.monthly': 'Laporan Bulanan',
};

const TRIGGER_LABELS = { schedule: 'Terjadwal', manual: 'Manual', event: 'Real-time' };

function Switch({ checked, onChange, label, disabled = false, size = 'md' }) {
    const small = size === 'sm';

    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={() => onChange(!checked)}
            className={`relative flex-none rounded-full transition-colors disabled:opacity-40 ${small ? 'h-5 w-9' : 'h-6 w-11'} ${checked ? 'bg-neu-accent' : 'bg-neu-dark/60 shadow-neu-in'}`}
        >
            <span
                className={`absolute top-0.5 rounded-full bg-white shadow transition-all ${small ? 'h-4 w-4' : 'h-5 w-5'} ${
                    checked ? (small ? 'left-[18px]' : 'left-[22px]') : 'left-0.5'
                }`}
            />
        </button>
    );
}

function Check({ checked, onChange, label, disabled = false }) {
    return (
        <label className={`inline-flex items-center gap-1.5 text-xs font-semibold whitespace-nowrap ${disabled ? 'opacity-40' : 'cursor-pointer'}`}>
            <input
                type="checkbox"
                className="rounded border-neu-sub/40 text-neu-accent focus:ring-neu-accent"
                checked={checked}
                disabled={disabled}
                onChange={(e) => onChange(e.target.checked)}
            />
            {label}
        </label>
    );
}

const Fixed = ({ on }) => (
    <span className={`text-sm font-bold ${on ? 'text-neu-accent' : 'text-neu-sub/50'}`} title={on ? 'Selalu' : 'Tidak tersedia'}>
        {on ? '✓' : '✗'}
    </span>
);

function Section({ title, hint, right, children, className = '' }) {
    return (
        <NeuCard className={`mb-4 ${className}`}>
            <div className="flex flex-wrap items-start justify-between gap-3 mb-3">
                <div className="min-w-0">
                    <h2 className="text-base font-bold">{title}</h2>
                    {hint && <p className="text-xs text-neu-sub mt-0.5">{hint}</p>}
                </div>
                {right}
            </div>
            {children}
        </NeuCard>
    );
}

// ---------------------------------------------------------------------------
// Matriks & Jadwal
// ---------------------------------------------------------------------------

function MatrixTab({ admin }) {
    const form = useForm({ ...admin.matrix });
    const d = form.data;
    const e = form.errors;
    const set = (key) => (value) => form.setData(key, value);

    const save = (ev) => {
        ev.preventDefault();
        form.put(route('notifications.update'), { preserveScroll: true });
    };

    const rows = [
        {
            no: '1',
            title: 'Informasi Masuk Minggu ke-2 Periode',
            desc: 'Info periode + minggu berjalan, lalu daftar perangkat yang harus di-PM. Sekali per periode.',
            enabled: 'reminder_enabled',
            trigger: (
                <div className="flex items-center gap-2">
                    <span className="text-xs text-neu-sub whitespace-nowrap">Hari pertama Minggu ke-2, pukul</span>
                    <input type="time" className="neu-input !py-1 !text-xs w-[110px]" value={d.reminder_time} onChange={(ev) => form.setData('reminder_time', ev.target.value)} disabled={!d.reminder_enabled} />
                </div>
            ),
            triggerError: e.reminder_time,
            app: 'reminder_app',
            email: 'reminder_email',
            recipients: [['reminder_to_technician', 'Teknisi'], ['reminder_to_pic', 'User/PIC'], ['reminder_to_supervisor', 'Atasan']],
        },
        {
            no: '2',
            title: 'Informasi Keterlambatan PM',
            desc: 'Daftar perangkat terlambat (lokasi, minggu jadwal, hari terlambat). Diulang setiap hari, berhenti otomatis bila PM selesai.',
            enabled: 'overdue_enabled',
            trigger: (
                <div className="flex items-center gap-2">
                    <span className="text-xs text-neu-sub whitespace-nowrap">Setiap hari pukul</span>
                    <input className="neu-input !py-1 !text-xs w-[140px] font-mono" value={d.overdue_times} onChange={(ev) => form.setData('overdue_times', ev.target.value)} placeholder="07:00, 14:00" disabled={!d.overdue_enabled} />
                </div>
            ),
            triggerError: e.overdue_times,
            app: 'overdue_app',
            email: 'overdue_email',
            recipients: [['overdue_to_technician', 'Teknisi'], ['overdue_to_supervisor', 'Atasan']],
        },
        { no: '3.1', title: 'Teknisi selesai Create PM', desc: 'Real-time', app: 'submitted_app', email: 'submitted_email', fixedTo: 'User/PIC yang ditunjuk' },
        { no: '3.2', title: 'User/PIC selesai Approve PM', desc: 'Real-time', app: 'pic_approved_app', email: 'pic_approved_email', fixedTo: 'Atasan' },
        { no: '3.3', title: 'Atasan selesai Approve PM', desc: 'Real-time', app: 'approved_app', email: 'approved_email', fixedTo: 'Teknisi pembuat' },
        { no: '3.4', title: 'Dokumen direvisi oleh User / Atasan', desc: 'Real-time, berisi catatan revisi & peminta revisi', app: 'revision_app', email: 'revision_email', fixedTo: 'Teknisi pembuat' },
        {
            no: '4.1',
            title: 'Laporan Mingguan',
            desc: 'Rekap 7 hari terakhir + status periode berjalan.',
            enabled: 'report_weekly_enabled',
            trigger: (
                <div className="flex items-center gap-2">
                    <select className="neu-input !py-1 !text-xs w-[110px]" value={d.report_weekly_day} onChange={(ev) => form.setData('report_weekly_day', ev.target.value)} disabled={!d.report_weekly_enabled}>
                        {DAYS.slice(1).map((day, i) => <option key={day} value={i + 1}>{day}</option>)}
                    </select>
                    <input type="time" className="neu-input !py-1 !text-xs w-[110px]" value={d.report_weekly_time} onChange={(ev) => form.setData('report_weekly_time', ev.target.value)} disabled={!d.report_weekly_enabled} />
                </div>
            ),
            triggerError: e.report_weekly_time,
            appFixed: false,
            emailFixed: true,
            recipients: [['report_to_supervisor', 'Atasan']],
        },
        {
            no: '4.2',
            title: 'Laporan Bulanan',
            desc: 'Rekap bulan sebelumnya + lampiran setahun.',
            enabled: 'report_monthly_enabled',
            trigger: (
                <div className="flex items-center gap-2">
                    <span className="text-xs text-neu-sub whitespace-nowrap">Tanggal</span>
                    <select className="neu-input !py-1 !text-xs w-[70px]" value={d.report_monthly_day} onChange={(ev) => form.setData('report_monthly_day', ev.target.value)} disabled={!d.report_monthly_enabled}>
                        {Array.from({ length: 28 }, (_, i) => <option key={i + 1} value={i + 1}>{i + 1}</option>)}
                    </select>
                    <input type="time" className="neu-input !py-1 !text-xs w-[110px]" value={d.report_monthly_time} onChange={(ev) => form.setData('report_monthly_time', ev.target.value)} disabled={!d.report_monthly_enabled} />
                </div>
            ),
            triggerError: e.report_monthly_time,
            appFixed: false,
            emailFixed: true,
            recipients: [['report_to_supervisor', 'Atasan']],
        },
    ];

    const groups = [
        { title: '1–2 · Jadwal & Keterlambatan (terjadwal)', rows: rows.slice(0, 2) },
        { title: '3 · Dokumen Approval (real-time)', rows: rows.slice(2, 6), hint: 'Alur: Teknisi Create PM → User/PIC Approve → Atasan Approve → Selesai' },
        { title: '4 · Dokumen Laporan (khusus Email)', rows: rows.slice(6) },
    ];

    const { recipients } = admin;

    return (
        <form onSubmit={save}>
            {!admin.mailReady && (
                <NeuCard className="mb-4 !bg-[#FFF5E6]">
                    <p className="text-sm">
                        <b>Email belum terhubung.</b> Notifikasi Apps tetap berjalan; saluran Email dan laporan baru terkirim setelah koneksi di{' '}
                        <Link href={route('admin.integrate')} className="font-semibold text-neu-accent underline">Settings → Integrate</Link> aktif.
                    </p>
                </NeuCard>
            )}

            {groups.map((group) => (
                <Section key={group.title} title={group.title} hint={group.hint}>
                    <div className="overflow-x-auto -mx-1">
                        <table className="w-full min-w-[820px] text-left">
                            <thead>
                                <tr className="text-[11px] uppercase tracking-wider text-neu-sub">
                                    <th className="px-2 py-2 w-[34%]">Notifikasi</th>
                                    <th className="px-2 py-2">Pemicu / Jadwal (WIB)</th>
                                    <th className="px-2 py-2 text-center w-[70px]">Apps</th>
                                    <th className="px-2 py-2 text-center w-[70px]">Email</th>
                                    <th className="px-2 py-2">Penerima</th>
                                </tr>
                            </thead>
                            <tbody>
                                {group.rows.map((row) => {
                                    const off = row.enabled ? !d[row.enabled] : false;

                                    return (
                                        <tr key={row.no} className="border-t border-neu-sub/15 align-top">
                                            <td className="px-2 py-3">
                                                <div className="flex items-start gap-2.5">
                                                    {row.enabled && <Switch checked={!!d[row.enabled]} onChange={set(row.enabled)} label={`Aktifkan ${row.title}`} size="sm" />}
                                                    <div className={off ? 'opacity-50' : ''}>
                                                        <b className="block text-sm"><span className="text-neu-sub font-mono mr-1">{row.no}</span>{row.title}</b>
                                                        <span className="block text-[11px] text-neu-sub">{row.desc}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className={`px-2 py-3 ${off ? 'opacity-50' : ''}`}>
                                                {row.trigger ?? <span className="text-xs text-neu-sub">Saat aksi terjadi</span>}
                                                {row.triggerError && <p className="mt-1 text-[11px] font-bold text-neu-bad">{row.triggerError}</p>}
                                            </td>
                                            <td className="px-2 py-3 text-center">
                                                {row.app ? <Switch checked={!!d[row.app]} onChange={set(row.app)} label={`Apps: ${row.title}`} disabled={off} size="sm" /> : <Fixed on={row.appFixed} />}
                                            </td>
                                            <td className="px-2 py-3 text-center">
                                                {row.email ? <Switch checked={!!d[row.email]} onChange={set(row.email)} label={`Email: ${row.title}`} disabled={off} size="sm" /> : <Fixed on={row.emailFixed} />}
                                            </td>
                                            <td className={`px-2 py-3 ${off ? 'opacity-50' : ''}`}>
                                                {row.fixedTo ? (
                                                    <span className="text-xs font-semibold">{row.fixedTo}</span>
                                                ) : (
                                                    <div className="flex flex-wrap gap-x-3 gap-y-1">
                                                        {row.recipients.map(([key, label]) => (
                                                            <Check key={key} checked={!!d[key]} onChange={set(key)} label={label} disabled={off} />
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {group.rows[0].no === '4.1' && (
                        <div className="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <span className="block text-xs text-neu-sub mb-1.5">Email penerima tambahan</span>
                                <textarea
                                    className="neu-input resize-y"
                                    rows={2}
                                    value={d.report_recipients ?? ''}
                                    onChange={(ev) => form.setData('report_recipients', ev.target.value)}
                                    placeholder="plant.head@perusahaan.com, it@perusahaan.com"
                                />
                                <p className="mt-1 text-[11px] text-neu-sub">Selain Atasan. Pisahkan dengan koma atau baris baru.</p>
                                {e.report_recipients && <p className="mt-1 text-[11px] font-bold text-neu-bad">{e.report_recipients}</p>}
                            </div>
                            <div>
                                <span className="block text-xs text-neu-sub mb-1.5">Lampiran</span>
                                <div className="flex flex-col gap-1.5">
                                    <Check checked={!!d.report_attach_pdf} onChange={set('report_attach_pdf')} label="PDF ringkasan (pencapaian + daftar terlambat)" />
                                    <Check checked={!!d.report_attach_excel} onChange={set('report_attach_excel')} label="Excel rekap PM lengkap" />
                                </div>
                                <p className="mt-2 text-[11px] text-neu-sub">
                                    Isi: jumlah PM terjadwal, selesai, dalam approval, revisi, terlambat; persentase pencapaian; daftar perangkat terlambat.
                                </p>
                            </div>
                        </div>
                    )}
                </Section>
            ))}

            <Section title="Siapa yang menerima?" hint="Dihitung otomatis dari role & permission. Ubah lewat menu Role / Pengguna, dan PIC lewat tab PIC Perangkat.">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 text-xs">
                    <div className="neu-inset px-4 py-3">
                        <b className="block mb-1">Teknisi ({recipients.technicians.length})</b>
                        <p className="text-[11px] text-neu-sub mb-2">Pemegang <code>pm.sign</code>. Mesin → <code>machine.view</code> (tech-machines), Utility → <code>utility.view</code> (tech-utilities).</p>
                        <ul className="space-y-1">
                            {recipients.technicians.map((t) => (
                                <li key={t.id} className="flex flex-wrap items-center gap-1.5">
                                    <span className="font-semibold">{t.name}</span>
                                    {t.scopes.map((sc) => (
                                        <NeuPill key={sc} variant={sc === 'utility' ? 'progress' : 'todo'} className="!text-[10px] !py-0">{sc === 'utility' ? 'Utility' : 'Mesin'}</NeuPill>
                                    ))}
                                </li>
                            ))}
                            {recipients.technicians.length === 0 && <li className="text-neu-sub">Belum ada.</li>}
                        </ul>
                    </div>
                    <div className="neu-inset px-4 py-3">
                        <b className="block mb-1">Atasan ({recipients.supervisors.length})</b>
                        <p className="text-[11px] text-neu-sub mb-2">Pemegang <code>pm.approve</code> (Admin hanya bila tidak ada yang lain). Menerima semua perangkat.</p>
                        <ul className="space-y-1">
                            {recipients.supervisors.map((u) => (
                                <li key={u.id}><span className="font-semibold">{u.name}</span> <span className="text-neu-sub">· {u.role}</span></li>
                            ))}
                            {recipients.supervisors.length === 0 && <li className="text-neu-sub">Belum ada.</li>}
                        </ul>
                    </div>
                    <div className="neu-inset px-4 py-3">
                        <b className="block mb-1">Penerima laporan ({recipients.reportRecipients.length})</b>
                        <p className="text-[11px] text-neu-sub mb-2">Atasan (bila dicentang) + email tambahan, sesuai pengaturan tersimpan.</p>
                        <ul className="space-y-0.5 break-all">
                            {recipients.reportRecipients.map((r) => <li key={r}>{r}</li>)}
                            {recipients.reportRecipients.length === 0 && <li className="text-neu-sub">Belum ada.</li>}
                        </ul>
                    </div>
                </div>
            </Section>

            <div className="sticky bottom-[84px] lg:bottom-4 z-20 flex justify-end mb-4">
                <NeuButton type="submit" variant="primary" disabled={form.processing} className="shadow-neu-up">
                    {form.processing ? 'Menyimpan…' : 'Simpan Pengaturan'}
                </NeuButton>
            </div>
        </form>
    );
}

// ---------------------------------------------------------------------------
// PIC Perangkat
// ---------------------------------------------------------------------------

function PicTab({ admin }) {
    const { devices, picCandidates } = admin;
    const [search, setSearch] = useState('');
    const [scope, setScope] = useState('all');
    const [onlyEmpty, setOnlyEmpty] = useState(false);
    const [selected, setSelected] = useState(() => new Set());
    const [bulkPic, setBulkPic] = useState('');
    const [busy, setBusy] = useState(false);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return devices.filter((m) => (scope === 'all' || m.scope === scope)
            && (!onlyEmpty || !m.pic_user_id)
            && (!q || [m.code, m.name, m.location, m.type_label, m.pic_name, m.fallback_pic].some((v) => (v ?? '').toLowerCase().includes(q))));
    }, [devices, search, scope, onlyEmpty]);

    const assigned = devices.filter((m) => m.pic_user_id).length;
    const fromHistory = devices.filter((m) => !m.pic_user_id && m.fallback_pic).length;
    const allChecked = filtered.length > 0 && filtered.every((m) => selected.has(m.id));

    const toggle = (id) => setSelected((prev) => {
        const next = new Set(prev);
        next.has(id) ? next.delete(id) : next.add(id);
        return next;
    });
    const toggleAll = () => setSelected((prev) => {
        const next = new Set(prev);
        filtered.forEach((m) => (allChecked ? next.delete(m.id) : next.add(m.id)));
        return next;
    });

    const submit = (ids, picId) => {
        setBusy(true);
        router.put(route('notifications.pic'), { machine_ids: ids, pic_user_id: picId || null }, {
            preserveScroll: true,
            preserveState: true,
            only: ['admin', 'flash'],
            onSuccess: () => setSelected(new Set()),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <>
            <NeuCard className="mb-4">
                <div className="flex flex-wrap items-center gap-4">
                    <div className="min-w-0 flex-1">
                        <h2 className="text-base font-bold">PIC Penanggung Jawab Perangkat</h2>
                        <p className="text-xs text-neu-sub mt-0.5 max-w-3xl">
                            User/PIC hanya menerima notifikasi untuk perangkat yang menjadi tanggung jawabnya. Bila kosong, dipakai PIC dari PM terakhir perangkat itu.
                            PIC di sini juga menjadi pilihan awal &quot;User PIC&quot; di form PM.
                        </p>
                    </div>
                    <div className="flex gap-2 text-xs">
                        <NeuPill variant="done">{assigned} ditetapkan</NeuPill>
                        <NeuPill variant="progress">{fromHistory} dari riwayat</NeuPill>
                        <NeuPill variant="todo">{devices.length - assigned - fromHistory} tanpa PIC</NeuPill>
                    </div>
                </div>
            </NeuCard>

            <div className="flex flex-wrap items-center gap-2 mb-3">
                <input className="neu-input !w-full sm:!w-64" placeholder="Cari kode, nama, lokasi, PIC…" value={search} onChange={(e) => setSearch(e.target.value)} />
                {[['all', 'Semua'], ['machine', 'Mesin'], ['utility', 'Utility']].map(([v, l]) => (
                    <NeuChip key={v} size="sm" active={scope === v} onClick={() => setScope(v)}>{l}</NeuChip>
                ))}
                <NeuChip size="sm" active={onlyEmpty} onClick={() => setOnlyEmpty((v) => !v)}>Belum ditetapkan</NeuChip>
            </div>

            {selected.size > 0 && (
                <div className="sticky top-14 lg:top-2 z-20 mb-3 rounded-[20px] bg-neu-bg shadow-neu-up px-4 py-3 flex flex-wrap items-center gap-2">
                    <b className="text-sm mr-2">{selected.size} perangkat dipilih</b>
                    <select className="neu-input !w-auto !py-1.5 !text-sm" value={bulkPic} onChange={(e) => setBulkPic(e.target.value)}>
                        <option value="">— pilih User PIC —</option>
                        {picCandidates.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                    </select>
                    <NeuButton variant="primary" disabled={!bulkPic || busy} onClick={() => submit([...selected], bulkPic)} className="!min-h-[36px] !py-1.5 !text-sm">Terapkan</NeuButton>
                    <NeuButton disabled={busy} onClick={() => submit([...selected], null)} className="!min-h-[36px] !py-1.5 !text-sm">Kosongkan PIC</NeuButton>
                    <button type="button" onClick={() => setSelected(new Set())} className="ml-auto text-xs text-neu-sub hover:underline">Batal pilih</button>
                </div>
            )}

            <NeuCard className="!p-2 overflow-x-auto">
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead>
                        <tr className="text-[11px] uppercase tracking-wider text-neu-sub">
                            <th className="px-3 py-2 w-8"><input type="checkbox" className="rounded text-neu-accent" checked={allChecked} onChange={toggleAll} aria-label="Pilih semua" /></th>
                            <th className="px-3 py-2">Perangkat</th>
                            <th className="px-3 py-2">Jenis</th>
                            <th className="px-3 py-2">Lokasi</th>
                            <th className="px-3 py-2 text-center">Minggu</th>
                            <th className="px-3 py-2 w-[240px]">User PIC</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((m) => (
                            <tr key={m.id} className={`border-t border-neu-sub/15 ${selected.has(m.id) ? 'bg-neu-accent/5' : ''}`}>
                                <td className="px-3 py-2"><input type="checkbox" className="rounded text-neu-accent" checked={selected.has(m.id)} onChange={() => toggle(m.id)} aria-label={`Pilih ${m.code}`} /></td>
                                <td className="px-3 py-2">
                                    <b className="block text-xs">{m.code}</b>
                                    <span className="text-xs text-neu-sub">{m.name}</span>
                                </td>
                                <td className="px-3 py-2 text-xs">{m.type_label}</td>
                                <td className="px-3 py-2 text-xs">{m.location || '-'}</td>
                                <td className="px-3 py-2 text-xs text-center">W{m.week}</td>
                                <td className="px-3 py-2">
                                    <select
                                        className="neu-input !py-1 !text-xs"
                                        value={m.pic_user_id ?? ''}
                                        disabled={busy}
                                        onChange={(e) => submit([m.id], e.target.value)}
                                        aria-label={`User PIC ${m.code}`}
                                    >
                                        <option value="">{m.fallback_pic ? `— (riwayat: ${m.fallback_pic})` : '— belum ditetapkan —'}</option>
                                        {picCandidates.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                                    </select>
                                </td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr><td colSpan={6} className="px-3 py-8 text-center text-sm text-neu-sub">Tidak ada perangkat yang cocok.</td></tr>
                        )}
                    </tbody>
                </table>
            </NeuCard>
        </>
    );
}

// ---------------------------------------------------------------------------
// Kirim & Riwayat
// ---------------------------------------------------------------------------

function PreviewTable({ result }) {
    if (!result) return null;

    return (
        <div className="mt-3 neu-inset p-3">
            <p className={`text-xs font-semibold mb-2 ${result.status === 'skipped' ? 'text-neu-warn' : ''}`}>{result.message}</p>
            {result.recipients.length > 0 && (
                <div className="overflow-x-auto">
                    <table className="w-full text-[11px] text-left">
                        <thead>
                            <tr className="text-neu-sub">
                                <th className="px-2 py-1">Penerima</th>
                                <th className="px-2 py-1">Peran</th>
                                <th className="px-2 py-1 text-right">Perangkat</th>
                                <th className="px-2 py-1">Saluran</th>
                            </tr>
                        </thead>
                        <tbody>
                            {result.recipients.map((r) => (
                                <tr key={r.id} className="border-t border-neu-sub/10">
                                    <td className="px-2 py-1"><b>{r.name}</b><span className="block text-neu-sub">{r.email}</span></td>
                                    <td className="px-2 py-1">{r.roles.join(', ')}</td>
                                    <td className="px-2 py-1 text-right font-bold">{r.count}</td>
                                    <td className="px-2 py-1">
                                        {r.channels.length === 0 && <span className="text-neu-sub">— (saluran nonaktif)</span>}
                                        {r.channels.includes('database') && <NeuPill variant="progress" className="!text-[10px] !py-0 mr-1">Apps</NeuPill>}
                                        {r.channels.includes('mail') && <NeuPill variant="done" className="!text-[10px] !py-0">Email</NeuPill>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function DispatchTab({ admin }) {
    const [preview, setPreview] = useState({});
    const [loading, setLoading] = useState(null);
    const [sending, setSending] = useState(null);
    const { context, mailReady, dispatches, matrix } = admin;

    const runPreview = (type) => {
        setLoading(type);
        window.axios.post(route('notifications.preview'), { type })
            .then(({ data }) => setPreview((p) => ({ ...p, [type]: data })))
            .catch((err) => setPreview((p) => ({ ...p, [type]: { status: 'skipped', message: err.response?.data?.message ?? 'Gagal memuat pratinjau.', recipients: [] } })))
            .finally(() => setLoading(null));
    };

    const send = (type, label) => {
        if (!confirm(`Kirim "${label}" sekarang ke semua penerima?`)) return;
        setSending(type);
        router.post(route('notifications.send'), { type }, { preserveScroll: true, onFinish: () => setSending(null) });
    };

    const cards = [
        {
            type: 'reminder',
            title: '1 · Reminder Minggu ke-2',
            hint: `Terjadwal otomatis pukul ${matrix.reminder_time} WIB pada hari pertama Minggu ke-2 (sekali per periode). "Kirim sekarang" mengirim ulang di luar jadwal.`,
            previewable: true,
        },
        {
            type: 'overdue',
            title: '2 · Keterlambatan PM',
            hint: `Terjadwal setiap hari pukul ${matrix.overdue_times.split(',').join(', ')} WIB selama masih ada perangkat terlambat.`,
            previewable: true,
        },
        { type: 'weekly', title: '4.1 · Laporan Mingguan', hint: `Terjadwal setiap ${DAYS[matrix.report_weekly_day]} ${matrix.report_weekly_time} WIB · khusus Email.`, emailOnly: true },
        { type: 'monthly', title: '4.2 · Laporan Bulanan', hint: `Terjadwal setiap tanggal ${matrix.report_monthly_day} pukul ${matrix.report_monthly_time} WIB · khusus Email.`, emailOnly: true },
    ];

    return (
        <>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                {cards.map((c) => (
                    <NeuCard key={c.type} className="!p-4">
                        <b className="block text-sm">{c.title}</b>
                        <p className="text-[11px] text-neu-sub mt-0.5">{c.hint}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {c.previewable && (
                                <NeuButton onClick={() => runPreview(c.type)} disabled={loading !== null} className="!min-h-[34px] !py-1 !px-3 !text-xs">
                                    {loading === c.type ? 'Memuat…' : 'Pratinjau penerima'}
                                </NeuButton>
                            )}
                            <NeuButton
                                variant="primary"
                                onClick={() => send(c.type, c.title)}
                                disabled={sending !== null || (c.emailOnly && !mailReady)}
                                className="!min-h-[34px] !py-1 !px-3 !text-xs"
                            >
                                {sending === c.type ? 'Mengirim…' : 'Kirim sekarang'}
                            </NeuButton>
                        </div>
                        {c.emailOnly && !mailReady && <p className="mt-2 text-[11px] text-neu-warn">Aktifkan koneksi email di Settings → Integrate.</p>}
                        <PreviewTable result={preview[c.type]} />
                    </NeuCard>
                ))}
            </div>

            <Section title="Riwayat Pengiriman" hint="40 pengiriman terakhir (terjadwal, manual, dan real-time).">
                <div className="overflow-x-auto -mx-1">
                    <table className="w-full min-w-[820px] text-left text-xs">
                        <thead>
                            <tr className="text-[11px] uppercase tracking-wider text-neu-sub">
                                <th className="px-2 py-2">Waktu</th>
                                <th className="px-2 py-2">Jenis</th>
                                <th className="px-2 py-2">Pemicu</th>
                                <th className="px-2 py-2">Ringkasan</th>
                                <th className="px-2 py-2 text-center">Apps</th>
                                <th className="px-2 py-2 text-center">Email</th>
                                <th className="px-2 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {dispatches.map((row) => (
                                <tr key={row.id} className="border-t border-neu-sub/15 align-top">
                                    <td className="px-2 py-2 whitespace-nowrap">{row.at}</td>
                                    <td className="px-2 py-2 whitespace-nowrap font-semibold">{TYPE_LABELS[row.type] ?? row.type}</td>
                                    <td className="px-2 py-2 whitespace-nowrap">{TRIGGER_LABELS[row.trigger] ?? row.trigger}{row.by ? ` · ${row.by}` : ''}</td>
                                    <td className="px-2 py-2">
                                        {row.summary}
                                        {row.error && <span className="block text-[11px] text-neu-bad whitespace-pre-line">{row.error}</span>}
                                    </td>
                                    <td className="px-2 py-2 text-center">{row.app_sent}</td>
                                    <td className="px-2 py-2 text-center">{row.email_sent}</td>
                                    <td className="px-2 py-2">
                                        <NeuPill variant={row.status === 'sent' ? 'done' : row.status === 'failed' ? 'issue' : 'default'} className="!text-[10px] !py-0">
                                            {row.status === 'sent' ? 'Terkirim' : row.status === 'failed' ? 'Gagal' : row.status === 'sending' ? 'Mengirim' : 'Dilewati'}
                                        </NeuPill>
                                    </td>
                                </tr>
                            ))}
                            {dispatches.length === 0 && (
                                <tr><td colSpan={7} className="px-2 py-8 text-center text-neu-sub">Belum ada pengiriman.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <p className="mt-2 text-[11px] text-neu-sub">Periode berjalan: <b>{context.label}</b> ({context.start} – {context.end}){context.week ? ` · Minggu ke-${context.week}` : ''}.</p>
            </Section>
        </>
    );
}

// ---------------------------------------------------------------------------

export default function Notifications({ auth, tab: initialTab = 'matrix', admin }) {
    const [tab, setTab] = useState(initialTab);

    const switchTab = (value) => {
        setTab(value);
        const url = new URL(window.location.href);
        value === 'matrix' ? url.searchParams.delete('tab') : url.searchParams.set('tab', value);
        window.history.replaceState(window.history.state, '', url);
    };

    const tabs = [['matrix', 'Matriks & Jadwal'], ['pic', 'PIC Perangkat'], ['dispatch', 'Kirim & Riwayat']];

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="flex flex-wrap items-end justify-between gap-3 mb-5">
                    <div>
                        <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                        <h1 className="text-xl sm:text-2xl font-bold">Notifications</h1>
                        <p className="text-sm text-neu-sub mt-0.5 max-w-2xl">
                            Pengaturan notifikasi in-app &amp; email untuk jadwal PM, keterlambatan, dokumen approval, dan laporan.
                            Periode berjalan: <b>{admin.context.label}</b>{admin.context.week ? ` · Minggu ke-${admin.context.week}` : ''}.
                        </p>
                    </div>
                    <NeuButton href={route('settings')} className="!min-h-[40px] !py-2 !text-sm">‹ Settings</NeuButton>
                </div>

                <div className="flex flex-wrap gap-2 mb-5" role="tablist">
                    {tabs.map(([value, label]) => (
                        <NeuChip key={value} role="tab" aria-selected={tab === value} active={tab === value} onClick={() => switchTab(value)}>{label}</NeuChip>
                    ))}
                </div>

                {tab === 'matrix' && <MatrixTab admin={admin} />}
                {tab === 'pic' && <PicTab admin={admin} />}
                {tab === 'dispatch' && <DispatchTab admin={admin} />}
            </div>
        </AuthenticatedLayout>
    );
}
