import type { Updater } from '@tanstack/vue-table';
import type { ClassValue } from 'clsx';

import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { Ref } from 'vue';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function valueUpdater<T extends Updater<any>>(updaterOrValue: T, ref: Ref) {
    ref.value = typeof updaterOrValue === 'function' ? updaterOrValue(ref.value) : updaterOrValue;
}

export const formatHtml = (html: string): string => {
    if (!html) return '';

    // Simple HTML formatting
    return html
        .replace(/></g, '>\n<') // Add newlines between tags
        .replace(/^\s+|\s+$/g, '') // Trim whitespace
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .join('\n');
};
