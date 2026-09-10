import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const STEPS = [
    'Requisitos',
    'Base de datos',
    'App y admin',
    'Finalizar',
] as const;

export function StepIndicator({ current }: { current: number }) {
    return (
        <ol className="flex items-center justify-center gap-2 sm:gap-4">
            {STEPS.map((label, index) => {
                const step = index + 1;
                const active = step === current;
                const done = step < current;

                return (
                    <li
                        key={label}
                        className="flex items-center gap-2 sm:gap-4"
                    >
                        <div className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                                    done && 'bg-emerald-500 text-white',
                                    active &&
                                        'bg-emerald-500 text-white ring-4 ring-emerald-500/25',
                                    !done &&
                                        !active &&
                                        'bg-muted text-muted-foreground',
                                )}
                            >
                                {done ? <Check className="size-4" /> : step}
                            </span>
                            <span
                                className={cn(
                                    'text-sm font-medium',
                                    active
                                        ? 'text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {label}
                            </span>
                        </div>
                        {step < STEPS.length && (
                            <span className="hidden h-px w-8 bg-border sm:block" />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
