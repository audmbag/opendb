import { LoaderIcon  } from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import React from 'react';
import type {ReactNode} from 'react';
import IconStackGlow from '@/components/icon-stack-glow';
import { cn } from '@/lib/utils';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from './reui/frame';

type Props = {
    ref?: React.Ref<HTMLDivElement>;
    title?: string | React.ReactNode;
    description?: string | React.ReactNode;
    innerPadding?: boolean;
    icon?: LucideIcon;
    innerClassName?: string;
    processing?: boolean;
    actions?: ReactNode;
    badge?: ReactNode;
} & React.ComponentPropsWithoutRef<typeof Frame>;


export default function CardFrame({
    ref,
    title,
    description,
    innerPadding = true,
    children,
    innerClassName,
    icon: Icon,
    actions,
    processing,
    badge,
    ...props
}: Props) {
    return (
        <Frame ref={ref} {...props}>
            {title && (
                <FrameHeader className="flex flex-row items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-2">
                        {processing ? (
                            <LoaderIcon className="size-4 shrink-0 animate-spin text-muted-foreground" />
                        ) : (
                            Icon && <IconStackGlow icon={Icon} size={"sm"} />
                        )}
                        <div className="min-w-0">
                            <div className="flex items-start gap-2">
                                <FrameTitle className="truncate">
                                    {title}
                                </FrameTitle>
                                {badge}
                            </div>
                            {description && (
                                <FrameDescription className="truncate text-xs">
                                    {description}
                                </FrameDescription>
                            )}
                        </div>
                    </div>
                    {actions && (
                        <div className="flex shrink-0 items-center gap-1">
                            {actions}
                        </div>
                    )}
                </FrameHeader>
            )}
            <FramePanel
                className={cn(innerPadding ? 'p-4' : 'p-0', innerClassName)}
            >
                {children}
            </FramePanel>
        </Frame>
    );
}
