import AppLogo from '@/components/app-logo';
import Button from '@/components/form/button';
import { RecentProjects } from '@/components/recent-projects';
import { Lead } from '@/components/typograpfy/typography';
import { Kbd } from '@/components/ui/kbd';
import { useCommandModal } from '@/hooks/use-command-modal';
import { useSettingsDialog } from '@/hooks/use-settings-modal';
import { CommandIcon, SettingsIcon } from 'lucide-react';



export default function HomeCool() {

    const { setOpen: openCommandModal } = useCommandModal();
    const { setOpen: openSettings } = useSettingsDialog();

    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-4">
            
            <AppLogo />

            <div className="pt-4 text-center">
                <Lead>
                    Votre agent AI personnel pour la gestion et la manipulation de vos bases de données.
                </Lead>
            </div>

            <div className="pt-4 w-full max-w-5xl">
                <RecentProjects />
            </div>

            <div className="flex pt-4 justify-center gap-2">
                <Button
                    onClick={() => openCommandModal(true)}
                    variant={"outline"}
                    icon={CommandIcon}
                    tooltip={() => <>Ouvrir le panneau de commandes <Kbd>Ctrl</Kbd> + <Kbd>K</Kbd></>}
                >
                    Commandes
                </Button>
                <Button
                    onClick={() => openSettings(true)}
                    variant={"outline"}
                    icon={SettingsIcon}
                    tooltip={() => <>Ouvrir les paramètres <Kbd>Ctrl</Kbd> + <Kbd>,</Kbd></>}
                >
                    Paramètres
                </Button>
            </div>
        </div>
    );
}
