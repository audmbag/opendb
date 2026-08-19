import { CommandIcon, SettingsIcon } from 'lucide-react';
import Button from '@/components/form/button';
import { RecentProjects } from '@/components/recent-projects';
import { Kbd } from '@/components/ui/kbd';
import { useCommandModal } from '@/hooks/use-command-modal';
import { useSettingsDialog } from '@/hooks/use-settings-modal';

export default function Home() {

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
                    tooltip={['Ouvrir le panneau de commandes', ['Ctrl', 'K']]}
                >
                    Commandes
                </Button>
                <Button
                    onClick={() => openSettings('general')}
                    variant={'outline'}
                    icon={SettingsIcon}
                    tooltip={['Ouvrir les paramètres', ['Ctrl', ',']]}
                >
                    Paramètres
                </Button>
            </div>
        </div>
    );
}
