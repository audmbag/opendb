import { getNodesBounds, getViewportForBounds } from '@xyflow/react';
import type { Node } from '@xyflow/react';
import { toBlob } from 'html-to-image';

const inlinedSvgProperties = [
    'display',
    'fill',
    'opacity',
    'stroke',
    'stroke-dasharray',
    'stroke-linecap',
    'stroke-linejoin',
    'stroke-width',
    'visibility',
];

/**
 * The CSS properties copied onto each cloned element. Without this list, html-to-image copies every computed property
 * (more than 700) of every element, which makes the export take several seconds; these are the ones a still image needs.
 */
const renderedStyleProperties = [
    'display',
    'position',
    'top',
    'right',
    'bottom',
    'left',
    'z-index',
    'box-sizing',
    'width',
    'height',
    'min-width',
    'min-height',
    'max-width',
    'max-height',
    'margin-top',
    'margin-right',
    'margin-bottom',
    'margin-left',
    'padding-top',
    'padding-right',
    'padding-bottom',
    'padding-left',
    'flex-direction',
    'flex-wrap',
    'flex-grow',
    'flex-shrink',
    'flex-basis',
    'order',
    'align-content',
    'align-items',
    'align-self',
    'justify-content',
    'justify-items',
    'justify-self',
    'row-gap',
    'column-gap',
    'grid-template-columns',
    'grid-template-rows',
    'grid-auto-flow',
    'grid-auto-columns',
    'grid-auto-rows',
    'grid-column-start',
    'grid-column-end',
    'grid-row-start',
    'grid-row-end',
    'overflow-x',
    'overflow-y',
    'overflow-clip-margin',
    'transform',
    'transform-origin',
    'translate',
    'rotate',
    'scale',
    'opacity',
    'visibility',
    'filter',
    'mix-blend-mode',
    'isolation',
    'clip-path',
    'mask-image',
    'background-color',
    'background-image',
    'background-size',
    'background-position',
    'background-repeat',
    'background-clip',
    'background-origin',
    'border-top-width',
    'border-right-width',
    'border-bottom-width',
    'border-left-width',
    'border-top-style',
    'border-right-style',
    'border-bottom-style',
    'border-left-style',
    'border-top-color',
    'border-right-color',
    'border-bottom-color',
    'border-left-color',
    'border-top-left-radius',
    'border-top-right-radius',
    'border-bottom-right-radius',
    'border-bottom-left-radius',
    'outline-width',
    'outline-style',
    'outline-color',
    'outline-offset',
    'box-shadow',
    'color',
    'font-family',
    'font-size',
    'font-weight',
    'font-style',
    'font-stretch',
    'font-variant-numeric',
    'font-feature-settings',
    'line-height',
    'letter-spacing',
    'word-spacing',
    'text-align',
    'text-transform',
    'text-indent',
    'text-overflow',
    'text-decoration-line',
    'text-decoration-style',
    'text-decoration-color',
    'text-decoration-thickness',
    'text-underline-offset',
    'text-shadow',
    'white-space-collapse',
    'text-wrap-mode',
    'word-break',
    'overflow-wrap',
    'vertical-align',
    'direction',
    '-webkit-line-clamp',
    '-webkit-box-orient',
    'list-style-type',
    'object-fit',
    'object-position',
    'fill',
    'fill-opacity',
    'stroke',
    'stroke-opacity',
    'stroke-width',
    'stroke-dasharray',
    'stroke-dashoffset',
    'stroke-linecap',
    'stroke-linejoin',
    'marker-start',
    'marker-mid',
    'marker-end',
    'shape-rendering',
];

/**
 * Resolve once the browser has painted, so that a pending state such as a spinner shows before the heavy work starts.
 */
function waitForPaint(): Promise<void> {
    return new Promise((resolve) => {
        requestAnimationFrame(() => setTimeout(resolve, 0));
    });
}

/**
 * Download every node of a React Flow canvas as a PNG, whatever part of it is currently in view.
 */
export async function exportFlowImage(
    nodes: Node[],
    container: HTMLElement,
    fileName: string,
): Promise<void> {
    const viewportElement = container.querySelector<HTMLElement>(
        '.react-flow__viewport',
    );

    if (!viewportElement || nodes.length === 0) {
        return;
    }

    await waitForPaint();

    const padding = 48;
    const bounds = getNodesBounds(nodes);
    const imageWidth = Math.ceil(bounds.width + padding * 2);
    const imageHeight = Math.ceil(bounds.height + padding * 2);
    const viewport = getViewportForBounds(
        bounds,
        imageWidth,
        imageHeight,
        0.1,
        2,
        0.2,
    );
    const backgroundToken = getComputedStyle(document.documentElement)
        .getPropertyValue('--background')
        .trim();
    const svgElements = Array.from(
        viewportElement.querySelectorAll<SVGElement>('svg *'),
    );
    const originalStyles = svgElements.map((element) =>
        element.getAttribute('style'),
    );

    // Every computed value is read before any is written: interleaving them would recompute the styles once per element.
    const computedValues = svgElements.map((element) => {
        const computedStyle = getComputedStyle(element);

        return inlinedSvgProperties.map((property) =>
            computedStyle.getPropertyValue(property),
        );
    });

    svgElements.forEach((element, index) => {
        inlinedSvgProperties.forEach((property, propertyIndex) => {
            element.style.setProperty(
                property,
                computedValues[index][propertyIndex],
            );
        });
    });

    try {
        const blob = await toBlob(viewportElement, {
            backgroundColor: backgroundToken || '#ffffff',
            width: imageWidth,
            height: imageHeight,
            pixelRatio: 2,
            skipFonts: true,
            includeStyleProperties: renderedStyleProperties,
            filter: (node) =>
                !(node as HTMLElement)?.classList?.contains?.(
                    'react-flow__minimap',
                ) &&
                !(node as HTMLElement)?.classList?.contains?.(
                    'react-flow__controls',
                ),
            style: {
                width: `${imageWidth}px`,
                height: `${imageHeight}px`,
                transform: `translate(${viewport.x}px, ${viewport.y}px) scale(${viewport.zoom})`,
            },
        });

        if (!blob) {
            return;
        }

        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.download = `${fileName}.png`;
        link.href = objectUrl;
        link.click();
        setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
    } finally {
        svgElements.forEach((element, index) => {
            const originalStyle = originalStyles[index];

            if (originalStyle === null) {
                element.removeAttribute('style');
            } else {
                element.setAttribute('style', originalStyle);
            }
        });
    }
}
