import { useLaravelReactI18n } from 'laravel-react-i18n';
import React from 'react';
import Modal from '@/components/modals/modal';
import ProjectForm from '@/components/project/project-form';

type Props = Pick<
    React.ComponentProps<typeof Modal>,
    'open' | 'onOpenChange'
>;

export default function EditProjectInfoModal({ open, onOpenChange }: Props) {
    const { t } = useLaravelReactI18n();

    return (
        <Modal
            showHeader
            scrollable
            className="h-auto"
            open={open}
            onOpenChange={onOpenChange}
            title={t('projects.create_new')}
            description={t('projects.create_new_description')}
        >
            <ProjectForm />
        </Modal>
    );
}
