import React, {  } from 'react';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from './reui/frame';
import { cn } from '@/lib/utils';

type Props = {
    title?: string;
    description?: string;
    innerPadding?: boolean;
    innerClassName?: string;
} & React.ComponentPropsWithoutRef<typeof Frame>;


export default function CardFrame({ title, description, innerPadding = true, children, innerClassName, ...props }: Props) {
    return (
        <Frame {...props}>
            {title && (
                <FrameHeader>
                    <FrameTitle>{title}</FrameTitle>
                    {description && (
                        <FrameDescription>{description}</FrameDescription>
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
