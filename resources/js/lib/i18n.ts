import { useLaravelReactI18n } from 'laravel-react-i18n';
import { numberFormatter } from '@/lib/intl';

type Replacements = Record<string, string | number>;

type Translator = {
    t: (key: string, replacements?: Replacements) => string;
    tChoice: (key: string, count: number, replacements?: Replacements) => string;
};

let translator: Translator = {
    t: (key) => key,
    tChoice: (key) => key,
};

const bindTranslator = (current: Translator): void => {
    translator = current;
};

/**
 * Translates a key of the translation files of lang/ outside of the components: helpers, stores, callbacks. A
 * component calls useLaravelReactI18n() instead, to render again when the language changes.
 */
export const trans = (key: string, replacements?: Replacements): string => translator.t(key, replacements);

/**
 * Like trans(), with the form matching the count: "{0} …|{1} …|[2,*] …" as in Laravel.
 */
export const transChoice = (key: string, count: number, replacements?: Replacements): string =>
    translator.tChoice(key, count, replacements);

/**
 * Keeps trans() on the current language: rendered first in the provider of the translations, before the page.
 */
export function TranslatorBridge(): null {
    bindTranslator(useLaravelReactI18n());

    return null;
}

/**
 * A number of a message of the server, written in the language of the reader: an integer, an estimated count of rows
 * (« ~1 234 »), a cost with two decimals, a share between 0 and 1, or a signed change between 0 and 1.
 */
type NumberParam = { number: number; format: 'integer' | 'rows' | 'decimal' | 'percent' | 'change' };

/**
 * A message saved by the server by its key in lang/{locale} (App\Support\TranslatableMessage), so that it is shown in
 * the current language. It may come as the object or as its JSON, like the reason of an approval.
 */
export type TranslatableMessage = {
    key: string;
    params?: Record<string, string | number | NumberParam | TranslatableMessage> | [];
};

/**
 * A text of the server: a translatable message, a text saved before the messages were translatable, or a list of
 * them shown one after the other.
 */
export type ServerMessage = string | TranslatableMessage | ServerMessage[];

const asTranslatableMessage = (value: unknown): TranslatableMessage | null => {
    if (typeof value === 'string' && value.startsWith('{')) {
        try {
            return asTranslatableMessage(JSON.parse(value));
        } catch {
            return null;
        }
    }

    return typeof value === 'object' && value !== null && typeof (value as TranslatableMessage).key === 'string'
        ? (value as TranslatableMessage)
        : null;
};

const formatNumberParam = ({ number, format }: NumberParam): string => {
    switch (format) {
        case 'rows':
            return `~${numberFormatter({ maximumFractionDigits: 0 }).format(number)}`;
        case 'decimal':
            return numberFormatter({ minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number);
        case 'percent':
            return numberFormatter({ style: 'percent', maximumFractionDigits: 0 }).format(number);
        case 'change':
            return numberFormatter({ style: 'percent', maximumFractionDigits: 0, signDisplay: 'always' })
                .format(number)
                .replace('-', '−');
        default:
            return numberFormatter({ maximumFractionDigits: 0 }).format(number);
    }
};

/**
 * The text of a message of the server: a translatable one in the current language, any other as it was written.
 */
export const translateMessage = (t: Translator['t'], message: ServerMessage | null | undefined): string => {
    if (Array.isArray(message)) {
        return message
            .map((part) => translateMessage(t, part))
            .filter(Boolean)
            .join(' ');
    }

    const translatable = asTranslatableMessage(message);

    if (!translatable) {
        return typeof message === 'string' ? message : '';
    }

    const params = Array.isArray(translatable.params) ? {} : (translatable.params ?? {});

    return t(
        translatable.key,
        Object.fromEntries(
            Object.entries(params).map(([name, value]) => [
                name,
                typeof value === 'object' && value !== null
                    ? 'number' in value
                        ? formatNumberParam(value as NumberParam)
                        : translateMessage(t, value as TranslatableMessage)
                    : value,
            ]),
        ),
    );
};
