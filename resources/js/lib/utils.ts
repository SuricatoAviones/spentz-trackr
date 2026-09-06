import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function sanitizeSvg(raw: string): string {
    return raw
        .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
        .replace(/<script\b[^>]*\/>/gi, '')
        .replace(/\son\w+\s*=\s*("|')[\s\S]*?\1/gi, '')
        .replace(/\sjavascript:\s*("|'|)/gi, '')
        .replace(/\sxlink:href\s*=\s*("|')\s*javascript:[\s\S]*?\1/gi, '');
}
