const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Twice the size shown, like the images of the diagrams, so that the image stays sharp once zoomed or printed.
 */
const PIXEL_RATIO = 2;

const PADDING = 16;

/**
 * The CSS properties a chart takes from the stylesheets of the page, which an image does not load: written onto each
 * element of its copy. Reading only these, rather than every computed property, keeps the export instant.
 */
const INLINED_PROPERTIES = [
    'display',
    'visibility',
    'opacity',
    'fill',
    'fill-opacity',
    'stroke',
    'stroke-opacity',
    'stroke-width',
    'stroke-dasharray',
    'stroke-linecap',
    'stroke-linejoin',
    'font-family',
    'font-size',
    'font-weight',
    'font-style',
    'letter-spacing',
    'text-anchor',
    'dominant-baseline',
];

/**
 * What the pointer draws over the chart, left out of its image.
 */
const POINTER_ELEMENTS = '.recharts-tooltip-cursor, .recharts-active-dot';

/**
 * The fonts already turned into data URLs, by family: a second image of the session is made without any download.
 */
const embeddedFonts = new Map<string, Promise<string | null>>();

const isTransparent = (color: string): boolean =>
    color === 'transparent' || color === 'rgba(0, 0, 0, 0)';

/**
 * The background the chart is drawn on: that of the card holding it, or of the page.
 */
function backgroundOf(element: HTMLElement): string {
    for (
        let current: HTMLElement | null = element;
        current;
        current = current.parentElement
    ) {
        const color = getComputedStyle(current).backgroundColor;

        if (!isTransparent(color)) {
            return color;
        }
    }

    return getComputedStyle(document.body).backgroundColor;
}

const unquote = (family: string): string =>
    family.trim().replace(/^["']|["']$/g, '');

/**
 * The @font-face of the family covering the Latin letters, accents included: the files of a family are split by
 * alphabet, and the labels of a chart are written with these ones.
 */
function latinFontFace(family: string): CSSFontFaceRule | null {
    let fallback: CSSFontFaceRule | null = null;

    for (const sheet of Array.from(document.styleSheets)) {
        let rules: CSSRuleList;

        try {
            rules = sheet.cssRules;
        } catch {
            continue;
        }

        for (const rule of Array.from(rules)) {
            if (
                !(rule instanceof CSSFontFaceRule) ||
                unquote(rule.style.getPropertyValue('font-family')) !== family
            ) {
                continue;
            }

            const range = rule.style.getPropertyValue('unicode-range');

            if (range === '' || /U\+0+-0*FF\b/i.test(range)) {
                return rule;
            }

            fallback ??= /-latin-(?!ext)/.test(
                rule.style.getPropertyValue('src'),
            )
                ? rule
                : null;
        }
    }

    return fallback;
}

const readAsDataUrl = (blob: Blob): Promise<string> =>
    new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve(reader.result as string);
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(blob);
    });

/**
 * The @font-face of the family with its file inside, since an image loads nothing: without it, the labels would be
 * written with another font than in the page. Null when the family is one of the system.
 */
function embeddedFontFace(family: string): Promise<string | null> {
    const cached = embeddedFonts.get(family);

    if (cached) {
        return cached;
    }

    const loading = (async () => {
        const rule = latinFontFace(family);
        const source =
            rule &&
            /url\(["']?([^"')]+)["']?\)/.exec(
                rule.style.getPropertyValue('src'),
            )?.[1];

        if (!rule || !source) {
            return null;
        }

        const url = new URL(
            source,
            rule.parentStyleSheet?.href ?? location.href,
        );
        const response = await fetch(url);

        if (!response.ok) {
            return null;
        }

        const dataUrl = await readAsDataUrl(await response.blob());

        return `@font-face{font-family:"${family}";font-style:${rule.style.getPropertyValue('font-style') || 'normal'};font-weight:${rule.style.getPropertyValue('font-weight') || 'normal'};src:url(${dataUrl})}`;
    })().catch(() => null);

    embeddedFonts.set(family, loading);

    return loading;
}

/**
 * The legend of the chart, written in HTML by the page, drawn again in SVG where it shows: a swatch for each square
 * of color, a text for each label.
 */
function legendOf(container: HTMLElement, origin: DOMRect): SVGGElement | null {
    const legend = container.querySelector<HTMLElement>(
        '.recharts-legend-wrapper',
    );

    if (!legend) {
        return null;
    }

    const group = document.createElementNS(SVG_NS, 'g');

    for (const element of Array.from(
        legend.querySelectorAll<HTMLElement>('*'),
    )) {
        const style = getComputedStyle(element);

        if (
            element.childElementCount > 0 ||
            element.textContent?.trim() ||
            isTransparent(style.backgroundColor)
        ) {
            continue;
        }

        const bounds = element.getBoundingClientRect();
        const swatch = document.createElementNS(SVG_NS, 'rect');

        swatch.setAttribute('x', String(bounds.left - origin.left));
        swatch.setAttribute('y', String(bounds.top - origin.top));
        swatch.setAttribute('width', String(bounds.width));
        swatch.setAttribute('height', String(bounds.height));
        swatch.setAttribute(
            'rx',
            String(parseFloat(style.borderTopLeftRadius) || 0),
        );
        swatch.setAttribute('fill', style.backgroundColor);
        group.append(swatch);
    }

    const walker = document.createTreeWalker(legend, NodeFilter.SHOW_TEXT);
    const range = document.createRange();

    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        const content = node.textContent?.trim();

        if (!content || !node.parentElement) {
            continue;
        }

        range.selectNodeContents(node);

        const bounds = range.getBoundingClientRect();
        const style = getComputedStyle(node.parentElement);
        const text = document.createElementNS(SVG_NS, 'text');

        text.setAttribute('x', String(bounds.left - origin.left));
        text.setAttribute(
            'y',
            String(bounds.top - origin.top + bounds.height / 2),
        );
        text.setAttribute('dominant-baseline', 'central');
        text.setAttribute(
            'style',
            `fill:${style.color};font-family:${style.fontFamily};font-size:${style.fontSize};font-weight:${style.fontWeight}`,
        );
        text.textContent = content;
        group.append(text);
    }

    return group;
}

/**
 * The chart as a standalone SVG: its drawing with the styles of the page written onto each element, its legend, and
 * the background of its card around them.
 */
async function standaloneSvg(
    container: HTMLElement,
    surface: SVGSVGElement,
): Promise<{ markup: string; width: number; height: number }> {
    const origin = surface.getBoundingClientRect();
    const originals = [
        surface,
        ...Array.from(surface.querySelectorAll<SVGElement>('*')),
    ];

    // Every computed value is read before any element is written, which would make the browser compute them again.
    const styles = originals.map((element) => {
        const computed = getComputedStyle(element);

        return INLINED_PROPERTIES.map(
            (property) => `${property}:${computed.getPropertyValue(property)}`,
        ).join(';');
    });

    const copy = surface.cloneNode(true) as SVGSVGElement;

    [copy, ...Array.from(copy.querySelectorAll<SVGElement>('*'))].forEach(
        (element, index) => {
            element.setAttribute('style', styles[index]);
        },
    );
    copy.querySelectorAll(POINTER_ELEMENTS).forEach((element) =>
        element.remove(),
    );
    copy.setAttribute('x', String(PADDING));
    copy.setAttribute('y', String(PADDING));
    copy.setAttribute('width', String(origin.width));
    copy.setAttribute('height', String(origin.height));

    const width = Math.ceil(origin.width + PADDING * 2);
    const height = Math.ceil(origin.height + PADDING * 2);
    const image = document.createElementNS(SVG_NS, 'svg');

    image.setAttribute('xmlns', SVG_NS);
    image.setAttribute('width', String(width));
    image.setAttribute('height', String(height));
    image.setAttribute('viewBox', `0 0 ${width} ${height}`);

    const fontFace = await embeddedFontFace(
        unquote(getComputedStyle(surface).fontFamily.split(',')[0] ?? ''),
    );

    if (fontFace) {
        const style = document.createElementNS(SVG_NS, 'style');

        style.textContent = fontFace;
        image.append(style);
    }

    const background = document.createElementNS(SVG_NS, 'rect');

    background.setAttribute('width', '100%');
    background.setAttribute('height', '100%');
    background.setAttribute('fill', backgroundOf(container));
    image.append(background, copy);

    const legend = legendOf(container, origin);

    if (legend) {
        legend.setAttribute('transform', `translate(${PADDING} ${PADDING})`);
        image.append(legend);
    }

    return {
        markup: new XMLSerializer().serializeToString(image),
        width,
        height,
    };
}

/**
 * A PNG of the chart drawn in the container, as shown: drawn straight from its SVG, without copying the page around
 * it. Null when the container holds no chart.
 */
export async function chartImage(container: HTMLElement): Promise<Blob | null> {
    const surface = container.querySelector<SVGSVGElement>(
        'svg.recharts-surface',
    );

    if (!surface) {
        return null;
    }

    const { markup, width, height } = await standaloneSvg(container, surface);
    const sourceUrl = URL.createObjectURL(
        new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }),
    );

    try {
        const picture = new Image();

        picture.src = sourceUrl;
        await picture.decode();

        const canvas = document.createElement('canvas');

        canvas.width = width * PIXEL_RATIO;
        canvas.height = height * PIXEL_RATIO;

        const context = canvas.getContext('2d');

        if (!context) {
            return null;
        }

        context.scale(PIXEL_RATIO, PIXEL_RATIO);
        context.drawImage(picture, 0, 0, width, height);

        return await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/png'),
        );
    } finally {
        URL.revokeObjectURL(sourceUrl);
    }
}
