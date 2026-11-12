import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CommandIcon,
    SettingsIcon,
} from 'lucide-react';
import Button from '@/components/form/button';
import { RecentProjects } from '@/components/recent-projects';
import { useCommandModal } from '@/hooks/use-command-modal';
import { useSettingsDialog } from '@/hooks/use-settings-modal';

export default function Home() {

    const { t } = useLaravelReactI18n();
    const { setOpen: openCommandModal } = useCommandModal();
    const { open: openSettings } = useSettingsDialog();

    return (
        <div className="mx-auto flex w-full flex-col items-center gap-4">
            <div className="w-full max-w-5xl pt-4">
                <RecentProjects />
            </div>

            <div className="flex justify-center gap-2 pt-4">
                <Button
                    onClick={() => openCommandModal(true)}
                    variant={'outline'}
                    icon={CommandIcon}
                    tooltip={[t('shortcuts.command_palette'), ['Ctrl', 'K']]}
                >
                    {t('commands.button')}
                </Button>
                <Button
                    onClick={() => openSettings('general')}
                    variant={'outline'}
                    icon={SettingsIcon}
                    tooltip={[t('shortcuts.settings'), ['Ctrl', ',']]}
                >
                    {t('settings.title')}
                </Button>
            </div>
        </div>
    );
}
