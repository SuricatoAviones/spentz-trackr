import type { ReactNode } from 'react';

export function TrackerCard({
    title,
    children,
    className = '',
}: {
    title?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`rounded-xl bg-surface-low ${className}`}>
            {title !== undefined && (
                <h2 className="px-4 pt-4 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                    {title}
                </h2>
            )}
            {children}
        </section>
    );
}
