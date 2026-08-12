import { XIcon } from 'lucide-react';
import { Dialog as DialogPrimitive } from 'radix-ui';
import * as React from 'react';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogOverlay, DialogPortal, DialogTitle } from "@/components/ui/dialog";
import { cn } from "@/lib/utils";


type Props = {
    title?: string;
    description?: string;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    children: React.ReactNode;
    showHeader?: boolean;
    className?: string;
    scrollable?: boolean;
};


const CustomDialogContent = ({
    className,
    children,
    showCloseButton = true,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Content> & {
    showCloseButton?: boolean;
}) => {
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
            {showCloseButton && (
                <DialogPrimitive.Close data-slot="dialog-close" asChild>
                    <Button
                        variant="ghost"
                        className="absolute top-2 right-2"
                        size="icon-sm"
                    >
                        <XIcon />
                        <span className="sr-only">Close</span>
                    </Button>
                </DialogPrimitive.Close>
            )}
        </DialogPrimitive.Content>
    );
};



export default function Modal({ title, description, open, onOpenChange, children, showHeader, className, scrollable }: Props) {

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay
                    className={cn({
                        'grid place-items-center overflow-y-auto': scrollable,
                    })}
                >
                    <CustomDialogContent
                        className={cn(
                            'h-120 overflow-hidden p-0 sm:max-w-200',
                            {
                                'relative top-0 left-0 m-auto translate-x-0 translate-y-0':
                                    scrollable,
                            },
                            className,
                        )}
                        showCloseButton={false}
                    >
                        <DialogTitle className="sr-only">
                            {title || 'Modal title'}
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            {description || 'Modal description'}
                        </DialogDescription>
                        <Frame className="w-full overflow-hidden">
                            {showHeader && (
                                <FrameHeader>
                                    <FrameTitle>{title}</FrameTitle>
                                    <FrameDescription>
                                        {description}
                                    </FrameDescription>
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
