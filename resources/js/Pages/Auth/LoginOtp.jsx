import { useEffect, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import '../../../css/login.css';

const CODE_LENGTH = 6;

const ShieldIcon = () => (
    <svg width={18} height={18} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
        <path d="m9 12 2 2 4-4" />
    </svg>
);

/**
 * Langkah kedua login: kode 6 digit dari email (Settings → Integrate →
 * Verifikasi login).
 */
export default function LoginOtp({ email, resendIn = 0, rememberDays = 30, status, error }) {
    const { data, setData, post, processing, errors, reset } = useForm({ code: '', trust_device: false });
    const [wait, setWait] = useState(resendIn);

    useEffect(() => setWait(resendIn), [resendIn]);
    useEffect(() => {
        if (wait <= 0) return undefined;
        const timer = setTimeout(() => setWait((w) => w - 1), 1000);
        return () => clearTimeout(timer);
    }, [wait]);

    const submit = (e) => {
        e?.preventDefault();
        if (processing || data.code.length !== CODE_LENGTH) return;
        post(route('login.otp.verify'), { onError: () => reset('code') });
    };

    // Kode lengkap langsung dikirim, sama seperti login PIN.
    useEffect(() => {
        if (data.code.length === CODE_LENGTH && !processing) submit();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.code]);

    const resend = () => router.post(route('login.otp.resend'), {}, { preserveScroll: true });
    const cancel = () => router.post(route('login.otp.cancel'));

    return (
        <main className="arc-page">
            <Head title="Verifikasi Login" />

            <div className="arc-background" aria-hidden="true">
                <div className="arc-video-fallback" />
                <div className="arc-overlay" />
                <div className="arc-glow arc-glow-one" />
                <div className="arc-glow arc-glow-two" />
                <div className="arc-grid" />
            </div>

            <section className="arc-card">
                <header className="arc-header">
                    <img src="/img/logo.png" alt="" className="arc-logo" />
                    <div className="arc-brand-wrap">
                        <div className="arc-brand-glow" />
                        <h1>Verifikasi Login</h1>
                    </div>
                    <p>Kode 6 digit sudah dikirim ke</p>
                    <span className="arc-hint">{email || 'email Anda'}</span>
                </header>

                {status && <div className="arc-alert arc-alert-ok" role="status">{status}</div>}
                {error && <div className="arc-alert arc-alert-error" role="alert">{error}</div>}

                <form className="arc-form" onSubmit={submit} noValidate>
                    <div className={`arc-field ${errors.code ? 'arc-field-invalid' : ''}`}>
                        <span className="arc-field-icon"><ShieldIcon /></span>
                        <input
                            id="code"
                            name="code"
                            className="arc-pin-input"
                            value={data.code}
                            placeholder="••••••"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={CODE_LENGTH}
                            autoComplete="one-time-code"
                            autoFocus
                            aria-label="Kode verifikasi 6 digit"
                            aria-invalid={Boolean(errors.code)}
                            readOnly={processing}
                            onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, CODE_LENGTH))}
                        />
                    </div>
                    {errors.code && <p className="arc-error" role="alert">{errors.code}</p>}

                    <div className="arc-options">
                        <label className="arc-remember">
                            <input
                                type="checkbox"
                                checked={data.trust_device}
                                onChange={(e) => setData('trust_device', e.target.checked)}
                            />
                            <span className="arc-switch" aria-hidden="true"><span /></span>
                            <span>Ingat perangkat ini {rememberDays} hari</span>
                        </label>
                    </div>

                    <button className="arc-submit" type="submit" disabled={processing || data.code.length !== CODE_LENGTH}>
                        <span className="arc-submit-shine" />
                        {processing ? <><span className="arc-loader" aria-hidden="true" />Memeriksa…</> : 'Verifikasi'}
                    </button>

                    <div className="arc-options">
                        <button type="button" className="arc-link-button" onClick={resend} disabled={wait > 0}>
                            {wait > 0 ? `Kirim ulang kode (${wait} dtk)` : 'Kirim ulang kode'}
                        </button>
                        <button type="button" className="arc-link-button" onClick={cancel}>Batal</button>
                    </div>
                </form>

                <p className="arc-footer">Tidak menerima email? Periksa folder Junk / Spam.</p>
            </section>
        </main>
    );
}
