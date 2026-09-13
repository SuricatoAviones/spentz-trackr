import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Wallet } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { home } from '@/routes';
import { privacy, terms } from '@/routes/legal';

/**
 * Plantilla compartida por los términos y la política de datos. El texto llega
 * ya resuelto desde `lang/{es,en}/legal.php` (ver LegalController): esta página
 * solo lo maqueta, para que actualizar lo legal no obligue a tocar React.
 */
type LegalSection = {
    heading: string;
    body: string[];
};

type LegalContent = {
    title: string;
    subtitle: string;
    updated: string;
    intro: string;
    sections: Record<string, LegalSection>;
};

export default function LegalDocument({
    document,
    content,
}: {
    document: 'terms' | 'privacy';
    content: LegalContent;
}) {
    const { t } = useTranslation();

    const sections = Object.entries(content.sections);

    return (
        <>
            <Head title={content.title} />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-6 py-5">
                        <Link
                            href={home().url}
                            className="flex items-center gap-2.5"
                        >
                            <span className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 text-primary-foreground">
                                <Wallet className="size-4" strokeWidth={2.4} />
                            </span>
                            <span className="font-display text-base font-bold tracking-tight">
                                Spentz Trackr
                            </span>
                        </Link>
                        <Link
                            href={home().url}
                            className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <ArrowLeft className="size-3.5" strokeWidth={2.4} />
                            {t('legal.back_home')}
                        </Link>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-3xl flex-1 px-6 py-10">
                    <p className="text-[11px] font-bold tracking-widest text-emerald-600 uppercase dark:text-emerald-400">
                        {t('legal.eyebrow')}
                    </p>
                    <h1 className="mt-2 font-display text-3xl font-extrabold tracking-tight">
                        {content.title}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {content.subtitle}
                    </p>
                    <p className="mt-4 inline-block rounded-full bg-muted px-3 py-1 text-[11px] font-medium text-muted-foreground">
                        {content.updated}
                    </p>

                    <p className="mt-8 text-sm leading-relaxed text-foreground">
                        {content.intro}
                    </p>

                    <div className="mt-10 space-y-9">
                        {sections.map(([key, section]) => (
                            <section key={key}>
                                <h2 className="font-display text-lg font-bold tracking-tight">
                                    {section.heading}
                                </h2>
                                <div className="mt-3 space-y-3">
                                    {section.body.map((paragraph, index) => (
                                        <p
                                            key={index}
                                            className="text-sm leading-relaxed text-muted-foreground"
                                        >
                                            {paragraph}
                                        </p>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>

                    <div className="mt-12 flex flex-wrap gap-4 border-t border-border pt-6 text-xs">
                        {document === 'terms' ? (
                            <Link
                                href={privacy().url}
                                className="font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                                {t('legal.privacy_link')}
                            </Link>
                        ) : (
                            <Link
                                href={terms().url}
                                className="font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                                {t('legal.terms_link')}
                            </Link>
                        )}
                    </div>
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto w-full max-w-3xl px-6 py-6 text-xs text-muted-foreground">
                        © 2026 Spentz Trackr
                    </div>
                </footer>
            </div>
        </>
    );
}
