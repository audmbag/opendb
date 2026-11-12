import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { LucideIcon} from 'lucide-react';
import { XIcon } from 'lucide-react';
import { Dialog as DialogPrimitive } from 'radix-ui';
import * as React from 'react';
import Button from '@/components/form/button';
import IconStackGlow from '@/components/icon-stack-glow';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Dialog, DialogDescription, DialogOverlay, DialogPortal, DialogTitle } from "@/components/ui/dialog";
import { cn } from "@/lib/utils";


type Props = {
    title?: string | React.ReactNode;
    description?: string;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    children: React.ReactNode;
    showHeader?: boolean;
    className?: string;
    scrollable?: boolean;
    icon?: LucideIcon;
    showCloseButton?: boolean;
    onEscapeKeyDown?: (event: KeyboardEvent) => void;
};


const CustomDialogContent = ({
    className,
    children,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Content>) => {
    return (
        <DialogPrimitive.Content
            data-slot="dialog-content"
            className={cn(
                'fixed top-1/2 left-1/2 z-50 grid w-full max-w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 gap-4 rounded-xl bg-popover p-4 text-sm text-popover-foreground ring-1 ring-foreground/10 duration-100 outline-none sm:max-w-sm data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95',
                className,
            )}
            {...props}
        >
            {children}
        </DialogPrimitive.Content>
    );
};



export default function Modal({
    title,
    description,
    open,
    onOpenChange,
    children,
    showHeader,
    className,
    scrollable,
    icon: Icon,
    showCloseButton = true,
    onEscapeKeyDown,
}: Props) {
    const { t } = useLaravelReactI18n();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay
                    className={cn({
                        'grid place-items-center overflow-y-auto': scrollable,
                    })}
                >
                    <CustomDialogContent
                        onEscapeKeyDown={onEscapeKeyDown}
                        className={cn(
                            'h-120 overflow-hidden p-0 sm:max-w-200',
                            {
                                'relative top-0 left-0 m-auto translate-x-0 translate-y-0':
                                    scrollable,
                            },
                            className,
                        )}
                    >
                        <DialogTitle className="sr-only">
                            {title || 'Modal title'}
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            {description || 'Modal description'}
                        </DialogDescription>
                        <Frame className="h-full w-full overflow-hidden p-2">
                            {showHeader && (
                                <FrameHeader className="flex flex-row items-center gap-4 px-2">
                                    {Icon && <IconStackGlow icon={Icon} />}
                                    <div className="flex-col gap-2">
                                        <FrameTitle>{title}</FrameTitle>
                                        <FrameDescription>
                                            {description}
                                        </FrameDescription>
                                    </div>
                                    {showCloseButton &&
                                        <div className="ml-auto">
                                            <Button
                                                variant="secondary"
                                                size="icon"
                                                onClick={() =>
                                                    onOpenChange?.(false)
                                                }
                                                icon={XIcon}
                                                aria-label={t('common.close')}
                                            />
                                        </div>
                                    }
                                </FrameHeader>
                            )}
                            <FramePanel className="h-full w-full p-0">
                                {children}
                            </FramePanel>
                        </Frame>
                    </CustomDialogContent>
                </DialogOverlay>
            </DialogPortal>
        </Dialog>
    );
}
