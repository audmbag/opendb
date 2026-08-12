import React, {  } from 'react';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from './reui/frame';

type Props = {
    title?: string;
    description?: string;
} & React.ComponentPropsWithoutRef<typeof Frame>;

export default function CardFrame({ title, description, children }: Props) {
    return (
        <Frame>
            <FrameHeader>
                <FrameTitle>{title}</FrameTitle>
                    <FrameDescription>
                        {description}
                    </FrameDescription>
            </FrameHeader>
            <FramePanel>
                {children}
            </FramePanel>
        </Frame>
    );
}