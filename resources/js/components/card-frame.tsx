import React, {  } from 'react';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from './reui/frame';

type Props = {
    title?: string;
    description?: string;
    innerPadding?: boolean;
} & React.ComponentPropsWithoutRef<typeof Frame>;


export default function CardFrame({ title, description, innerPadding = true, children, ...props }: Props) {
    return (
        <Frame {...props}>
            {
                title &&
                <FrameHeader>
                    <FrameTitle>{title}</FrameTitle>
                    {description && (
                        <FrameDescription>{description}</FrameDescription>
                    )}
                </FrameHeader>
            }
            <FramePanel className={innerPadding ? "p-4" : "p-0"}>
                {children}
            </FramePanel>
        </Frame>
    );
}
