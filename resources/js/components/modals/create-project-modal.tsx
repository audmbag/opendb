import { useLaravelReactI18n } from 'laravel-react-i18n';
import React from 'react';
import CloningConnectionModal from '@/components/modals/cloning-database-modal';
import Modal from '@/components/modals/modal';
import ProjectForm from '@/components/project/project-form';
import { useProjectModal } from '@/hooks/use-project-modal';


export default function CreateProjectModal() {
    const { t } = useLaravelReactI18n();

    const { setOpen, open } = useProjectModal();

    return (
        <>
            <Modal
                showHeader
                scrollable
                className="h-auto"
                open={open}
                onOpenChange={setOpen}
                title={t('projects.create_new')}
                description={t('projects.create_new_description')}
            >
                <ProjectForm />
            </Modal>
            <CloningConnectionModal />
        </>
    );
}
