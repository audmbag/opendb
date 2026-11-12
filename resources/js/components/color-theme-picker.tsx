import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CheckIcon } from 'lucide-react';
import { COLOR_THEMES, useColorTheme } from '@/hooks/use-color-theme';
import { cn } from '@/lib/utils';

/**
 * A miniature of the interface in a theme: its data-theme attribute gives it the colors, the radius and the font of
 * that theme, in the light or dark appearance of the page.
 */
function ThemePreview({ theme }: { theme: string }) {
    return (
        <span
            aria-hidden="true"
            data-theme={theme}
            className="flex h-16 overflow-hidden rounded-lg border border-border bg-background font-sans text-foreground"
        >
            <span className="flex w-1/4 flex-col gap-1 border-r border-sidebar-border bg-sidebar p-1.5">
                <span className="h-1.5 w-full rounded-full bg-sidebar-primary" />
                <span className="h-1.5 w-3/4 rounded-full bg-sidebar-foreground/20" />
                <span className="h-1.5 w-2/3 rounded-full bg-sidebar-foreground/20" />
            </span>
            <span className="flex flex-1 flex-col gap-1 p-1.5">
                <span className="flex items-center gap-1 rounded-sm border border-border bg-card p-1">
                    <span className="text-[10px] leading-none font-semibold">
                        Aa
                    </span>
                    <span className="h-1 flex-1 rounded-full bg-muted-foreground/40" />
                </span>
                <span className="mt-auto flex items-center gap-1">
                    <span className="h-3 w-8 rounded-sm bg-primary" />
                    <span className="h-3 w-5 rounded-sm bg-secondary" />
                    <span className="h-3 w-3 rounded-sm bg-accent" />
                </span>
            </span>
        </span>
    );
}

/**
 * The themes of the interface, each one shown by a miniature of the interface in its colors, the chosen one checked.
 */
export default function ColorThemePicker({
    className,
}: {
    className?: string;
}) {
    const { t } = useLaravelReactI18n();
    const { colorTheme, updateColorTheme } = useColorTheme();

    return (
        <div className={cn('@container', className)}>
            <div
                role="radiogroup"
                aria-label={t('settings.general.color_theme')}
                className="grid grid-cols-2 gap-2 @sm:grid-cols-3 @xl:grid-cols-5"
            >
                {COLOR_THEMES.map((theme) => {
                    const isChecked = colorTheme === theme.value;

                    return (
                        <button
                            key={theme.value}
                            type="button"
                            role="radio"
                            aria-checked={isChecked}
                            aria-label={t(theme.label)}
                            onClick={() => updateColorTheme(theme.value)}
                            className={cn(
                                'flex flex-col gap-1.5 rounded-xl p-1 text-left ring-1 ring-transparent transition-shadow outline-none hover:ring-border focus-visible:ring-2 focus-visible:ring-ring',
                                isChecked &&
                                    'ring-2 ring-primary hover:ring-primary',
                            )}
                        >
                            <ThemePreview theme={theme.value} />
                            <span className="flex items-center justify-between gap-1 px-0.5 text-xs font-medium">
                                {t(theme.label)}
                                {isChecked && (
                                    <CheckIcon className="size-3.5 text-primary" />
                                )}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
