import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';

/**
 * The page of a project the user was on when they left it, to bring them back there when they open it again.
 * Kept by this browser only, like the tabs of the project.
 */
const lastPageKey = (projectId: string) => `opendb:last-page:${projectId}`;

const belongsToProject = (url: string, projectId: string): boolean =>
    url === `/${projectId}` ||
    url.startsWith(`/${projectId}/`) ||
    url.startsWith(`/${projectId}?`);

export function rememberLastProjectPage(projectId: string): void {
    const url = window.location.pathname + window.location.search;

    if (!belongsToProject(url, projectId)) {
        return;
    }

    try {
        localStorage.setItem(lastPageKey(projectId), url);
    } catch {
        // Without storage, the project opens on its chat.
    }
}

export function lastProjectPage(projectId: string): string | null {
    try {
        const url = localStorage.getItem(lastPageKey(projectId));

        return url && belongsToProject(url, projectId) ? url : null;
    } catch {
        return null;
    }
}

/**
 * The page the project opens on: the one the user left, or else its chat.
 */
export function projectEntryUrl(projectId: string): string {
    return lastProjectPage(projectId) ?? ConversationController.home.url(projectId);
}
