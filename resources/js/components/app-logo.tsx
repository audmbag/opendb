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

const Lightcolors = [
    "text-zinc-500",
    "text-zinc-600",
    "text-zinc-700",
    "text-zinc-800",
    "text-zinc-900",
    "text-zinc-950",
]

const Darkcolors = [
    "dark:text-zinc-600",
    "dark:text-zinc-500",
    "dark:text-zinc-400",
    "dark:text-zinc-300",
    "dark:text-zinc-200",
    "dark:text-zinc-100",
]

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
                        Lightcolors[index],
                        Darkcolors[index],
                    )}
                    style={
                        animate
                            ? {
                                  animation: `spinXInfinite 10s infinite`,
                                  animationDelay: `${index * 0.05}s`,
                              }
                            : {}
                    }
                >
                    {line}
                </pre>
            ))}
        </div>
    );
})

export default AppLogo;
