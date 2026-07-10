import FileTreeController from '@/wayfinder/actions/App/Http/Controllers/FileTreeController';
import {
    asyncDataLoaderFeature,
    hotkeysCoreFeature,
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
import { use, useCallback, useEffect, useMemo, useRef, useState } from 'react';

const FAKE_NETWORK_DELAY = 300;

const wait = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

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


const useFileFetch = (onlyFolders?: boolean) => {
    const [rootFolder, setRootFolder] = useState<FileNode | null>(null);
    const hasFetchedRef = useRef(false);

    const { get, processing } = useHttp<null, FileNode>();
    const { get: getChild, processing: childProcessing } = useHttp<null, FileNode>();

    useEffect(() => {
        if (hasFetchedRef.current) {
            return;
        }
        hasFetchedRef.current = true;

        get(FileTreeController.url({query: { onlyFolders }}), {
            onSuccess: (response) => {
                setRootFolder(response);
            },
            onError: (error) => {
                console.log(error);
            },
        });
    }, []);

    const fetchFolder = useCallback(
        (path: string): Promise<FileNode> => {
            return new Promise((resolve, reject) => {
                getChild(FileTreeController.url({ query: { path, onlyFolders } }), {
                    onSuccess: (response) => resolve(response),
                    onError: (error) => reject(error),
                });
            });
        },
        [getChild],
    );

    return { rootFolder, isFetching: processing, fetchFolder, childProcessing };
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

const useFileTree = (
    initialRootFolder: FileNode,
    fetchFolder: (path: string) => Promise<FileNode>,
    indent: number = INDENT,
) => {
    const [rootFolder, setRootFolder] = useState<FileNode>(initialRootFolder);
    const [selectedItemId, setSelectedItemId] = useState<string>(rootFolder.id);

    const resolvedRef = useRef<Set<string>>(new Set([initialRootFolder.id]));

    const fileIndex = useMemo(() => buildIndex([rootFolder]), [rootFolder]);

    const selectedFolder = useMemo(
        () => (selectedItemId ? (fileIndex.get(selectedItemId) ?? null) : null),
        [selectedItemId],
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

            const fresh = await fetchFolder(itemId);

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
                if (!data || data.type !== 'folder') {
                    return;
                }
            }
            setSelectedItemId(nextId);
        },
        features: [
            asyncDataLoaderFeature,
            selectionFeature,
            hotkeysCoreFeature,
        ],
    });

    return {
        tree,
        indent,
        selectedItemId,
        setSelectedItemId,
        selectedFolder,
    };
};

export { type FileNode, useFileFetch, useFileTree, getFileIcon };
