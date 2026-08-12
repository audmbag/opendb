import { CommandIcon, SettingsIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import Button from '@/components/form/button';
import { RecentProjects } from '@/components/recent-projects';
import { Lead } from '@/components/typograpfy/typography';
import { Kbd } from '@/components/ui/kbd';
import { useCommandModal } from '@/hooks/use-command-modal';
import { useSettingsDialog } from '@/hooks/use-settings-modal';

export default function Home() {

    const { setOpen: openCommandModal } = useCommandModal();
    const { setOpen: openSettings } = useSettingsDialog();

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
                    tooltip={
                        <>
                            Ouvrir le panneau de commandes <Kbd>Ctrl</Kbd> +{' '}
                            <Kbd>K</Kbd>
                        </>
                    }
                >
                    Commandes
                </Button>
                <Button
                    onClick={() => openSettings(true)}
                    variant={'outline'}
                    icon={SettingsIcon}
                    tooltip={
                        <>
                            Ouvrir les paramètres <Kbd>Ctrl</Kbd> + <Kbd>,</Kbd>
                        </>
                    }
                >
                    Paramètres
                </Button>
            </div>
        </div>
    );
}
