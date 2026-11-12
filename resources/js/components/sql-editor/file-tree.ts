import type { FileNode } from '@/hooks/use-file-tree';
import { isSameOrParentPath } from '@/hooks/use-file-tree';

/**
 * The id of the root of the tree, the .opendb/sql folder itself: never the path of a file or a folder.
 */
export const ROOT_ID = '/';

/**
 * A path of the editor in a URL: each name is encoded, the slashes between the folders are kept.
 */
export const encodePath = (path: string): string =>
    path.split('/').map(encodeURIComponent).join('/');

/**
 * The folder holding the path, null at the root.
 */
export const parentPath = (path: string): string | null => {
    const index = path.lastIndexOf('/');

    return index < 0 ? null : path.slice(0, index);
};

export const baseName = (path: string): string =>
    path.slice(path.lastIndexOf('/') + 1);

export const joinPath = (folder: string | null, name: string): string =>
    folder === null ? name : `${folder}/${name}`;

/**
 * The path of a file or a folder once the folder `from` is moved to `to`, unchanged outside of it.
 */
export const movedPath = (path: string, from: string, to: string): string =>
    isSameOrParentPath(from, path) ? `${to}${path.slice(from.length)}` : path;

/**
 * The paths of the files of the tree, in its order.
 */
export function filePaths(nodes: FileNode[], paths: string[] = []): string[] {
    for (const node of nodes) {
        if (node.type === 'file') {
            paths.push(node.id);
        } else if (node.children) {
            filePaths(node.children, paths);
        }
    }

    return paths;
}

export function folderIds(nodes: FileNode[], ids: string[] = []): string[] {
    for (const node of nodes) {
        if (node.type === 'folder') {
            ids.push(node.id);
            folderIds(node.children ?? [], ids);
        }
    }

    return ids;
}

/**
 * The files whose name holds the search, in the folders that lead to them.
 */
export function filterTree(nodes: FileNode[], search: string): FileNode[] {
    return nodes.flatMap((node): FileNode[] => {
        if (node.type === 'file') {
            return node.name.toLowerCase().includes(search) ? [node] : [];
        }

        const children = filterTree(node.children ?? [], search);

        return children.length > 0 ? [{ ...node, children }] : [];
    });
}

/**
 * The folders leading to the path, from the root.
 */
export function ancestors(path: string): string[] {
    const segments = path.split('/').slice(0, -1);

    return segments.map((_, index) => segments.slice(0, index + 1).join('/'));
}
