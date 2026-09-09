import { getNodesBounds, getViewportForBounds } from '@xyflow/react';
import type { Node } from '@xyflow/react';
import { toPng, toSvg } from 'html-to-image';
import type { SchemaExport, TableNodeData, TableNodeType } from './types';

export function exportSchemaImage(
    format: 'png' | 'svg',
    nodes: TableNodeType[],
    schema: SchemaExport | null,
): Promise<void> {
    const viewportElement = document.querySelector<HTMLElement>(
        '.react-flow__viewport',
    );

    if (!viewportElement || nodes.length === 0) {
        return Promise.resolve();
    }

    const padding = 48;
    const bounds = getNodesBounds(nodes as Node<TableNodeData>[]);
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
    const backgroundColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--background')
        .trim();
    const exportImage = format === 'png' ? toPng : toSvg;
    const svgElements = Array.from(
        viewportElement.querySelectorAll<SVGElement>('svg *'),
    );
    const originalStyles = svgElements.map((element) =>
        element.getAttribute('style'),
    );

    svgElements.forEach((element) => {
        const computedStyle = getComputedStyle(element);
        [
            'display',
            'fill',
            'opacity',
            'stroke',
            'stroke-dasharray',
            'stroke-linecap',
            'stroke-linejoin',
            'stroke-width',
            'visibility',
        ].forEach((property) => {
            element.style.setProperty(
                property,
                computedStyle.getPropertyValue(property),
            );
        });
    });

    return exportImage(viewportElement, {
        backgroundColor: backgroundColor || '#ffffff',
        width: imageWidth,
        height: imageHeight,
        pixelRatio: 2,
        skipFonts: true,
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
    })
        .then((dataUrl) => {
            const extension = format;
            const link = document.createElement('a');
            link.download = `schema-${schema?.connection ?? 'export'}.${extension}`;
            link.href = dataUrl;
            link.click();
        })
        .finally(() => {
            svgElements.forEach((element, index) => {
                const originalStyle = originalStyles[index];

                if (originalStyle === null) {
                    element.removeAttribute('style');
                } else {
                    element.setAttribute('style', originalStyle);
                }
            });
        });
}
