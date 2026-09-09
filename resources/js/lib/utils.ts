import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { StoreApi, UseBoundStore } from 'zustand';
import { PromptInputMessage } from '@/components/ai-elements/prompt-input';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function limit(text: string, maxLength: number) {
    if (text.length <= maxLength) {
        return text;
    }

    return text.slice(0, maxLength) + '...';
}

export function shuffle(array: any[]) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }

    return array;
}


export function numberFormat(value: number) {
    return value.toLocaleString();
}

type WithSelectors<S> = S extends { getState: () => infer T }
    ? S & { use: { [K in keyof T]: () => T[K] } }
    : never;

export const createSelectors = <S extends UseBoundStore<StoreApi<object>>>(
    _store: S,
) => {
    const store = _store as WithSelectors<typeof _store>;
    store.use = {};

    for (const k of Object.keys(store.getState())) {
        (store.use as any)[k] = () => store((s) => s[k as keyof typeof s]);
    }

    return store;
};


export async function dataUrlToBlob(file: PromptInputMessage['files'][0]) {
    const res = await fetch(file.url);
    const blob = await res.blob();

    return new File([blob], file.filename || 'file', { type: file.mediaType });
}
