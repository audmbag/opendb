import type { ItemInstance } from '@headless-tree/core';
import { AlertTriangleIcon, FileIcon, FolderOpenIcon, HomeIcon, SearchIcon, SearchXIcon } from 'lucide-react';
import type { CSSProperties } from 'react';
import { Fragment, useEffect } from 'react';
import { createCallable } from 'react-call'
import { EmptyState } from '@/components/empty-state';
import IconStackGlow from '@/components/icon-stack-glow';
import { Tree, TreeItem, TreeItemLabel } from '@/components/reui/tree';
import { Badge } from '@/components/ui/badge';
import { Kbd } from '@/components/ui/kbd';
import { Skeleton } from '@/components/ui/skeleton';
import type { FileNode} from '@/hooks/use-file-tree';
import { getDisplayPath, getFileIcon, useFileFetch, useFileTree } from '@/hooks/use-file-tree';
import { cn } from '@/lib/utils';
import Button from '../form/button';
import Modal from './modal';


interface SelectFolderModalProps {
    onlyFolders?: boolean;
    targetFileExtensions?: string[];
    initialPath?: string;
}

interface ModalContentProps extends SelectFolderModalProps {
    onCancel: () => void;
    onConfirm: (folder: FileNode) => void;
}

interface TreeContentProps extends ModalContentProps {
    rootFolder: FileNode;
    fetchFolder: (path: string) => Promise<FileNode>,
}



const SelectFileModal = createCallable<SelectFolderModalProps, FileNode | null>(({ onlyFolders, targetFileExtensions, initialPath, call }) => {

    return (
        <Modal
            open={!call.ended}
            showHeader
            onOpenChange={() => call.end(null)}
            onEscapeKeyDown={(event) => {
                if (event.target instanceof HTMLElement && event.target.closest('[data-slot=tree-search]')) {
                    event.preventDefault();
                }
            }}
            icon={onlyFolders ? FolderOpenIcon : FileIcon}
            title={
                onlyFolders
                    ? 'Sélectionner un dossier'
                    : 'Sélectionner un fichier'
            }
            description={
                onlyFolders
                    ? 'Parcourez votre dossier personnel'
                    : targetFileExtensions?.length
                      ? `Fichiers acceptés : ${targetFileExtensions.map((extension) => `.${extension}`).join(', ')}`
                      : 'Parcourez votre dossier personnel'
            }
            className="h-140 sm:max-w-130"
        >
            <ModalContent
                onlyFolders={onlyFolders}
                targetFileExtensions={targetFileExtensions}
                initialPath={initialPath}
                onCancel={() => call.end(null)}
                onConfirm={call.end}
            />
        </Modal>
    );
}, 1000);


const ModalContent = ({ onCancel, onConfirm, onlyFolders, targetFileExtensions, initialPath }: ModalContentProps) => {

    const { rootFolder, isFetching, hasError, retry, fetchFolder } = useFileFetch(onlyFolders, targetFileExtensions, initialPath);

    if (hasError) {
        return (
            <EmptyState
                icon={AlertTriangleIcon}
                title="Impossible de parcourir les fichiers"
                description="Le dossier n'a pas pu être lu. Vérifiez qu'il existe et que vous y avez accès."
                actions={[
                    { label: 'Annuler', variant: 'outline', onClick: onCancel },
                    { label: 'Réessayer', onClick: retry },
                ]}
            />
        );
    }

    if (isFetching || !rootFolder) {
        return <TreeSkeleton />;
    }

    return (
        <TreeContent
            rootFolder={rootFolder}
            onCancel={onCancel}
            onConfirm={onConfirm}
            fetchFolder={fetchFolder}
            onlyFolders={onlyFolders}
            targetFileExtensions={targetFileExtensions}
            initialPath={initialPath}
        />
    );
}


function TreeSkeleton() {
    return (
        <div className="flex h-full flex-col gap-4 p-4">
            <Skeleton className="h-14 w-full" />
            <div className="flex flex-1 flex-col gap-2 rounded-md bg-background p-3">
                {[60, 45, 70, 35, 55, 40].map((width, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <Skeleton className="size-4" />
                        <Skeleton className="h-4" style={{ width: `${width}%` }} />
                    </div>
                ))}
            </div>
            <div className="flex justify-end gap-2">
                <Skeleton className="h-8 w-20" />
                <Skeleton className="h-8 w-40" />
            </div>
        </div>
    );
}


function TreeContent({ rootFolder, onCancel, onConfirm, fetchFolder, onlyFolders, targetFileExtensions, initialPath }: TreeContentProps) {
    'use no memo';

    const { tree, indent, selectedFolder, failedItemIds, isSelectable } = useFileTree(
        rootFolder,
        fetchFolder,
        { onlyFolders, initialPath, onConfirm },
    );

    const items = tree.getItems();
    const searchValue = tree.getSearchValue();

    useEffect(() => {
        if (searchValue) {
            tree.getFocusedItem().scrollTo({ block: 'nearest' }).then(() => null);
        }
    }, [searchValue, tree]);

    const emptyLabel = onlyFolders ? 'Aucun dossier' : 'Aucun fichier compatible';

    const getEmptyFolderLabel = (item: ItemInstance<FileNode>): string | null => {
        if (!item.isFolder() || !item.isExpanded() || item.isLoading()) {
            return null;
        }

        if (failedItemIds.has(item.getId())) {
            return "Impossible d'ouvrir ce dossier";
        }

        return item.getChildren().length === 0 ? emptyLabel : null;
    };

    return (
        <div className="flex h-full min-h-0 flex-col gap-2 p-4">
            <SelectionSummary
                rootFolder={rootFolder}
                selected={selectedFolder}
                onlyFolders={onlyFolders}
            />

            <div className="relative min-h-0 flex-1 overflow-y-auto overflow-x-hidden rounded-md bg-background p-2">
                {tree.isSearchOpen() && (
                    <div data-slot="tree-search" className="sticky top-0 z-30 mb-2 flex items-center gap-2 rounded-md border bg-popover px-2 py-1 shadow-sm">
                        <SearchIcon className="size-4 shrink-0 text-muted-foreground" />
                        <input
                            {...tree.getSearchInputElementProps()}
                            autoFocus
                            placeholder="Rechercher..."
                            className="w-full bg-transparent text-sm outline-none"
                        />
                        <span className="shrink-0 text-xs text-muted-foreground">
                            {tree.getSearchMatchingItems().length} résultat(s)
                        </span>
                    </div>
                )}

                {items.length === 0 ? (
                    <EmptyState
                        className="h-full p-4"
                        icon={SearchXIcon}
                        title={emptyLabel}
                        description={
                            targetFileExtensions?.length
                                ? `Aucun fichier ${targetFileExtensions.map((extension) => `.${extension}`).join(', ')} dans votre dossier personnel.`
                                : 'Votre dossier personnel est vide.'
                        }
                    />
                ) : (
                    <Tree indent={indent} tree={tree} className="w-full">
                        {items.map((item) => {
                            const data = item.getItemData();
                            const emptyFolderLabel = getEmptyFolderLabel(item);

                            return (
                                <Fragment key={item.getId()}>
                                    <TreeItem
                                        item={item}
                                        className="w-full text-left"
                                        title={data.id}
                                        onDoubleClick={() => isSelectable(data) && onConfirm(data)}
                                    >
                                        <TreeItemLabel
                                            className={cn(
                                                'min-w-0 in-data-[search-match=true]:bg-primary/10! in-data-[selected=true]:font-medium',
                                                item.isLoading() && 'animate-pulse',
                                            )}
                                        >
                                            <span className="flex min-w-0 items-center gap-2">
                                                {getFileIcon(data, item.isExpanded())}
                                                <span className="truncate">
                                                    {data.name}
                                                </span>
                                            </span>
                                        </TreeItemLabel>
                                    </TreeItem>
                                    {emptyFolderLabel && (
                                        <div
                                            className="py-1 ps-(--tree-padding) text-xs text-muted-foreground italic"
                                            style={{ '--tree-padding': `${(item.getItemMeta().level + 1) * indent + 28}px` } as CSSProperties}
                                        >
                                            {emptyFolderLabel}
                                        </div>
                                    )}
                                </Fragment>
                            );
                        })}
                    </Tree>
                )}
            </div>

            <div className="flex items-center justify-between gap-2">
                <p className="hidden items-center gap-1.5 text-xs whitespace-nowrap text-muted-foreground sm:flex">
                    <Kbd>↵</Kbd> Valider
                    <Kbd className="ml-2">Aa</Kbd> Rechercher
                </p>
                <div className="ml-auto flex gap-2">
                    <Button variant="outline" onClick={onCancel}>
                        Annuler
                    </Button>
                    <Button
                        disabled={!selectedFolder}
                        onClick={() => selectedFolder && onConfirm(selectedFolder)}
                    >
                        Sélectionner le {onlyFolders ? 'dossier' : 'fichier'}
                    </Button>
                </div>
            </div>
        </div>
    );
}


interface SelectionSummaryProps {
    rootFolder: FileNode;
    selected: FileNode | null;
    onlyFolders?: boolean;
}

function SelectionSummary({ rootFolder, selected, onlyFolders }: SelectionSummaryProps) {
    const isRoot = selected?.id === rootFolder.id;

    return (
        <div className="rounded-md border bg-background">
            <div className="flex min-h-14 items-center gap-3 rounded-md px-2">
                <IconStackGlow
                    icon={
                        isRoot ? (
                            <HomeIcon className="size-4 text-muted-foreground" />
                        ) : selected ? (
                            getFileIcon(selected, false)
                        ) : (
                            getFileIcon(
                                {
                                    type: onlyFolders ? 'folder' : 'file',
                                    name: '',
                                },
                                false,
                            )
                        )
                    }
                />
                {selected ? (
                    <div className="flex min-w-0 flex-1 flex-col">
                        <span className="flex items-center gap-2 truncate text-sm font-medium">
                            <span className="truncate">{selected.name}</span>
                            {isRoot && (
                                <Badge variant="secondary">
                                    Dossier personnel
                                </Badge>
                            )}
                        </span>
                        <span
                            className="truncate text-left text-xs text-muted-foreground [direction:rtl]"
                            title={selected.id}
                        >
                            <bdi>
                                {getDisplayPath(rootFolder.id, selected.id)}
                            </bdi>
                        </span>
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        {onlyFolders
                            ? 'Aucun dossier sélectionné'
                            : 'Aucun fichier sélectionné'}
                    </span>
                )}
            </div>
        </div>
    );
}

export default SelectFileModal;
