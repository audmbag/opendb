import React from 'react';
import Modal from '@/components/modals/modal';
import ProjectForm from '@/components/project/project-form';

type Props = Pick<
    React.ComponentProps<typeof Modal>,
    'open' | 'onOpenChange'
>;

export default function EditProjectInfoModal({ open, onOpenChange }: Props) {
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
            <ProjectForm />
        </Modal>
    );
}
