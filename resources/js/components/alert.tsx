import {
    AlertCircleIcon,
    CheckCircleIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import React, { useMemo } from 'react';
import { Alert as UiAlert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { cn } from '@/lib/utils';

type Props = {
    variant?: "default" | "destructive" | "success" | "warning";
    title: string;
    message: string;
} & Omit<React.ComponentProps<typeof UiAlert>, "variant">;

const variantClass = {
    success: "bg-green-50 text-green-800 dark:bg-green-900 dark:text-green-200",
    warning: "bg-yellow-50 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200",
};

export default function Alert({ variant, title, message, className, ...props }: Props) {

    const alertVariant = useMemo(() => {
        return variant === 'destructive' || variant === 'default'
            ? variant
            : 'default'
    }, [variant]);

    const Icon = useMemo(() => {
        switch (variant) {
            case 'success':
                return <CheckCircleIcon className="text-green-500" />;
            case 'warning':
                return <AlertCircleIcon className="text-yellow-500" />;
            case 'destructive':
                return <TriangleAlertIcon className="text-red-500" />;
            default:
                return <AlertCircleIcon />;
        }
    }, [variant]);

    const cls = useMemo(() => {
        return variant ? variantClass[variant as keyof typeof variantClass] : undefined;
    }, [variant]);

    return (
        <UiAlert
            variant={alertVariant}
            className={cn(cls, className)}
            {...props}
        >
            {Icon}
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription>{message}</AlertDescription>
        </UiAlert>
    );
}
