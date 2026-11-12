import { getLocale } from '@/hooks/use-locale';

const formatters = new Map<string, unknown>();

/**
 * One formatter per language and options, created on first use: they follow the language of the interface.
 */
function cached<T>(kind: string, options: object | undefined, create: (locale: string) => T): T {
    const locale = getLocale();
    const key = `${kind}|${locale}|${JSON.stringify(options ?? {})}`;

    if (!formatters.has(key)) {
        formatters.set(key, create(locale));
    }

    return formatters.get(key) as T;
}

export const numberFormatter = (options?: Intl.NumberFormatOptions): Intl.NumberFormat =>
    cached('number', options, (locale) => new Intl.NumberFormat(locale, options));

export const dateFormatter = (options?: Intl.DateTimeFormatOptions): Intl.DateTimeFormat =>
    cached('date', options, (locale) => new Intl.DateTimeFormat(locale, options));

export const relativeTimeFormatter = (options?: Intl.RelativeTimeFormatOptions): Intl.RelativeTimeFormat =>
    cached('relative', options, (locale) => new Intl.RelativeTimeFormat(locale, options));

export const listFormatter = (options?: Intl.ListFormatOptions): Intl.ListFormat =>
    cached('list', options, (locale) => new Intl.ListFormat(locale, options));
