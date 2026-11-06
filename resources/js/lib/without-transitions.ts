/**
 * Applies a change of the whole page at once, like its appearance or its color theme. The components with a transition
 * on their colors, the buttons and the links for instance, would otherwise fade from the old theme to the new one for
 * 150 ms, the page taking a moment to follow the choice.
 *
 * The transitions are suspended while the browser computes the new styles, then given back two frames later, once the
 * new theme is painted: removing them in the next frame would still start the fade, the styles of that frame not being
 * computed yet when its callbacks run.
 */
export function withoutTransitions(change: () => void): void {
    if (typeof document === 'undefined') {
        change();

        return;
    }

    const style = document.createElement('style');
    style.textContent = '*,*::before,*::after{transition:none!important}';
    document.head.appendChild(style);

    change();

    // Computes the new styles now, while the transitions are suspended.
    void window.getComputedStyle(document.body).backgroundColor;

    requestAnimationFrame(() => requestAnimationFrame(() => style.remove()));
}
