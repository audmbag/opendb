import { CircleFadingPlusIcon, LucideIcon } from 'lucide-react';

import React from 'react';
import { createCallable } from 'react-call';
import {
    AlertDialog as UiAlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

export type AlertDialogProps = {
    title: string;
    description: string;
    icon?: LucideIcon;
    confirmText?: string;
    cancelText?: string;
    variant?: "default" | "destructive";
} & React.ComponentProps<typeof UiAlertDialog>;

const AlertDialog = createCallable<AlertDialogProps, boolean>(({ title, description, icon: Icon, confirmText, cancelText, call, variant, ...props }) => {

    return (
        <UiAlertDialog
            open={!call.ended}
            onOpenChange={() => call.end(false)}
            {...props}
        >
            <AlertDialogContent size="sm">
                <AlertDialogHeader>
                    <AlertDialogMedia className="bg-destructive/10 text-destructive dark:bg-destructive/20 dark:text-destructive">
                        {Icon ? <Icon /> : <CircleFadingPlusIcon />}
                    </AlertDialogMedia>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel onClick={() => call.end(false)}>
                        {cancelText || 'Annuler'}
                    </AlertDialogCancel>
                    <AlertDialogAction variant={variant} onClick={() => call.end(true)}>
                        {confirmText || 'Confirmer'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </UiAlertDialog>
    );
})


export default AlertDialog;
