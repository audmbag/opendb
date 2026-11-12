import { useLaravelReactI18n } from 'laravel-react-i18n';
import { createStoredPreference } from '@/lib/stored-preference';

/**
 * The languages of the interface, one translation folder of lang/ each, named in their own language so that anyone
 * finds theirs whatever the current one.
 */
export const LOCALES = [
    { value: 'fr', label: 'Français' },
    { value: 'en', label: 'English' },
] as const;

export type Locale = (typeof LOCALES)[number]['value'];

const LOCALE_VALUES = LOCALES.map((locale) => locale.value);

/**
 * The language the server rendered the page in: the one kept in the cookie, or else the preferred one of the browser.
 */
const serverLocale = (): Locale => {
    const lang = typeof document === 'undefined' ? '' : document.documentElement.lang.slice(0, 2);

    return LOCALE_VALUES.includes(lang as Locale) ? (lang as Locale) : 'en';
};

/**
 * Kept in a cookie as well, for the server to answer in the same language: its messages, errors and validations.
 */
const localePreference = createStoredPreference<Locale>({
    key: 'locale',
    values: LOCALE_VALUES,
    fallback: serverLocale(),
    cookie: 'locale',
    apply: (locale) => {
        document.documentElement.lang = locale;
    },
});

export const initializeLocale = localePreference.initialize;

/**
 * The current language, for the code outside of the components, like the formatters of numbers and dates.
 */
export const getLocale = localePreference.get;

export function useLocale() {
    const locale = localePreference.useValue();
    const { setLocale } = useLaravelReactI18n();

    const updateLocale = (value: Locale): void => {
        localePreference.update(value);
        setLocale(value);
    };

    return { locale, updateLocale } as const;
}
