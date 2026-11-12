import type { VariantProps } from "class-variance-authority";
import type { LucideIcon } from "lucide-react";
import React, { useCallback } from "react";
import type { AlertDialogProps } from '@/components/alert-dialog';
import AlertDialog from '@/components/alert-dialog';
import { Spinner } from "@/components/spinner";
import type { TooltipProps } from '@/components/tooltip';
import Tooltip from '@/components/tooltip';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { cn } from '@/lib/utils';
import type { buttonVariants} from "../ui/button";
import { Button as UIButton } from "../ui/button";


export type ButtonProps = React.ComponentProps<typeof UIButton> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
        icon?:
            | LucideIcon
            | ((props: React.SVGProps<SVGSVGElement>) => React.ReactElement);
        tooltip?: TooltipProps['value'];
        tooltipColor?: TooltipProps['variant'];
        processing?: boolean;
        kbd?: string[];
        stateAppearance?: 'success' | 'destructive' | 'warning';
        confirmation?: Omit<AlertDialogProps, 'variant'>;
    };


export default function Button ({asChild, icon: Icon, tooltip, tooltipColor, processing, type, kbd = [], stateAppearance, className, confirmation, onClick, ...props}: ButtonProps)  {

    const handleClick = useCallback<React.MouseEventHandler<HTMLButtonElement>>(async (event) => {
        if (confirmation) {
            event.preventDefault();
            const result = await AlertDialog.call({
                ...confirmation,
                variant: "destructive"
            });

            if(result) {
                onClick?.(event);
            }
        } else {
            onClick?.(event);
        }
    }, [onClick, confirmation]);


    return (
        <Tooltip
            value={tooltip}
            variant={stateAppearance ? stateAppearance : tooltipColor}
        >
            <UIButton
                asChild={asChild}
                type={type || 'button'}
                disabled={props.disabled || processing}
                className={cn({
                    'bg-green-50 text-green-700 hover:bg-green-50 hover:text-green-700 dark:bg-green-950 dark:text-green-300 dark:hover:bg-green-950 dark:hover:text-green-300' : stateAppearance == 'success',
                    'bg-red-50 text-red-700 hover:bg-red-50 hover:text-red-700 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-950 dark:hover:text-red-300' : stateAppearance == 'destructive',
                    'bg-yellow-50 text-yellow-700 hover:bg-yellow-50 hover:text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300 dark:hover:bg-yellow-950 dark:hover:text-yellow-300' : stateAppearance == 'warning',
                }, className)}
                onClick={handleClick}
                {...props}
            >
                {processing ? <Spinner /> : Icon && <Icon />}
                {props.children}
                {kbd?.length > 0 && (
                    <KbdGroup>
                        {kbd?.map((key) => (
                            <Kbd
                                key={key}
                                data-icon="inline-end"
                                className="translate-x-0.5"
                            >
                                {key}
                            </Kbd>
                        ))}
                    </KbdGroup>
                )}
            </UIButton>
        </Tooltip>
    );
}
