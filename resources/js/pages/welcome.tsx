import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BarChart3, Camera, Wallet, Zap } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

function RadarVisual({ t }: { t: (key: string) => string }) {
    const legend = [
        { color: '#10b981', label: 'USD', pct: '45%' },
        { color: '#f59e0b', label: 'Bs', pct: '30%' },
        { color: '#3b82f6', label: 'USDT', pct: '25%' },
    ];

    return (
        <div className="relative mx-auto w-full max-w-sm">
            <div className="absolute -inset-10 rounded-full bg-[radial-gradient(circle_at_center,rgba(16,185,129,0.12),transparent_70%)]" />
            <div className="relative rounded-3xl border border-white/5 bg-[#111a2e]/70 p-6 shadow-[0_24px_80px_-32px_rgba(0,0,0,0.7)] backdrop-blur">
                <div className="relative mx-auto aspect-square w-full max-w-[300px]">
                    <svg
                        viewBox="0 0 200 200"
                        className="size-full"
                        aria-hidden="true"
                    >
                        <circle
                            cx="100"
                            cy="100"
                            r="88"
                            fill="none"
                            stroke="#1a2540"
                            strokeWidth="1"
                        />
                        <circle
                            cx="100"
                            cy="100"
                            r="62"
                            fill="none"
                            stroke="#1a2540"
                            strokeWidth="1"
                        />
                        <circle
                            cx="100"
                            cy="100"
                            r="36"
                            fill="none"
                            stroke="#1a2540"
                            strokeWidth="1"
                        />
                        <line
                            x1="10"
                            y1="100"
                            x2="190"
                            y2="100"
                            stroke="#1a2540"
                            strokeWidth="1"
                        />
                        <line
                            x1="100"
                            y1="10"
                            x2="100"
                            y2="190"
                            stroke="#1a2540"
                            strokeWidth="1"
                        />
                        <circle
                            cx="100"
                            cy="100"
                            r="60"
                            fill="none"
                            stroke="#10b981"
                            strokeWidth="14"
                            strokeDasharray="169.6 207.4"
                        />
                        <circle
                            cx="100"
                            cy="100"
                            r="60"
                            fill="none"
                            stroke="#f59e0b"
                            strokeWidth="14"
                            strokeDasharray="113.1 263.9"
                            transform="rotate(169.6 100 100)"
                        />
                        <circle
                            cx="100"
                            cy="100"
                            r="60"
                            fill="none"
                            stroke="#3b82f6"
                            strokeWidth="14"
                            strokeDasharray="94.2 282.8"
                            transform="rotate(282.7 100 100)"
                        />
                        <line
                            x1="100"
                            y1="100"
                            x2="100"
                            y2="16"
                            stroke="#10b981"
                            strokeWidth="1.5"
                            strokeOpacity="0.55"
                            className="origin-center animate-[spin_7s_linear_infinite]"
                        />
                    </svg>
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-0.5">
                        <span className="text-[10px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                            {t('welcome.visual_month_spend')}
                        </span>
                        <span className="font-display text-3xl font-bold">
                            US$ 1.248
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {t('welcome.visual_in_currencies')}
                        </span>
                    </div>
                </div>
                <div className="mt-6 space-y-2.5">
                    {legend.map((item) => (
                        <div
                            key={item.label}
                            className="flex items-center justify-between text-sm"
                        >
                            <span className="inline-flex items-center gap-2.5 text-muted-foreground">
                                <span
                                    className="size-2.5 rounded-full"
                                    style={{ backgroundColor: item.color }}
                                />
                                {item.label}
                            </span>
                            <span className="font-medium">{item.pct}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

export default function Welcome() {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    const features = [
        {
            icon: Zap,
            title: t('welcome.feature_rate_title'),
            description: t('welcome.feature_rate_description'),
        },
        {
            icon: Camera,
            title: t('welcome.feature_receipts_title'),
            description: t('welcome.feature_receipts_description'),
        },
        {
            icon: BarChart3,
            title: t('welcome.feature_reports_title'),
            description: t('welcome.feature_reports_description'),
        },
    ];

    return (
        <>
            <Head title="Spentz Trackr" />

            <div className="relative flex min-h-screen flex-col overflow-hidden bg-background text-foreground">
                <div
                    className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.025)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.025)_1px,transparent_1px)] bg-[size:56px_56px]"
                    style={{
                        WebkitMaskImage:
                            'radial-gradient(ellipse 90% 60% at 50% 0%, black 55%, transparent 100%)',
                        maskImage:
                            'radial-gradient(ellipse 90% 60% at 50% 0%, black 55%, transparent 100%)',
                    }}
                />
                <div className="pointer-events-none absolute -top-48 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-emerald-500/10 blur-3xl" />

                <header className="relative z-10 mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
                    <div className="flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-primary-foreground shadow-lg shadow-emerald-500/25">
                            <Wallet className="size-4.5" strokeWidth={2.4} />
                        </span>
                        <span className="font-display text-lg font-bold tracking-tight">
                            Spent
                            <span className="text-emerald-400">Trackr</span>
                        </span>
                    </div>
                    <nav
                        className="flex items-center gap-3"
                        aria-label={t('welcome.nav_aria')}
                    >
                        {auth.user ? (
                            <Link
                                href={dashboard().url}
                                className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg shadow-emerald-500/25 transition-transform active:scale-95"
                            >
                                {t('welcome.go_dashboard')}
                                <ArrowRight
                                    className="size-4"
                                    strokeWidth={2.4}
                                />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login().url}
                                    className="rounded-xl px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    {t('welcome.login')}
                                </Link>
                                <Link
                                    href={register().url}
                                    className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg shadow-emerald-500/25 transition-transform active:scale-95"
                                >
                                    {t('welcome.create_account')}
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="relative z-10 mx-auto grid w-full max-w-5xl flex-1 items-center gap-12 px-6 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:gap-20 lg:py-16">
                    <section>
                        <span className="inline-flex animate-in items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs font-medium text-muted-foreground duration-700 fade-in slide-in-from-bottom-2">
                            <span className="size-1.5 rounded-full bg-emerald-400" />
                            {t('welcome.made_for')}
                        </span>
                        <h1 className="mt-5 animate-in font-display text-4xl leading-[1.05] font-bold tracking-tight delay-100 duration-700 fade-in slide-in-from-bottom-3 motion-reduce:animate-none sm:text-5xl">
                            {t('welcome.hero_1')}{' '}
                            <span className="text-emerald-400">
                                {t('welcome.hero_usd')}
                            </span>
                            ,{' '}
                            <span className="text-amber-400">
                                {t('welcome.hero_bs')}
                            </span>{' '}
                            {t('welcome.hero_2')}{' '}
                            <span className="text-blue-400">
                                {t('welcome.hero_usdt')}
                            </span>
                        </h1>
                        <p className="mt-5 max-w-md animate-in text-base leading-relaxed text-muted-foreground delay-200 duration-700 fade-in slide-in-from-bottom-3 motion-reduce:animate-none">
                            {t('welcome.hero_description')}
                        </p>
                        <div className="mt-8 flex animate-in flex-wrap items-center gap-3 delay-300 duration-700 fade-in slide-in-from-bottom-3 motion-reduce:animate-none">
                            <Link
                                href={
                                    auth.user ? dashboard().url : register().url
                                }
                                className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 px-5 py-3 text-sm font-semibold text-primary-foreground shadow-lg shadow-emerald-500/25 transition-transform active:scale-95"
                            >
                                {auth.user
                                    ? t('welcome.go_dashboard')
                                    : t('welcome.create_free')}
                                <ArrowRight
                                    className="size-4"
                                    strokeWidth={2.4}
                                />
                            </Link>
                        </div>
                        <div className="mt-10 grid animate-in gap-3 delay-400 duration-700 fade-in slide-in-from-bottom-3 motion-reduce:animate-none sm:grid-cols-3">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="rounded-2xl border border-white/5 bg-[#111a2e]/60 p-4"
                                >
                                    <feature.icon
                                        className="size-4 text-emerald-400"
                                        strokeWidth={2.2}
                                    />
                                    <h2 className="mt-2.5 font-display text-sm font-semibold">
                                        {feature.title}
                                    </h2>
                                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        {feature.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <div className="animate-in delay-200 duration-700 zoom-in-95 fade-in motion-reduce:animate-none">
                        <RadarVisual t={t} />
                    </div>
                </main>

                <footer className="relative z-10 mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6 text-xs text-muted-foreground">
                    <span>© 2026 Spentz Trackr</span>
                    <span>{t('welcome.rates_source')}</span>
                </footer>
            </div>
        </>
    );
}
