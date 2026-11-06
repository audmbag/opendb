import { useSyncExternalStore } from 'react';

type Options<T extends string> = {
    /**
     * The key of the value in the local storage of the browser.
     */
    key: string;
    /**
     * The values accepted: anything else stored, by an older version for instance, falls back to the default one.
     */
    values: readonly T[];
    fallback: T;
    /**
     * Also kept in a cookie of this name, for the server to render the page with it before the scripts load.
     */
    cookie?: string;
    /**
     * Applies the value to the page, once it is read at startup and then at each change.
     */
    apply?: (value: T) => void;
};

export type StoredPreference<T extends string> = {
    readonly initialize: () => void;
    readonly get: () => T;
    readonly update: (value: T) => void;
    readonly useValue: () => T;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${name}=${value};path=/;max-age=${days * 24 * 60 * 60};SameSite=Lax`;
};

const readStorage = (key: string): string | null => {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
};

const writeStorage = (key: string, value: string): void => {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Private browsing or blocked storage: the choice lasts until the page is reloaded.
    }
};

/**
 * A preference of this computer only, kept by the browser, that the components read and follow as it changes.
 */
export function createStoredPreference<T extends string>({
    key,
    values,
    fallback,
    cookie,
    apply,
}: Options<T>): StoredPreference<T> {
    const listeners = new Set<() => void>();
    let current: T = fallback;

    const subscribe = (callback: () => void) => {
        listeners.add(callback);

        return () => listeners.delete(callback);
    };

    const get = (): T => current;

    const initialize = (): void => {
        if (typeof window === 'undefined') {
            return;
        }

        const stored = readStorage(key);
        current = values.includes(stored as T) ? (stored as T) : fallback;

        if (cookie) {
            setCookie(cookie, current);
        }

        apply?.(current);
        listeners.forEach((listener) => listener());
    };

    const update = (value: T): void => {
        current = value;
        writeStorage(key, value);

        if (cookie) {
            setCookie(cookie, value);
        }

        apply?.(value);
        listeners.forEach((listener) => listener());
    };

    const useValue = (): T =>
        useSyncExternalStore(subscribe, get, () => fallback);

    return { initialize, get, update, useValue } as const;
}
