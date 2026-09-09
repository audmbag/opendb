import React, { Fragment } from 'react';
import { Kbd } from '@/components/ui/kbd';
import * as TooltipUI from '@/components/ui/tooltip';


export type TooltipProps = {
    value: React.ReactNode | [string, string[]];
    variant?: 'default' | 'destructive' | 'success';
    children: React.ReactNode;
} & React.ComponentProps<typeof TooltipUI.TooltipContent>;

export default function Tooltip({ value, variant = "default", children, ...props }: TooltipProps) {

    const tooltipColorClass =
        variant === 'destructive'
            ? 'text-red-300 dark:text-red-600'
            : variant === 'success'
              ? 'text-green-300 dark:text-green-600'
              : undefined;


    return (
        <TooltipUI.Tooltip>
            <TooltipUI.TooltipTrigger asChild>
                {children}
            </TooltipUI.TooltipTrigger>
            {value && (
                <TooltipUI.TooltipContent
                    className={tooltipColorClass}
                    {...props}
                >
                    {Array.isArray(value) ? (
                        <p className={'wrap-anywhere'}>
                            {value[0]}{' '}
                            {value[1].map((item, index) => (
                                <Fragment key={index}>
                                    <Kbd>{item}</Kbd>
                                    {index < value[1].length - 1 && ' + '}
                                </Fragment>
                            ))}
                        </p>
                    ) : (
                        <p className={'wrap-anywhere'}>{value}</p>
                    )}
                </TooltipUI.TooltipContent>
            )}
        </TooltipUI.Tooltip>
    );
}
