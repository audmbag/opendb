import {
    BookOpenTextIcon,
    CommandIcon,
    DatabaseIcon,
    SettingsIcon,
} from 'lucide-react';
import Button from '@/components/form/button';
import { RecentProjects } from '@/components/recent-projects';
import { useCommandModal } from '@/hooks/use-command-modal';
import { useSettingsDialog } from '@/hooks/use-settings-modal';
import Wizard from '@/components/form/wizard';
import ProjectInfoForm from '@/components/project/project-info-form';
import DriverConfigForm from '@/components/project/driver-config-form';

export default function Home() {

    const { setOpen: openCommandModal } = useCommandModal();
    const { open: openSettings } = useSettingsDialog();


    return (
        <div className="mx-auto flex w-full flex-col items-center gap-4">
            <Wizard steps={[
                {
                    id: 'info',
                    header: {
                        icon: BookOpenTextIcon,
                        title: 'Informations du projet',
                    },
                    content: <ProjectInfoForm />
                },
                {
                    id: 'database',
                    header: {
                        icon: DatabaseIcon,
                        title: 'Informations de la BDD',
                    },
                    content: <DriverConfigForm />
                }
            ]} />
            {/*<div className="w-full max-w-5xl pt-4">*/}
            {/*    <RecentProjects />*/}
            {/*</div>*/}

            {/*<div className="flex justify-center gap-2 pt-4">*/}
            {/*    <Button*/}
            {/*        onClick={() => openCommandModal(true)}*/}
            {/*        variant={'outline'}*/}
            {/*        icon={CommandIcon}*/}
            {/*        tooltip={['Ouvrir le panneau de commandes', ['Ctrl', 'K']]}*/}
            {/*    >*/}
            {/*        Commandes*/}
            {/*    </Button>*/}
            {/*    <Button*/}
            {/*        onClick={() => openSettings('general')}*/}
            {/*        variant={'outline'}*/}
            {/*        icon={SettingsIcon}*/}
            {/*        tooltip={['Ouvrir les paramètres', ['Ctrl', ',']]}*/}
            {/*    >*/}
            {/*        Paramètres*/}
            {/*    </Button>*/}
            {/*</div>*/}
        </div>
    );
}
