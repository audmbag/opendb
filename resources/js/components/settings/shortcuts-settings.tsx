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
    return (
        <div className="flex flex-col gap-4">
            <Muted className="text-xs">
                Sur Mac, Ctrl correspond à la touche Cmd.
            </Muted>
            {SHORTCUT_GROUPS.map((group) => (
                <CardFrame
                    key={group.label}
                    title={group.label}
                    icon={KeyboardIcon}
                >
                    {group.shortcuts.map((shortcut) => (
                        <SettingRow
                            key={shortcut.description}
                            label={shortcut.description}
                        >
                            <div className="flex items-center gap-2">
                                {shortcut.keys.map((combination, index) => (
                                    <Fragment key={combination.join('+')}>
                                        {index > 0 && (
                                            <Muted className="text-xs">
                                                ou
                                            </Muted>
                                        )}
                                        <KbdGroup>
                                            {combination.map((key) => (
                                                <Kbd key={key}>{key}</Kbd>
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
