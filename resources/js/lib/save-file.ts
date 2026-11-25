/**
 * Resolve once the browser has painted, so that a pending state such as a spinner shows before the heavy work starts.
 */
export function waitForPaint(): Promise<void> {
    return new Promise((resolve) => {
        requestAnimationFrame(() => setTimeout(resolve, 0));
    });
}

/**
 * Save a file made in the page, like an image of a diagram, under the given name.
 */
export function saveBlob(blob: Blob, fileName: string): void {
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.download = fileName;
    link.href = objectUrl;
    link.click();
    setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
}

/**
 * A name of file made from a title: without accents, spaces or characters a file system may refuse.
 */
export function fileNameOf(title: string, fallback: string): string {
    const name = title
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80)
        .replace(/-+$/, '');

    return name || fallback;
}
