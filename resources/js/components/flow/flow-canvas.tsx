import {
    Background,
    BackgroundVariant,
    ControlButton,
    Controls,
    ReactFlow,
    useReactFlow,
} from '@xyflow/react';
import type { Edge, Node, ReactFlowProps } from '@xyflow/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import '@xyflow/react/dist/style.css';
import {
    ImageDownIcon,
    Loader2Icon,
    Maximize2Icon,
    Minimize2Icon,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useHotkeys } from 'react-hotkeys-hook';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { exportFlowImage } from './export-flow-image';

type Props<NodeType extends Node, EdgeType extends Edge> = Omit<
    ReactFlowProps<NodeType, EdgeType>,
    'children' | 'className' | 'ariaLabelConfig'
> & {
    /**
     * The accessible name of the canvas, also used for the full screen view.
     */
    label: string;
    /**
     * The name of the PNG file, without extension.
     */
    exportFileName: string;
    /**
     * False while the nodes are laid out: the canvas is hidden behind a skeleton and is not framed yet.
     */
    isReady?: boolean;
    /**
     * A panel shown beside the canvas, on the right or below it when there is no room, and in the full screen view.
     */
    aside?: ReactNode;
    /**
     * Frame the nodes in the view, instead of the default fitView. Called once the canvas is ready and whenever its size changes.
     */
    onFitView?: () => void;
    /**
     * Whether the wheel zooms outside the full screen view. Off by default, so that the wheel scrolls the conversation
     * holding the canvas; a page made of the canvas alone turns it on.
     */
    zoomOnScroll?: boolean;
    /**
     * Whether the nodes are framed again whenever the canvas is resized. A canvas the user moves around in turns it
     * off, so that opening a panel beside it keeps the view where it is.
     */
    fitOnResize?: boolean;
    className?: string;
    canvasClassName?: string;
    asideClassName?: string;
    /**
     * Extra elements rendered inside React Flow, such as a MiniMap or a Panel.
     */
    children?: ReactNode;
};

/**
 * The labels React Flow gives to its controls, in the language of the interface.
 */
const flowAriaLabels = (t: (key: string) => string) => ({
    'controls.ariaLabel': t('flow.controls'),
    'controls.zoomIn.ariaLabel': t('flow.zoom_in'),
    'controls.zoomOut.ariaLabel': t('flow.zoom_out'),
    'controls.fitView.ariaLabel': t('flow.fit_view'),
    'minimap.ariaLabel': t('flow.minimap'),
});

/**
 * Whether the viewer asked the system to limit animations: viewport moves are then instant.
 */
export function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

const iconClassName = 'max-h-3.5! max-w-3.5! fill-none!';

/**
 * The colors of React Flow taken from the theme. Its stylesheet is not in a cascade layer, so it wins over the Tailwind
 * utilities: only its variables can restyle the controls and the minimap.
 */
const themeStyle = {
    '--xy-background-color': 'var(--background)',
    '--xy-controls-button-background-color': 'var(--card)',
    '--xy-controls-button-background-color-hover': 'var(--muted)',
    '--xy-controls-button-color': 'var(--muted-foreground)',
    '--xy-controls-button-color-hover': 'var(--foreground)',
    '--xy-controls-button-border-color': 'var(--border)',
    '--xy-controls-box-shadow': 'none',
    '--xy-minimap-background-color': 'var(--card)',
    '--xy-minimap-mask-background-color':
        'color-mix(in oklab, var(--background) 65%, transparent)',
    '--xy-minimap-node-background-color': 'var(--muted)',
} as CSSProperties;

/**
 * The canvas shared by the diagrams of the artifacts: dotted background, controls (zoom, fit, PNG export, full screen),
 * skeleton while laying out, and an optional panel beside it.
 * The wheel scrolls the conversation and only zooms in full screen; pinching or Ctrl + wheel always zooms.
 * It must be rendered inside a ReactFlowProvider.
 */
export default function FlowCanvas<
    NodeType extends Node = Node,
    EdgeType extends Edge = Edge,
>({
    label,
    exportFileName,
    isReady = true,
    aside,
    onFitView,
    zoomOnScroll = false,
    fitOnResize = true,
    className,
    canvasClassName,
    asideClassName,
    children,
    ...flowProps
}: Props<NodeType, EdgeType>) {
    const { t } = useLaravelReactI18n();
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [isExporting, setIsExporting] = useState(false);
    const canvasRef = useRef<HTMLDivElement>(null);
    const fullscreenRef = useRef<HTMLDivElement>(null);
    const wasFullscreen = useRef(false);
    const { fitView, getNodes } = useReactFlow<NodeType, EdgeType>();

    const fit = useCallback(() => {
        if (onFitView) {
            onFitView();

            return;
        }

        fitView({ padding: 0.1 });
    }, [fitView, onFitView]);

    useHotkeys('escape', () => setIsFullscreen(false), {
        enabled: isFullscreen,
    });

    useEffect(() => {
        if (!isFullscreen) {
            return;
        }

        const previousOverflow = document.body.style.overflow;

        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [isFullscreen]);

    /**
     * Move the focus into the full screen view, then back to its button when leaving it.
     */
    useEffect(() => {
        if (isFullscreen) {
            fullscreenRef.current?.focus();
        } else if (wasFullscreen.current) {
            canvasRef.current
                ?.querySelector<HTMLElement>('[data-flow-fullscreen-toggle]')
                ?.focus();
        }

        wasFullscreen.current = isFullscreen;
    }, [isFullscreen]);

    /**
     * Frame the nodes once they are laid out, and again whenever the canvas is resized (full screen, window).
     */
    useEffect(() => {
        const canvas = canvasRef.current;

        if (!canvas || !isReady) {
            return;
        }

        let frame = 0;
        let hasFitted = false;
        const observer = new ResizeObserver(() => {
            if (hasFitted && !fitOnResize) {
                return;
            }

            hasFitted = true;
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(fit);
        });

        observer.observe(canvas);

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
        };
    }, [fit, fitOnResize, isReady, isFullscreen]);

    const handleExport = useCallback(async () => {
        if (isExporting || !canvasRef.current) {
            return;
        }

        setIsExporting(true);

        try {
            await exportFlowImage(getNodes(), canvasRef.current, exportFileName);
        } catch (error) {
            console.error(error);
        } finally {
            setIsExporting(false);
        }
    }, [exportFileName, getNodes, isExporting]);

    const fullscreenLabel = isFullscreen
        ? t('flow.exit_fullscreen')
        : t('flow.enter_fullscreen');

    const body = (
        <>
            <div
                ref={canvasRef}
                aria-busy={!isReady}
                className={cn(
                    'relative min-h-0 min-w-0 bg-background',
                    isFullscreen
                        ? 'flex-1'
                        : cn('flex-[999_1_420px]', canvasClassName),
                )}
            >
                <ReactFlow<NodeType, EdgeType>
                    {...flowProps}
                    ariaLabelConfig={flowAriaLabels(t)}
                    zoomOnScroll={isFullscreen || zoomOnScroll}
                    preventScrolling={isFullscreen || zoomOnScroll}
                    style={themeStyle}
                    className={cn(!isReady && 'opacity-0')}
                >
                    <Background
                        variant={BackgroundVariant.Dots}
                        gap={16}
                        size={1}
                        color="var(--muted-foreground)"
                    />
                    <Controls
                        showInteractive={false}
                        position="bottom-left"
                        onFitView={fit}
                        className="overflow-hidden rounded-md border border-border shadow-sm dark:shadow-none [&>button:focus-visible]:ring-2 [&>button:focus-visible]:ring-ring [&>button:focus-visible]:outline-none"
                    >
                        <ControlButton
                            onClick={handleExport}
                            disabled={isExporting || !isReady}
                            title={t('flow.export_png')}
                            aria-label={t('flow.export_png')}
                        >
                            {isExporting ? (
                                <Loader2Icon
                                    className={cn(iconClassName, 'animate-spin')}
                                />
                            ) : (
                                <ImageDownIcon className={iconClassName} />
                            )}
                        </ControlButton>
                        <ControlButton
                            data-flow-fullscreen-toggle
                            onClick={() => setIsFullscreen((current) => !current)}
                            title={fullscreenLabel}
                            aria-label={fullscreenLabel}
                        >
                            {isFullscreen ? (
                                <Minimize2Icon className={iconClassName} />
                            ) : (
                                <Maximize2Icon className={iconClassName} />
                            )}
                        </ControlButton>
                    </Controls>
                    {children}
                </ReactFlow>

                {!isReady && (
                    <div className="absolute inset-0 p-4" aria-hidden="true">
                        <Skeleton className="size-full" />
                    </div>
                )}
            </div>

            {aside && (
                <div
                    className={cn(
                        'min-h-0 min-w-0 overflow-y-auto bg-card',
                        isFullscreen
                            ? 'max-h-[40dvh] md:max-h-none md:w-96 md:shrink-0'
                            : cn('flex-[1_1_280px]', asideClassName),
                    )}
                >
                    {aside}
                </div>
            )}
        </>
    );

    if (isFullscreen) {
        return createPortal(
            <div
                ref={fullscreenRef}
                role="dialog"
                aria-modal="true"
                aria-label={label}
                tabIndex={-1}
                className={cn(
                    'fixed inset-0 z-100 flex flex-col bg-background outline-none md:flex-row',
                    aside && 'gap-px bg-border',
                )}
            >
                {body}
            </div>,
            document.body,
        );
    }

    return (
        <div
            role="group"
            aria-label={label}
            className={cn('flex flex-wrap', aside && 'gap-px bg-border', className)}
        >
            {body}
        </div>
    );
}
