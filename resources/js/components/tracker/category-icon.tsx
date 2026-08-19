import { Tag } from 'lucide-react';
import { ICONS } from '@/lib/icons';

export function CategoryIcon({
    icon,
    color,
    size = 'md',
    className = '',
}: {
    icon: string;
    color: string;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}) {
    const Icon = ICONS[icon] ?? Tag;
    const sizes = {
        sm: 'size-8 [&>svg]:size-4',
        md: 'size-10 [&>svg]:size-5',
        lg: 'size-14 [&>svg]:size-7',
    };

    return (
        <span
            className={`inline-flex shrink-0 items-center justify-center rounded-full ${sizes[size]} ${className}`}
            style={{ backgroundColor: `${color}26`, color }}
            aria-hidden="true"
        >
            <Icon strokeWidth={2.2} />
        </span>
    );
}
