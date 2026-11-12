import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { LucideIcon } from 'lucide-react';
import { MonitorIcon, MoonIcon, SunIcon } from 'lucide-react';
import Tooltip from '@/components/tooltip';
import type { Appearance } from '@/hooks/use-apparence';
import { useAppearance } from '@/hooks/use-apparence';
import { cn } from '@/lib/utils';

/**
 * The themes of the interface, their label being a key of lang/{locale}/settings.php.
 */
export const APPEARANCES: { value: Appearance; label: string; icon: LucideIcon }[] = [
    { value: 'system', label: 'settings.appearances.system', icon: MonitorIcon },
    { value: 'light', label: 'settings.appearances.light', icon: SunIcon },
    { value: 'dark', label: 'settings.appearances.dark', icon: MoonIcon },
];

/**
 * The theme of the interface, chosen among the system one, light and dark, in a single segmented control.
 */
export default function ThemeToggle({ className }: { className?: string }) {
    const { t } = useLaravelReactI18n();
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="radiogroup"
            aria-label={t('settings.general.theme')}
            className={cn(
                'inline-flex items-center gap-0.5 rounded-lg bg-muted p-0.5',
                className,
            )}
        >
            {APPEARANCES.map((theme) => (
                <Tooltip key={theme.value} value={t(theme.label)}>
                    <button
                        type="button"
                        role="radio"
                        aria-checked={appearance === theme.value}
                        aria-label={t(theme.label)}
                        onClick={() => updateAppearance(theme.value)}
                        className={cn(
                            'inline-flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            appearance === theme.value &&
                                'bg-background text-foreground shadow-sm dark:bg-foreground/15',
                        )}
                    >
                        <theme.icon className="size-4" />
                    </button>
                </Tooltip>
            ))}
        </div>
    );
}
