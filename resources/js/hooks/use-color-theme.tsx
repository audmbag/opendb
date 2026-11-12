import { createStoredPreference } from '@/lib/stored-preference';
import { withoutTransitions } from '@/lib/without-transitions';

/**
 * The themes of the interface, their label being a key of lang/{locale}/settings.php.
 */
export const COLOR_THEMES = [
    { value: 'neutral', label: 'settings.color_themes.neutral' },
    { value: 'slate', label: 'settings.color_themes.slate' },
    { value: 'ocean', label: 'settings.color_themes.ocean' },
    { value: 'forest', label: 'settings.color_themes.forest' },
    { value: 'amethyst', label: 'settings.color_themes.amethyst' },
    { value: 'rose', label: 'settings.color_themes.rose' },
    { value: 'tangerine', label: 'settings.color_themes.tangerine' },
    { value: 'amber', label: 'settings.color_themes.amber' },
    { value: 'midnight', label: 'settings.color_themes.midnight' },
    { value: 'paper', label: 'settings.color_themes.paper' },
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
