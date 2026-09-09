import type { LucideIcon } from 'lucide-react';
import { CircleFadingPlusIcon } from 'lucide-react';

import React, { useMemo } from 'react';
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
import { cn } from '@/lib/utils';

export type AlertDialogProps = {
    title: string;
    description: string;
    icon?: LucideIcon;
    confirmText?: string;
    cancelText?: string;
    variant?: "default" | "destructive";
} & React.ComponentProps<typeof UiAlertDialog>;

const AlertDialog = createCallable<AlertDialogProps, boolean>(({ title, description, icon: Icon, confirmText, cancelText, call, variant, ...props }) => {

    const variantClasses = useMemo(() => {
        switch (variant) {
            case 'destructive':
                return 'bg-destructive/10 text-destructive dark:bg-destructive/20 dark:text-destructive'
            case 'default':
                return undefined
            default:
                return undefined
        }
    }, [variant])

    return (
        <UiAlertDialog
            open={!call.ended}
            onOpenChange={() => call.end(false)}
            {...props}
        >
            <AlertDialogContent size="sm" className="gap-0 overflow-hidden p-0">
                <AlertDialogHeader className="p-4">
                    <AlertDialogMedia className={cn('size-10', variantClasses)}>
                        {Icon ? (
                            <Icon className="size-5" />
                        ) : (
                            <CircleFadingPlusIcon className="size-5" />
                        )}
                    </AlertDialogMedia>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter className="bg-muted p-4">
                    <AlertDialogCancel onClick={() => call.end(false)}>
                        {cancelText || 'Annuler'}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        variant={variant}
                        onClick={() => call.end(true)}
                    >
                        {confirmText || 'Confirmer'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </UiAlertDialog>
    );
})


export default AlertDialog;
