import React from 'react';
import Modal from '@/components/modal';
import ProjectForm from '@/components/project/project-form';
import type { Project } from '@/types/models/project';


type Props = Pick<
    React.ComponentProps<typeof Modal>,
    'open' | 'onOpenChange'
> & {
    part: 'config' | 'info';
    project: Project;
};

export default function EditProjectInfoModal({ open, onOpenChange, part, project }: Props) {
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
            <ProjectForm
                project={project}
                part={part}
                onClose={() => onOpenChange && onOpenChange(false)}
                onSuccess={() => onOpenChange && onOpenChange(false)}
            />
        </Modal>
    );
}
