import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Trash2Icon } from 'lucide-react';
import type React from 'react';
import { useState } from 'react';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

type Props = {
    projectName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /**
     * Receives the name typed by the user, only once it is the name of the project.
     */
    onConfirm: (name: string) => void;
    processing?: boolean;
    error?: string;
};

/**
 * Asks the user to type the name of the project before deleting it, so that a project is never deleted by a misclick.
 */
export default function DeleteProjectDialog({
    projectName,
    open,
    onOpenChange,
    onConfirm,
    processing = false,
    error,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [typedName, setTypedName] = useState('');

    const canDelete = typedName === projectName && !processing;

    const changeOpen = (nextOpen: boolean) => {
        if (!nextOpen) {
            setTypedName('');
        }

        onOpenChange(nextOpen);
    };

    const confirm = (event: React.FormEvent) => {
        event.preventDefault();

        if (canDelete) {
            onConfirm(typedName);
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={changeOpen}>
            <AlertDialogContent
                size="sm"
                className="gap-0 overflow-hidden p-0"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    (event.currentTarget as HTMLElement)
                        .querySelector<HTMLInputElement>('input[name="name"]')
                        ?.focus();
                }}
            >
                <form onSubmit={confirm}>
                    <AlertDialogHeader className="p-4">
                        <AlertDialogMedia className="size-10 bg-destructive/10 text-destructive dark:bg-destructive/20">
                            <Trash2Icon className="size-5" />
                        </AlertDialogMedia>
                        <AlertDialogTitle>{t('projects.delete.title')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {t('projects.delete.description_before')}{' '}
                            <strong className="font-medium text-foreground select-all">
                                {projectName}
                            </strong>{' '}
                            {t('projects.delete.description_after')}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="px-4 pb-4">
                        <Input
                            name="name"
                            autoComplete="off"
                            placeholder={projectName}
                            aria-label={t('projects.delete.name_label')}
                            value={typedName}
                            onChange={(event) =>
                                setTypedName(event.target.value)
                            }
                            error={error}
                        />
                    </div>
                    <AlertDialogFooter className="bg-muted p-4">
                        <Button
                            variant="outline"
                            disabled={processing}
                            onClick={() => changeOpen(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            processing={processing}
                            disabled={!canDelete}
                        >
                            {t('common.delete')}
                        </Button>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
}
