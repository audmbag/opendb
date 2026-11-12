import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CodeIcon, PaletteIcon } from 'lucide-react';
import CardFrame from '@/components/card-frame';
import ColorThemePicker from '@/components/color-theme-picker';
import Select from '@/components/form/select';
import SettingRow from '@/components/settings/setting-row';
import SqlCodeBlock from '@/components/tables/sql-code-block';
import ThemeToggle from '@/components/theme-toggle';
import type { Locale } from '@/hooks/use-locale';
import { LOCALES, useLocale } from '@/hooks/use-locale';
import type { SyntaxTheme } from '@/hooks/use-syntax-theme';
import { SYNTAX_THEMES, useSyntaxTheme } from '@/hooks/use-syntax-theme';

const syntaxThemes = SYNTAX_THEMES.map((theme) => ({
    label: theme.label,
    value: theme.value,
}));

const locales = LOCALES.map((locale) => ({
    label: locale.label,
    value: locale.value,
}));

/**
 * The settings of this computer only, kept by the browser.
 */
export default function GeneralSettings() {
    const { t } = useLaravelReactI18n();
    const { locale, updateLocale } = useLocale();
    const { syntaxTheme, updateSyntaxTheme } = useSyntaxTheme();

    return (
        <div className="flex flex-col gap-4">
            <CardFrame
                title={t('settings.general.appearance')}
                description={t('settings.general.appearance_description')}
                icon={PaletteIcon}
            >
                <SettingRow
                    label={t('settings.general.language')}
                    description={t('settings.general.language_description')}
                >
                    <Select
                        name="locale"
                        items={locales}
                        value={locale}
                        position="item-aligned"
                        onValueChange={(value) =>
                            updateLocale(value as Locale)
                        }
                    />
                </SettingRow>
                <SettingRow
                    label={t('settings.general.theme')}
                    description={t('settings.general.theme_description')}
                >
                    <ThemeToggle />
                </SettingRow>
                <SettingRow
                    label={t('settings.general.color_theme')}
                    description={t('settings.general.color_theme_description')}
                    className="sm:flex-col sm:items-stretch"
                >
                    <ColorThemePicker />
                </SettingRow>
            </CardFrame>
            <CardFrame
                title={t('settings.general.syntax_highlighting')}
                description={t('settings.general.syntax_highlighting_description')}
                icon={CodeIcon}
            >
                <SettingRow
                    label={t('settings.general.code_theme')}
                    description={t('settings.general.code_theme_description')}
                >
                    <Select
                        name="syntax_theme"
                        items={syntaxThemes}
                        value={syntaxTheme}
                        position="item-aligned"
                        onValueChange={(value) =>
                            updateSyntaxTheme(value as SyntaxTheme)
                        }
                    />
                </SettingRow>
                <SqlCodeBlock
                    code={t('settings.general.preview_query')}
                    aria-label={t('settings.general.code_preview')}
                    className="mt-3 [&_pre]:text-xs"
                />
            </CardFrame>
        </div>
    );
}
