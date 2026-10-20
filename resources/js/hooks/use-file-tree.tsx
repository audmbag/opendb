import {
    asyncDataLoaderFeature,
    hotkeysCoreFeature,
    searchFeature,
    selectionFeature,
} from '@headless-tree/core';
import { useTree } from '@headless-tree/react';
import { useHttp } from '@inertiajs/react';
import {
    BracesIcon,
    FileCodeIcon,
    FileIcon,
    FileTextIcon,
    FolderIcon,
    FolderOpenIcon,
    PaletteIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import FileTreeController from '@/wayfinder/actions/App/Http/Controllers/FileTreeController';

const INDENT = 20;

interface FileNode {
    id: string;
    name: string;
    type: 'folder' | 'file';
    children?: FileNode[];
}

const getFileIcon = (
    node: Pick<FileNode, 'type' | 'name'>,
    isExpanded: boolean,
) => {
    if (node.type === 'folder') {
        return isExpanded ? (
            <FolderOpenIcon className="pointer-events-none size-4 text-amber-500" />
        ) : (
            <FolderIcon className="pointer-events-none size-4 text-amber-500" />
        );
    }

    const extension = node.name.split('.').pop()?.toLowerCase();

    switch (extension) {
        case 'tsx':
        case 'ts':
            return (
                <FileCodeIcon className="pointer-events-none size-4 text-blue-500" />
            );
        case 'css':
            return (
                <PaletteIcon className="pointer-events-none size-4 text-purple-500" />
            );
        case 'json':
            return (
                <BracesIcon className="pointer-events-none size-4 text-yellow-500" />
            );
        case 'md':
            return (
                <FileTextIcon className="pointer-events-none size-4 text-muted-foreground" />
            );
        default:
            return (
                <FileIcon className="pointer-events-none size-4 text-muted-foreground" />
            );
    }
};

function buildIndex(nodes: FileNode[], map = new Map<string, FileNode>()) {
    for (const node of nodes) {
        map.set(node.id, node);

        if (node.children?.length) {
            buildIndex(node.children, map);
        }
    }

    return map;
}

/**
 * Indique si `path` est égal à `parent` ou se trouve à l'intérieur.
 */
function isSameOrParentPath(parent: string, path: string): boolean {
    return (
        path === parent ||
        (path.startsWith(parent) && ['/', '\\'].includes(path[parent.length]))
    );
}

/**
 * Retourne les nœuds menant de la racine au nœud le plus proche de `targetId`.
 */
function findPathToNode(root: FileNode, targetId: string): FileNode[] {
    const path = [root];
    let current = root;

    while (current.id !== targetId) {
        const next = current.children?.find((child) =>
            isSameOrParentPath(child.id, targetId),
        );

        if (!next) {
            break;
        }

        path.push(next);
        current = next;
    }

    return path;
}

/**
 * Affiche un chemin relativement au dossier racine (ex: `~/projects/opendb`).
 */
function getDisplayPath(rootId: string, path: string): string {
    if (!isSameOrParentPath(rootId, path)) {
        return path;
    }

    return `~${path.slice(rootId.length)}`;
}

const isSelectableNode = (node: Pick<FileNode, 'type'>, onlyFolders?: boolean) =>
    onlyFolders ? node.type === 'folder' : node.type === 'file';

const useFileFetch = (onlyFolders?: boolean, targetFileExtensions?: string[], initialPath?: string) => {
    const [rootFolder, setRootFolder] = useState<FileNode | null>(null);
    const [hasError, setHasError] = useState(false);
    const hasFetchedRef = useRef(false);

    const { get, processing } = useHttp<null, FileNode>();
    const { get: getChild, processing: childProcessing } = useHttp<null, FileNode>();

    const extensions = targetFileExtensions?.join(',');

    const fetchRootFolder = useCallback(() => {
        setHasError(false);

        get(FileTreeController.url({ query: { onlyFolders, targetFileExtensions: extensions, reveal: initialPath || undefined } }), {
            onHttpException: () => false,
            onNetworkError: () => false,
        })
            .then((response) => {
                if (!response) {
                    setHasError(true);

                    return;
                }

                setRootFolder(response);
            })
            .catch(() => setHasError(true));
    }, [get, onlyFolders, extensions, initialPath]);

    useEffect(() => {
        if (hasFetchedRef.current) {
            return;
        }

        hasFetchedRef.current = true;

        fetchRootFolder();
    });

    const fetchFolder = useCallback(
        async (path: string): Promise<FileNode> => {
            const response = await getChild(FileTreeController.url({ query: { path, onlyFolders, targetFileExtensions: extensions } }), {
                onHttpException: () => false,
                onNetworkError: () => false,
            });

            if (!response) {
                throw new Error(`Impossible de lire le dossier ${path}`);
            }

            return response;
        },
        [getChild, onlyFolders, extensions],
    );

    return { rootFolder, isFetching: processing, hasError, retry: fetchRootFolder, fetchFolder, childProcessing };
};

function mergeNode(root: FileNode, updated: FileNode): FileNode {
    if (root.id === updated.id) {
        return { ...root, ...updated };
    }

    if (!root.children) {
        return root;
    }

    return {
        ...root,
        children: root.children.map((child) => mergeNode(child, updated)),
    };
}

interface UseFileTreeOptions {
    onlyFolders?: boolean;
    initialPath?: string;
    onConfirm?: (node: FileNode) => void;
    indent?: number;
}

const useFileTree = (
    initialRootFolder: FileNode,
    fetchFolder: (path: string) => Promise<FileNode>,
    { onlyFolders, initialPath, onConfirm, indent = INDENT }: UseFileTreeOptions = {},
) => {
    const [rootFolder, setRootFolder] = useState<FileNode>(initialRootFolder);
    const [failedItemIds, setFailedItemIds] = useState<Set<string>>(() => new Set());

    /**
     * Chemin vers la valeur initiale, préchargé par le serveur via le paramètre `reveal`.
     */
    const [initialBranch] = useState(() =>
        initialPath ? findPathToNode(initialRootFolder, initialPath) : [initialRootFolder],
    );

    const [selectedItemId, setSelectedItemId] = useState<string | null>(() => {
        const target = initialBranch[initialBranch.length - 1];

        if (initialPath && target.id === initialPath && isSelectableNode(target, onlyFolders)) {
            return target.id;
        }

        return onlyFolders ? initialRootFolder.id : null;
    });

    const resolvedRef = useRef<Set<string>>(
        new Set([
            ...(initialRootFolder.children ?? []).map((child) => child.id),
            ...initialBranch.filter((node) => node.type === 'folder').map((node) => node.id),
        ]),
    );

    const fileIndex = useMemo(() => buildIndex([rootFolder]), [rootFolder]);

    const selectedFolder = useMemo(
        () => (selectedItemId ? (fileIndex.get(selectedItemId) ?? null) : null),
        [fileIndex, selectedItemId],
    );

    const resolveChildren = useCallback(
        async (itemId: string): Promise<{ id: string; data: FileNode }[]> => {
            const current = fileIndex.get(itemId);

            if (resolvedRef.current.has(itemId)) {
                return (current?.children ?? []).map((child) => ({
                    id: child.id,
                    data: child,
                }));
            }

            let fresh: FileNode;

            try {
                fresh = await fetchFolder(itemId);
            } catch {
                setFailedItemIds((prev) => new Set(prev).add(itemId));

                return [];
            }

            resolvedRef.current.add(itemId);

            fresh.children?.forEach((child) =>
                resolvedRef.current.add(child.id),
            );

            setRootFolder((prev) => mergeNode(prev, fresh));

            return (fresh.children ?? []).map((child) => ({
                id: child.id,
                data: child,
            }));
        },
        [fileIndex, fetchFolder],
    );

    const tree = useTree<FileNode>({
        indent,
        rootItemId: rootFolder.id,
        getItemName: (item) => item.getItemData().name,
        isItemFolder: (item) => item.getItemData()?.type === 'folder',
        createLoadingItemData: () => ({
            id: 'loading',
            name: 'Chargement...',
            type: 'file',
        }),
        dataLoader: {
            getItem: (itemId) => {
                const item = fileIndex.get(itemId);

                if (!item) {
                    throw new Error(`Item ${itemId} introuvable`);
                }

                return item;
            },
            getChildrenWithData: (itemId) => resolveChildren(itemId),
        },
        initialState: {
            expandedItems: initialBranch
                .slice(1)
                .filter((node) => node.type === 'folder' && node.id !== selectedItemId)
                .map((node) => node.id),
            focusedItem: selectedItemId,
        },
        state: {
            selectedItems: selectedItemId ? [selectedItemId] : [],
        },
        setSelectedItems: (updater) => {
            const currentItems = selectedItemId ? [selectedItemId] : [];
            const items =
                typeof updater === 'function' ? updater(currentItems) : updater;

            const nextId = items[items.length - 1] ?? null;

            if (nextId) {
                const data = fileIndex.get(nextId);

                if (!data || !isSelectableNode(data, onlyFolders)) {
                    return;
                }
            }

            setSelectedItemId(nextId);
        },
        hotkeys: {
            customConfirmSelection: {
                hotkey: 'Enter',
                preventDefault: true,
                isEnabled: (instance) => {
                    const focused = instance.getFocusedItem()?.getItemData();

                    return !instance.isSearchOpen() && !!focused && isSelectableNode(focused, onlyFolders);
                },
                handler: (_, instance) => onConfirm?.(instance.getFocusedItem().getItemData()),
            },
        },
        features: [
            asyncDataLoaderFeature,
            selectionFeature,
            hotkeysCoreFeature,
            searchFeature,
        ],
    });

    const hasRevealedRef = useRef(!selectedItemId || selectedItemId === initialRootFolder.id);

    useEffect(() => {
        if (hasRevealedRef.current || !selectedItemId) {
            return;
        }

        const item = tree.getItems().find((candidate) => candidate.getId() === selectedItemId);

        if (!item) {
            return;
        }

        hasRevealedRef.current = true;
        item.scrollTo({ block: 'center' });
    });

    return {
        tree,
        indent,
        rootFolder,
        selectedItemId,
        setSelectedItemId,
        selectedFolder,
        failedItemIds,
        isSelectable: (node: Pick<FileNode, 'type'>) => isSelectableNode(node, onlyFolders),
    };
};

export { type FileNode, useFileFetch, useFileTree, getFileIcon, getDisplayPath };
