import { useLaravelReactI18n } from 'laravel-react-i18n';
import { KeyboardIcon } from 'lucide-react';
import { Fragment } from 'react';
import CardFrame from '@/components/card-frame';
import SettingRow from '@/components/settings/setting-row';
import { Muted } from '@/components/typograpfy/typography';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { SHORTCUT_GROUPS } from '@/lib/shortcuts';

/**
 * The keyboard shortcuts of the application. On a Mac, Ctrl is Cmd.
 */
export default function ShortcutsSettings() {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex flex-col gap-4">
            <Muted className="text-xs">
                {t('shortcuts.mac_hint')}
            </Muted>
            {SHORTCUT_GROUPS.map((group) => (
                <CardFrame
                    key={group.label}
                    title={t(group.label)}
                    icon={KeyboardIcon}
                >
                    {group.shortcuts.map((shortcut) => (
                        <SettingRow
                            key={shortcut.description}
                            label={t(shortcut.description)}
                        >
                            <div className="flex items-center gap-2">
                                {shortcut.keys.map((combination, index) => (
                                    <Fragment key={combination.join('+')}>
                                        {index > 0 && (
                                            <Muted className="text-xs">
                                                {t('shortcuts.or')}
                                            </Muted>
                                        )}
                                        <KbdGroup>
                                            {combination.map((key) => (
                                                <Kbd key={key}>{key.startsWith('shortcuts.') ? t(key) : key}</Kbd>
                                            ))}
                                        </KbdGroup>
                                    </Fragment>
                                ))}
                            </div>
                        </SettingRow>
                    ))}
                </CardFrame>
            ))}
        </div>
    );
}
