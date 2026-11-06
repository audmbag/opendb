import { createStoredPreference } from '@/lib/stored-preference';
import { withoutTransitions } from '@/lib/without-transitions';

export const COLOR_THEMES = [
    { value: 'neutral', label: 'Neutre' },
    { value: 'slate', label: 'Ardoise' },
    { value: 'ocean', label: 'Océan' },
    { value: 'forest', label: 'Forêt' },
    { value: 'amethyst', label: 'Améthyste' },
    { value: 'rose', label: 'Rose' },
    { value: 'tangerine', label: 'Mandarine' },
    { value: 'amber', label: 'Ambre' },
    { value: 'midnight', label: 'Minuit' },
    { value: 'paper', label: 'Papier' },
] as const;

export type ColorTheme = (typeof COLOR_THEMES)[number]['value'];

/**
 * The theme of the interface, like the presets of shadcn: all its colors, its radius and its font, defined in app.css
 * for the data-theme attribute of the page. The neutral one is the default, without attribute.
 */
const colorThemePreference = createStoredPreference<ColorTheme>({
    key: 'color-theme',
    values: COLOR_THEMES.map((theme) => theme.value),
    fallback: 'neutral',
    cookie: 'color_theme',
    apply: (theme) =>
        withoutTransitions(() => {
            if (theme === 'neutral') {
                delete document.documentElement.dataset.theme;
            } else {
                document.documentElement.dataset.theme = theme;
            }
        }),
});

export const initializeColorTheme = colorThemePreference.initialize;

export function useColorTheme() {
    const colorTheme = colorThemePreference.useValue();

    return {
        colorTheme,
        updateColorTheme: colorThemePreference.update,
    } as const;
}
