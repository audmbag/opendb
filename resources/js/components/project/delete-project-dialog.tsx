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
                        <AlertDialogTitle>Supprimer le projet</AlertDialogTitle>
                        <AlertDialogDescription>
                            Ses conversations et la mémoire de l'agent seront
                            supprimées définitivement. Saisissez{' '}
                            <strong className="font-medium text-foreground select-all">
                                {projectName}
                            </strong>{' '}
                            pour confirmer.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="px-4 pb-4">
                        <Input
                            name="name"
                            autoComplete="off"
                            placeholder={projectName}
                            aria-label="Nom du projet à supprimer"
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
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            processing={processing}
                            disabled={!canDelete}
                        >
                            Supprimer
                        </Button>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
}
