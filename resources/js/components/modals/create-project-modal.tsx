import React from 'react';
import CloningConnectionModal from '@/components/modals/cloning-project-modal';
import Modal from '@/components/modals/modal';
import ProjectForm from '@/components/project/project-form';
import { useProjectModal } from '@/hooks/use-project-modal';


export default function CreateProjectModal() {

    const { setOpen, open } = useProjectModal();

    return (
        <>
            <Modal
                showHeader
                scrollable
                className="h-auto"
                open={open}
                onOpenChange={setOpen}
                title="Créer un nouveau projet"
                description="Formulaire pour créer un nouveau projet"
            >
                <ProjectForm />
            </Modal>
            <CloningConnectionModal />
        </>
    );
}
