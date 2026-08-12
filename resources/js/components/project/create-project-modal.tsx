import React  from 'react';
import Modal from '@/components/modal';
import ProjectForm from '@/components/project/project-form';

export default function CreateProjectModal({ open, onOpenChange }: Pick<React.ComponentProps<typeof Modal>, "open" | "onOpenChange">) {

    return (
        <Modal
            showHeader
            scrollable
            className="h-auto"
            open={open}
            onOpenChange={onOpenChange}
            title="Créer un nouveau projet"
            description="Formulaire pour créer un nouveau projet"
        >
            <ProjectForm onClose={() => onOpenChange && onOpenChange(false)} />
        </Modal>
    );
}
