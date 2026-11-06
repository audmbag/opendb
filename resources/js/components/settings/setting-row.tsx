import type React from 'react';
import { Muted } from '@/components/typograpfy/typography';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    description?: React.ReactNode;
    htmlFor?: string;
    children: React.ReactNode;
    className?: string;
};

/**
 * A setting on one line: what it does on the left, the control changing it on the right.
 */
export default function SettingRow({
    label,
    description,
    htmlFor,
    children,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 border-b border-border py-3 first:pt-0 last:border-b-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                <label htmlFor={htmlFor} className="text-sm font-medium">
                    {label}
                </label>
                {description && (
                    <Muted className="text-xs">{description}</Muted>
                )}
            </div>
            <div className="shrink-0">{children}</div>
        </div>
    );
}
