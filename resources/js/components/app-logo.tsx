import React, { memo, useMemo } from 'react';
import { cn } from "@/lib/utils"

const appLogo = [
    '  ██████╗  ██████╗  ███████╗ ███╗   ██╗ ██████╗  ██████╗ ',
    ' ██╔═══██╗ ██╔══██╗ ██╔════╝ ████╗  ██║ ██╔══██╗ ██╔══██╗',
    ' ██║   ██║ ██████╔╝ █████╗   ██╔██╗ ██║ ██║  ██║ ██████╔╝',
    ' ██║   ██║ ██╔═══╝  ██╔══╝   ██║╚██╗██║ ██║  ██║ ██╔══██╗',
    ' ╚██████╔╝ ██║      ███████╗ ██║ ╚████║ ██████╔╝ ██████╔╝',
    '  ╚═════╝  ╚═╝      ╚══════╝ ╚═╝  ╚═══╝ ╚═════╝  ╚═════╝ ',
]

const iconLogo = [
    '  ██████╗ ',
    ' ██╔═══██╗',
    ' ██║   ██║',
    ' ██║   ██║',
    ' ╚██████╔╝',
    '  ╚═════╝ ',
]

/**
 * The color of each line of the logo, a gradient from the muted foreground to the primary color of the current theme,
 * so that it follows the color theme and the appearance of the interface.
 */
const lineColors = appLogo.map(
    (_, index) => `color-mix(in oklch, var(--primary) ${Math.round((index / (appLogo.length - 1)) * 100)}%, var(--muted-foreground))`,
);

interface AppLogoProps extends React.ComponentProps<'div'> {
    icon?: boolean;
    animate?: boolean;
    size?: 'default' | 'xs' | 'sm' | 'lg';
}

const AppLogo = memo(({ icon = false, size = "default", animate = true, className, ...props }: AppLogoProps) => {
    const targetLogo = icon ? iconLogo : appLogo;

    const sizeClass = useMemo(() => {
        switch (size) {
            case 'xs':
                return 'text-[6px]';
            case 'sm':
                return 'text-[8px] md:text-[10px]';
            case 'lg':
                return 'text-xs md:text-sm';
            case 'default':
                return 'text-xs md:text-sm';
        }
    }, [size]);

    return (
        <div className={cn(className)} {...props}>
            {animate && (
                <style>
                    {`
                    @keyframes spinXInfinite {
                        0% {
                            transform: perspective(600px) rotateX(0deg);
                            animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
                        }
                        12.7% {
                            transform: perspective(600px) rotateX(360deg);
                        }
                        100% {
                            transform: perspective(600px) rotateX(360deg);
                        }
                    }
                    `}
                </style>
            )}

            {targetLogo.map((line, index) => (
                <pre
                    key={index}
                    className={cn(
                        'origin-center',
                        sizeClass,
                    )}
                    style={{
                        color: lineColors[index],
                        ...(animate && {
                            animation: `spinXInfinite 10s infinite`,
                            animationDelay: `${index * 0.05}s`,
                        }),
                    }}
                >
                    {line}
                </pre>
            ))}
        </div>
    );
})

export default AppLogo;
