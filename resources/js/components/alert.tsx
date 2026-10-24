import {
    AlertCircleIcon,
    CheckCircleIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import React, { useMemo } from 'react';
import type { ButtonProps } from '@/components/form/button';
import Button from '@/components/form/button';
import {
    Alert as UiAlert,
    AlertAction,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { cn } from '@/lib/utils';
import CardFrame from '@/components/card-frame';

type Props = {
    variant?: 'default' | 'destructive' | 'success' | 'warning';
    mode?: 'default' | 'frame';
    title: string;
    message: string;
    buttons?: (ButtonProps & { label: string })[];
} & Omit<React.ComponentProps<typeof UiAlert>, 'variant'>;

const variantClass = {
    success: "bg-green-50 text-green-800 dark:bg-green-900 dark:text-green-200",
    warning: "bg-yellow-50 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200",
    destructive: "bg-red-50 text-red-800 dark:bg-red-900 dark:text-red-200",
};

export default function Alert({ variant, title, message, className, buttons, mode = 'default', ...props }: Props) {

    const alertVariant = useMemo(() => {
        return variant === 'destructive' || variant === 'default'
            ? variant
            : 'default'
    }, [variant]);

    let color = 'text-gray-800 dark:text-gray-200';

    if (variant === 'success') {
        color = 'text-green-800 dark:text-green-200';
    } else if (variant === 'warning') {
        color = 'text-yellow-800 dark:text-yellow-200';
    } else if (variant === 'destructive') {
        color = 'text-red-800 dark:text-red-200';
    }

    const Icon = useMemo(() => {
        switch (variant) {
            case 'success':
                return <CheckCircleIcon className="text-green-500 size-4" />;
            case 'warning':
                return <AlertCircleIcon className="text-yellow-500 size-4" />;
            case 'destructive':
                return <TriangleAlertIcon className="text-red-500 size-4" />;
            default:
                return <AlertCircleIcon />;
        }
    }, [variant]);

    const cls = useMemo(() => {
        return variant ? variantClass[variant as keyof typeof variantClass] : undefined;
    }, [variant]);

    const alert = (
        <UiAlert
            variant={alertVariant}
            className={cn(
                'flex gap-4 px-4 py-4',
                mode === 'frame' && 'border-0',
                cls,
                className,
            )}
            {...props}
        >
            {Icon}
            <div className="flex flex-col gap-2">
                <AlertTitle>{title}</AlertTitle>
                <AlertDescription className={color}>{message}</AlertDescription>
                <AlertAction className="relative top-0 right-0 pt-2">
                    {buttons?.map((button, index) => (
                        <Button key={index} {...button}>
                            {button.label}
                        </Button>
                    ))}
                </AlertAction>
            </div>
        </UiAlert>
    );

    if(mode === 'frame') {
        return (
            <CardFrame innerPadding={false}>
                {alert}
            </CardFrame>
        )
    }

    return alert;
}
