import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';

function Toggle({ checked, onChange, label, hint, disabled = false }) {
    return (
        <label className={`flex items-start gap-3 py-2 ${disabled ? 'opacity-50' : 'cursor-pointer'}`}>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={`relative mt-0.5 h-6 w-11 flex-none rounded-full transition-colors ${checked ? 'bg-neu-accent' : 'bg-neu-dark/60 shadow-neu-in'}`}
            >
                <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all ${checked ? 'left-[22px]' : 'left-0.5'}`} />
            </button>
            <span className="min-w-0">
                <b className="block text-sm">{label}</b>
                {hint && <span className="block text-xs text-neu-sub mt-0.5">{hint}</span>}
            </span>
        </label>
    );
}

function Field({ label, error, hint, children, className = '' }) {
    return (
        <div className={className}>
            <span className="block text-xs text-neu-sub mb-1.5">{label}</span>
            {children}
            {hint && !error && <p className="mt-1 text-[11px] text-neu-sub">{hint}</p>}
            {error && <p className="mt-1 text-[11px] font-bold text-neu-bad">{error}</p>}
        </div>
    );
}

function Section({ title, hint, children, right }) {
    return (
        <NeuCard className="mb-4">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-3">
                <div>
                    <h2 className="text-base font-bold">{title}</h2>
                    {hint && <p className="text-xs text-neu-sub mt-0.5">{hint}</p>}
                </div>
                {right}
            </div>
            {children}
        </NeuCard>
    );
}

/**
 * Settings → Integrate: email Microsoft 365 perusahaan dan fitur-fitur email.
 */
export default function Integrate({ auth, settings, status, defaultTestRecipient = '', appUrl = '' }) {
    const form = useForm({
        ...settings,
        graph_client_secret: '',
        smtp_password: '',
    });
    const d = form.data;
    const e = form.errors;
    const set = (key) => (value) => form.setData(key, value);
    const input = (key, extra = {}) => ({
        className: 'neu-input',
        value: d[key] ?? '',
        onChange: (ev) => form.setData(key, ev.target.value),
        ...extra,
    });

    const [testTo, setTestTo] = useState(defaultTestRecipient);
    const [testing, setTesting] = useState(false);
    const [showGuide, setShowGuide] = useState(!status.ready);

    const save = (ev) => {
        ev.preventDefault();
        form.put(route('admin.integrate.update'), {
            preserveScroll: true,
            onSuccess: () => form.setData((prev) => ({ ...prev, graph_client_secret: '', smtp_password: '' })),
        });
    };

    const sendTest = () => {
        setTesting(true);
        router.post(route('admin.integrate.test'), { to: testTo }, { preserveScroll: true, onFinish: () => setTesting(false) });
    };

    const mailOn = d.mail_enabled;
    const featuresNeedMail = !status.ready;

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="flex flex-wrap items-end justify-between gap-3 mb-5">
                    <div>
                        <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                        <h1 className="text-xl sm:text-2xl font-bold">Integrate</h1>
                        <p className="text-sm text-neu-sub mt-0.5 max-w-2xl">
                            Hubungkan aplikasi dengan email <b>Microsoft Office 365</b> perusahaan. Koneksi ini dipakai notifikasi &amp; laporan (Settings → Notifications), verifikasi login, dan lupa password.
                        </p>
                    </div>
                    <NeuButton href={route('settings')} className="!min-h-[40px] !py-2 !text-sm">‹ Settings</NeuButton>
                </div>

                {/* Status */}
                <NeuCard className={`mb-4 ${status.ready ? '' : '!bg-[#FFF5E6]'}`}>
                    <div className="flex flex-wrap items-center gap-3">
                        <span className={`neu-status-dot ${status.ready ? 'neu-status-dot-on' : 'neu-status-dot-off'} mx-1`} aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            <b className="block text-sm">{status.ready ? 'Email Microsoft 365 aktif' : 'Email belum terhubung'}</b>
                            <span className="block text-xs text-neu-sub">
                                {status.ready
                                    ? `Pengirim: ${settings.from_address} · ${settings.mail_driver === 'smtp' ? 'SMTP' : 'Microsoft Graph API'}`
                                    : 'Isi koneksi di bawah, simpan, lalu kirim email uji. Fitur email baru berjalan setelah koneksi aktif.'}
                            </span>
                            {status.last_test_at && (
                                <span className={`block text-[11px] mt-0.5 ${status.last_test_ok ? 'text-neu-accent' : 'text-neu-bad'}`}>
                                    Uji terakhir {status.last_test_at}: {status.last_test_ok ? 'berhasil' : `gagal — ${status.last_test_error ?? ''}`}
                                </span>
                            )}
                        </div>
                    </div>
                </NeuCard>

                <form onSubmit={save}>
                    {/* Koneksi */}
                    <Section
                        title="Koneksi Microsoft 365"
                        hint="Disarankan Microsoft Graph API (OAuth). SMTP AUTH dengan password sudah dihentikan Microsoft untuk banyak tenant."
                        right={<Toggle checked={mailOn} onChange={set('mail_enabled')} label="Aktifkan email" />}
                    >
                        <div className="flex flex-wrap gap-2 mb-4" role="radiogroup" aria-label="Metode koneksi">
                            <NeuChip size="sm" role="radio" aria-checked={d.mail_driver === 'graph'} active={d.mail_driver === 'graph'} onClick={() => form.setData('mail_driver', 'graph')}>
                                Microsoft Graph API (Rekomendasi)
                            </NeuChip>
                            <NeuChip size="sm" role="radio" aria-checked={d.mail_driver === 'smtp'} active={d.mail_driver === 'smtp'} onClick={() => form.setData('mail_driver', 'smtp')}>
                                SMTP Office 365
                            </NeuChip>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Field label="Email pengirim (mailbox) *" error={e.from_address} hint="Mis. pm-noreply@redbellgroup.com — mailbox atau shared mailbox Microsoft 365.">
                                <input type="email" {...input('from_address')} placeholder="pm-noreply@perusahaan.com" />
                            </Field>
                            <Field label="Nama pengirim" error={e.from_name}>
                                <input {...input('from_name')} placeholder="PM Preventive" maxLength={100} />
                            </Field>

                            {d.mail_driver === 'graph' ? (
                                <>
                                    <Field label="Tenant ID (Directory ID) *" error={e.graph_tenant_id}>
                                        <input {...input('graph_tenant_id')} className="neu-input font-mono" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" autoComplete="off" />
                                    </Field>
                                    <Field label="Client ID (Application ID) *" error={e.graph_client_id}>
                                        <input {...input('graph_client_id')} className="neu-input font-mono" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" autoComplete="off" />
                                    </Field>
                                    <Field
                                        label="Client Secret (Value) *"
                                        error={e.graph_client_secret}
                                        hint={settings.graph_client_secret_set ? 'Sudah tersimpan (terenkripsi). Kosongkan bila tidak ingin mengganti.' : 'Disimpan terenkripsi dan tidak pernah ditampilkan lagi.'}
                                        className="sm:col-span-2"
                                    >
                                        <input type="password" {...input('graph_client_secret')} placeholder={settings.graph_client_secret_set ? '•••••••• (tersimpan)' : 'Tempel Value dari Certificates & secrets'} autoComplete="new-password" />
                                    </Field>
                                </>
                            ) : (
                                <>
                                    <Field label="Host SMTP" error={e.smtp_host}>
                                        <input {...input('smtp_host')} placeholder="smtp.office365.com" />
                                    </Field>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label="Port" error={e.smtp_port}>
                                            <input type="number" {...input('smtp_port')} min={1} max={65535} />
                                        </Field>
                                        <Field label="Enkripsi" error={e.smtp_encryption}>
                                            <select {...input('smtp_encryption')}>
                                                <option value="tls">STARTTLS (587)</option>
                                                <option value="ssl">SSL (465)</option>
                                                <option value="">Tanpa</option>
                                            </select>
                                        </Field>
                                    </div>
                                    <Field label="Username" error={e.smtp_username} hint="Kosong = sama dengan email pengirim.">
                                        <input {...input('smtp_username')} autoComplete="off" />
                                    </Field>
                                    <Field label="Password" error={e.smtp_password} hint={settings.smtp_password_set ? 'Sudah tersimpan. Kosongkan bila tidak ingin mengganti.' : 'Disimpan terenkripsi.'}>
                                        <input type="password" {...input('smtp_password')} placeholder={settings.smtp_password_set ? '•••••••• (tersimpan)' : ''} autoComplete="new-password" />
                                    </Field>
                                </>
                            )}
                        </div>

                        {d.mail_driver === 'graph' && (
                            <div className="mt-4">
                                <button type="button" className="text-xs font-semibold text-neu-accent hover:underline" onClick={() => setShowGuide((v) => !v)}>
                                    {showGuide ? '▾' : '▸'} Panduan membuat app registration di Microsoft Entra (untuk admin IT)
                                </button>
                                {showGuide && (
                                    <ol className="mt-2 neu-inset px-4 py-3 text-xs text-neu-sub space-y-1.5 list-decimal list-inside">
                                        <li>Buka <b>entra.microsoft.com</b> → <b>Identity → Applications → App registrations → New registration</b>. Nama: <i>PM Preventive Mailer</i>, akun: <i>Single tenant</i>.</li>
                                        <li>Salin <b>Application (client) ID</b> dan <b>Directory (tenant) ID</b> ke kolom di atas.</li>
                                        <li><b>API permissions → Add a permission → Microsoft Graph → Application permissions → Mail.Send</b>, lalu klik <b>Grant admin consent</b>.</li>
                                        <li><b>Certificates &amp; secrets → New client secret</b>. Salin kolom <b>Value</b> (bukan Secret ID) ke Client Secret. Catat tanggal kedaluwarsanya.</li>
                                        <li>Disarankan: batasi aplikasi hanya boleh mengirim dari mailbox pengirim (Exchange <i>Application Access Policy</i> / RBAC for Applications).</li>
                                        <li>Simpan pengaturan di halaman ini, lalu tekan <b>Kirim Email Uji</b>.</li>
                                    </ol>
                                )}
                            </div>
                        )}
                    </Section>

                    {/* Akun */}
                    <Section title="Fitur Akun" hint={featuresNeedMail ? 'Aktif setelah koneksi email tersimpan dan berfungsi.' : 'Keamanan login dan pemulihan akun lewat email.'}>
                        <Toggle
                            checked={d.feature_password_reset}
                            onChange={set('feature_password_reset')}
                            label="Lupa password via email"
                            hint='Menampilkan tautan "Lupa password?" di halaman login. Tautan reset dikirim ke email akun dan berlaku 60 menit.'
                        />
                        <Toggle
                            checked={d.feature_login_otp}
                            onChange={set('feature_login_otp')}
                            label="Verifikasi login (kode OTP via email)"
                            hint="Setelah password/PIN benar, user memasukkan kode 6 digit yang dikirim ke emailnya (berlaku 10 menit). Akun tanpa email valid dilewati."
                        />
                        {d.feature_login_otp && (
                            <div className="ml-14 mt-1 mb-2 max-w-xs">
                                <Field label="Ingat perangkat (hari)" error={e.login_otp_remember_days} hint="User bisa mencentang “Ingat perangkat ini” agar tidak diminta kode selama periode ini.">
                                    <input type="number" {...input('login_otp_remember_days')} min={1} max={365} />
                                </Field>
                                <p className="mt-2 text-[11px] text-neu-warn">
                                    Darurat (email bermasalah dan tidak ada yang bisa login): jalankan <code>php artisan integration:disable-otp</code> di server.
                                </p>
                            </div>
                        )}
                    </Section>

                    {/* Notifikasi */}
                    <Section
                        title="Notifikasi Email"
                        hint="Dikirim otomatis di latar belakang."
                        right={<NeuButton href={route('notifications.index', { tab: 'matrix' })} className="!min-h-[36px] !py-1.5 !px-3 !text-xs">Notifikasi &amp; Laporan PM ›</NeuButton>}
                    >
                        <p className="text-xs text-neu-sub mb-2 neu-inset px-3 py-2">
                            Notifikasi PM (jadwal Minggu ke-2, keterlambatan, dokumen approval) dan laporan mingguan/bulanan kini diatur di <b>Settings → Notifications</b>, lengkap dengan saluran Apps &amp; Email per jenis notifikasi.
                        </p>
                        <Toggle checked={d.notify_low_stock} onChange={set('notify_low_stock')} label="Stok suku cadang habis / menipis" hint="Dikirim sekali saat stok melewati batas minimum atau habis." />
                        {d.notify_low_stock && (
                            <div className="ml-14 mt-1">
                                <Field label="Penerima peringatan stok" error={e.low_stock_recipients} hint="Pisahkan dengan koma atau baris baru. Kosong = pakai penerima laporan.">
                                    <textarea {...input('low_stock_recipients')} rows={2} className="neu-input resize-y" placeholder="gudang@perusahaan.com, purchasing@perusahaan.com" />
                                </Field>
                            </div>
                        )}
                    </Section>

                    <div className="sticky bottom-[84px] lg:bottom-4 z-20 flex justify-end mb-4">
                        <NeuButton type="submit" variant="primary" disabled={form.processing} className="shadow-neu-up">
                            {form.processing ? 'Menyimpan…' : 'Simpan Pengaturan'}
                        </NeuButton>
                    </div>
                </form>

                {/* Uji */}
                <Section title="Kirim Email Uji" hint="Memastikan koneksi berfungsi. Gunakan setelah pengaturan disimpan.">
                    <div className="flex flex-col sm:flex-row gap-2">
                        <input type="email" className="neu-input" value={testTo} onChange={(ev) => setTestTo(ev.target.value)} placeholder="email tujuan" aria-label="Email tujuan uji" />
                        <NeuButton onClick={sendTest} disabled={testing || !status.ready || !testTo} variant="primary" className="whitespace-nowrap">
                            {testing ? 'Mengirim…' : 'Kirim Email Uji'}
                        </NeuButton>
                    </div>
                    {!status.ready && <p className="mt-2 text-[11px] text-neu-warn">Aktifkan &amp; simpan koneksi email terlebih dahulu.</p>}
                    <div className="mt-3 flex flex-wrap gap-2 text-[11px]">
                        <NeuPill variant={status.ready && settings.feature_password_reset ? 'done' : 'default'}>Lupa password {status.ready && settings.feature_password_reset ? 'aktif' : 'nonaktif'}</NeuPill>
                        <NeuPill variant={status.ready && settings.feature_login_otp ? 'done' : 'default'}>OTP login {status.ready && settings.feature_login_otp ? 'aktif' : 'nonaktif'}</NeuPill>
                        <NeuPill variant={status.ready && (settings.report_weekly_enabled || settings.report_monthly_enabled) ? 'done' : 'default'}>
                            Laporan otomatis {status.ready && (settings.report_weekly_enabled || settings.report_monthly_enabled) ? 'terjadwal' : 'nonaktif'}
                        </NeuPill>
                    </div>
                </Section>
            </div>
        </AuthenticatedLayout>
    );
}
