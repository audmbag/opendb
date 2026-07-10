import { Dialog, DialogContent, DialogDescription, DialogTitle } from "@/components/ui/dialog";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { cn } from "@/lib/utils";


type Props = {
    title?: string;
    description?: string;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    children: React.ReactNode;
    showHeader?: boolean;
    className?: string;
};


export default function Modal({ title, description, open, onOpenChange, children, showHeader, className }: Props) {

    return <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent
            className={cn("overflow-hidden p-0 sm:max-w-200 h-120", className)}
        >
            <DialogTitle className="sr-only">{title || "Modal title"}</DialogTitle>
            <DialogDescription className="sr-only">
                {description || "Modal description"}
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
                <FramePanel className="p-0 h-full w-full">
                    {children}
                </FramePanel>
            </Frame>
        </DialogContent>
    </Dialog>
}