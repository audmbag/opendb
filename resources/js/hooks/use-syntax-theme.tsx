import type { BundledTheme } from 'shiki';
import { createStoredPreference } from '@/lib/stored-preference';

export type SyntaxThemeDefinition = {
    value: string;
    label: string;
    /**
     * The Shiki themes of the pair, one for each appearance of the interface.
     */
    light: BundledTheme;
    dark: BundledTheme;
};

export const SYNTAX_THEMES = [
    {
        value: 'github',
        label: 'GitHub',
        light: 'github-light',
        dark: 'github-dark',
    },
    { value: 'one', label: 'One', light: 'one-light', dark: 'one-dark-pro' },
    {
        value: 'catppuccin',
        label: 'Catppuccin',
        light: 'catppuccin-latte',
        dark: 'catppuccin-mocha',
    },
    {
        value: 'vitesse',
        label: 'Vitesse',
        light: 'vitesse-light',
        dark: 'vitesse-dark',
    },
    {
        value: 'rose-pine',
        label: 'Rosé Pine',
        light: 'rose-pine-dawn',
        dark: 'rose-pine',
    },
    {
        value: 'solarized',
        label: 'Solarized',
        light: 'solarized-light',
        dark: 'solarized-dark',
    },
    {
        value: 'gruvbox',
        label: 'Gruvbox',
        light: 'gruvbox-light-medium',
        dark: 'gruvbox-dark-medium',
    },
    {
        value: 'everforest',
        label: 'Everforest',
        light: 'everforest-light',
        dark: 'everforest-dark',
    },
    {
        value: 'night-owl',
        label: 'Night Owl',
        light: 'night-owl-light',
        dark: 'night-owl',
    },
    {
        value: 'kanagawa',
        label: 'Kanagawa',
        light: 'kanagawa-lotus',
        dark: 'kanagawa-wave',
    },
] as const satisfies readonly SyntaxThemeDefinition[];

export type SyntaxTheme = (typeof SYNTAX_THEMES)[number]['value'];

const syntaxThemePreference = createStoredPreference<SyntaxTheme>({
    key: 'syntax-theme',
    values: SYNTAX_THEMES.map((theme) => theme.value),
    fallback: 'github',
});

export const initializeSyntaxTheme = syntaxThemePreference.initialize;

export const findSyntaxTheme = (value: SyntaxTheme): SyntaxThemeDefinition =>
    SYNTAX_THEMES.find((theme) => theme.value === value) ?? SYNTAX_THEMES[0];

/**
 * The coloring of the code everywhere in the application: the blocks of the conversation, the SQL of the tables and
 * of the plans, the editor of the console.
 */
export function useSyntaxTheme() {
    const syntaxTheme = syntaxThemePreference.useValue();

    return {
        syntaxTheme,
        definition: findSyntaxTheme(syntaxTheme),
        updateSyntaxTheme: syntaxThemePreference.update,
    } as const;
}
