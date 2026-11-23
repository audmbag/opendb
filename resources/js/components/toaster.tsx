import type { CSSProperties, PointerEvent } from 'react';
import type { ToasterProps } from 'sonner';
import { Toaster as UiToast } from '@/components/ui/sonner';
import { useAppearance } from '@/hooks/use-apparence';

/**
 * Neutral toasts, inverted against the application theme: light on a dark
 * interface and dark on a light one, tinted with the primary colour and set in
 * the font of the active colour theme. Only the icon carries the tone of the
 * message, and the buttons sit on the right, centred on the content.
 *
 * A press on a toast stops at the React root: the open dialogs and menus listen for it on the document to close, and
 * a toast, its action button included, is not outside of them.
 */
export default function Toaster(props: ToasterProps) {
    const { resolvedAppearance } = useAppearance();

    const keepLayersOpen = (event: PointerEvent) =>
        event.nativeEvent.stopPropagation();

    return (
        <div className="contents" onPointerDown={keepLayersOpen}>
            <UiToast
                position="bottom-center"
                theme={resolvedAppearance === 'dark' ? 'light' : 'dark'}
                style={
                    {
                        '--width': '400px',
                        '--normal-bg':
                            'color-mix(in oklch, var(--foreground) 90%, var(--primary))',
                        '--normal-text': 'var(--background)',
                        '--normal-border': 'transparent',
                        '--border-radius': 'calc(var(--radius) + 4px)',
                    } as CSSProperties
                }
                toastOptions={{
                    classNames: {
                        toast: 'cn-toast items-center! gap-2.5! border-0! px-3.5! py-3! font-sans! shadow-lg!',
                        content: 'min-w-0 flex-1',
                        title: 'font-medium! leading-snug!',
                        description: 'text-background/70! leading-snug!',
                        actionButton:
                            'h-7! rounded-md! bg-background/10! px-2.5! text-background! transition-colors hover:bg-background/20! [[data-cancel]+&]:ml-0!',
                        cancelButton:
                            'h-7! rounded-md! bg-transparent! px-2.5! text-background/60! transition-colors hover:text-background!',
                        success:
                            '[&_[data-icon]]:text-emerald-400 dark:[&_[data-icon]]:text-emerald-600',
                        error: '[&_[data-icon]]:text-red-400 dark:[&_[data-icon]]:text-red-600',
                        warning:
                            '[&_[data-icon]]:text-amber-400 dark:[&_[data-icon]]:text-amber-600',
                        info: '[&_[data-icon]]:text-sky-400 dark:[&_[data-icon]]:text-sky-600',
                    },
                }}
                {...props}
            />
        </div>
    );
}
