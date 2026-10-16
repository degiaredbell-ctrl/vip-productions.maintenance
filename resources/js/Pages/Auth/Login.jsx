import { useEffect, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import '../../../css/login.css';

const iconProps = {
    width: 18,
    height: 18,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': true,
};

const MailIcon = () => (
    <svg {...iconProps}><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" /></svg>
);
const LockIcon = () => (
    <svg {...iconProps}><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
);
const EyeIcon = () => (
    <svg {...iconProps}><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0" /><circle cx="12" cy="12" r="3" /></svg>
);
const EyeOffIcon = () => (
    <svg {...iconProps}><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.97 0 8.5 3.5 9.94 6.65a1 1 0 0 1 0 .7 10.8 10.8 0 0 1-1.44 2.49" /><path d="M14.08 14.16a3 3 0 0 1-4.24-4.24" /><path d="M17.48 17.5A10.75 10.75 0 0 1 2.06 12.35a1 1 0 0 1 0-.7 10.8 10.8 0 0 1 4.45-5.14" /><path d="m2 2 20 20" /></svg>
);
const KeyIcon = () => (
    <svg {...iconProps}><circle cx="7.5" cy="15.5" r="5.5" /><path d="m21 2-9.6 9.6" /><path d="m15.5 7.5 3 3L22 7l-3-3" /></svg>
);
const WrenchIcon = () => (
    <svg {...iconProps} width={15} height={15}><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" /></svg>
);

const PIN_LENGTH = 6;

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        email: '',
        password: '',
        pin: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);
    const [method, setMethod] = useState(() =>
        typeof window !== 'undefined' && window.localStorage.getItem('loginMethod') === 'pin' ? 'pin' : 'password'
    );

    const switchMethod = (next) => {
        if (next === method) return;
        setMethod(next);
        clearErrors();
        reset('password', 'pin');
        window.localStorage.setItem('loginMethod', next);
    };

    const submitPassword = () => {
        transform(({ email, password, remember }) => ({ email, password, remember }));
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const submitPin = () => {
        transform(({ pin, remember }) => ({ pin, remember }));
        post(route('login.pin'), {
            onFinish: () => reset('pin'),
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (processing) return;
        method === 'pin' ? submitPin() : submitPassword();
    };

    const onPinChange = (e) => {
        const value = e.target.value.replace(/\D/g, '').slice(0, PIN_LENGTH);
        setData('pin', value);
    };

    // PIN lengkap langsung dikirim, tanpa perlu menekan Enter.
    useEffect(() => {
        if (method === 'pin' && data.pin.length === PIN_LENGTH && !processing) {
            submitPin();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.pin, method]);

    return (
        <main className="arc-page">
            <Head title="Masuk" />

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
                        <h1>PM Preventive</h1>
                    </div>
                    <p>PT Verra Inter Pangan</p>
                    <span className="arc-hint">
                        {method === 'pin' ? 'Masukkan 6 digit PIN Anda' : 'Tekan Enter untuk masuk'}
                    </span>
                    <div className="arc-status-icons" aria-hidden="true">
                        <span>✦</span>
                        <WrenchIcon />
                        <span>◆</span>
                    </div>
                </header>

                {status && <div className="arc-alert arc-alert-ok" role="status">{status}</div>}

                <div className="arc-tabs" role="tablist" aria-label="Metode masuk">
                    <button
                        type="button"
                        role="tab"
                        aria-selected={method === 'password'}
                        className={method === 'password' ? 'arc-tab arc-tab-active' : 'arc-tab'}
                        onClick={() => switchMethod('password')}
                    >
                        <MailIcon /> Email & Password
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={method === 'pin'}
                        className={method === 'pin' ? 'arc-tab arc-tab-active' : 'arc-tab'}
                        onClick={() => switchMethod('pin')}
                    >
                        <KeyIcon /> PIN
                    </button>
                </div>

                <form className="arc-form" onSubmit={submit} noValidate>
                    {method === 'pin' ? (
                        <>
                            <div className={`arc-field ${errors.pin ? 'arc-field-invalid' : ''}`}>
                                <span className="arc-field-icon"><KeyIcon /></span>
                                <input
                                    key="pin"
                                    id="pin"
                                    type={showPassword ? 'text' : 'password'}
                                    name="pin"
                                    className="arc-pin-input"
                                    value={data.pin}
                                    placeholder="••••••"
                                    inputMode="numeric"
                                    pattern="[0-9]*"
                                    maxLength={PIN_LENGTH}
                                    autoComplete="one-time-code"
                                    autoFocus
                                    required
                                    aria-label="PIN 6 digit"
                                    aria-invalid={Boolean(errors.pin)}
                                    readOnly={processing}
                                    onChange={onPinChange}
                                />
                                <button
                                    type="button"
                                    className="arc-password-button"
                                    onClick={() => setShowPassword((v) => !v)}
                                    aria-label={showPassword ? 'Sembunyikan PIN' : 'Tampilkan PIN'}
                                >
                                    {showPassword ? <EyeOffIcon /> : <EyeIcon />}
                                </button>
                            </div>
                            {errors.pin && <p className="arc-error" role="alert">{errors.pin}</p>}
                        </>
                    ) : (
                        <>
                            <div className={`arc-field ${errors.email ? 'arc-field-invalid' : ''}`}>
                                <span className="arc-field-icon"><MailIcon /></span>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    placeholder="Email"
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    aria-label="Email"
                                    aria-invalid={Boolean(errors.email)}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                            </div>
                            {errors.email && <p className="arc-error" role="alert">{errors.email}</p>}

                            <div className={`arc-field ${errors.password ? 'arc-field-invalid' : ''}`}>
                                <span className="arc-field-icon"><LockIcon /></span>
                                <input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    value={data.password}
                                    placeholder="Password"
                                    autoComplete="current-password"
                                    required
                                    aria-label="Password"
                                    aria-invalid={Boolean(errors.password)}
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                <button
                                    type="button"
                                    className="arc-password-button"
                                    onClick={() => setShowPassword((v) => !v)}
                                    aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                                >
                                    {showPassword ? <EyeOffIcon /> : <EyeIcon />}
                                </button>
                            </div>
                            {errors.password && <p className="arc-error" role="alert">{errors.password}</p>}
                        </>
                    )}

                    <div className="arc-options">
                        <label className="arc-remember">
                            <input
                                type="checkbox"
                                name="remember"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />
                            <span className="arc-switch" aria-hidden="true"><span /></span>
                            <span>Ingat saya</span>
                        </label>
                        {canResetPassword && method === 'password' && (
                            <Link href={route('password.request')}>Lupa password?</Link>
                        )}
                    </div>

                    <button className="arc-submit" type="submit" disabled={processing}>
                        <span className="arc-submit-shine" />
                        {processing ? <><span className="arc-loader" aria-hidden="true" />Memproses…</> : 'Masuk'}
                    </button>
                </form>

                <p className="arc-footer">© {new Date().getFullYear()} Redbell Group · Preventive Maintenance</p>
            </section>
        </main>
    );
}
