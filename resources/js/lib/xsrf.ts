/**
 * The XSRF token Laravel leaves in its cookie, for the requests sent without the Inertia client.
 */
export const xsrfToken = (): string | null => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
};
