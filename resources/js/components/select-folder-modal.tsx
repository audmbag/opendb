import { Tree, TreeItem, TreeItemLabel } from '@/components/reui/tree';
import Button from './form/button';
import Input from './form/input';
import { cn, limit } from '@/lib/utils';
import { FileNode, getFileIcon, useFileFetch, useFileTree } from '@/hooks/use-file-tree';
import { Spinner } from './ui/spinner';
import { createCallable } from 'react-call'
import Modal from './modal';


interface SelectFolderModalProps {
    onlyFolders?: boolean;
}

interface ModalContentProps {
    onlyFolders?: boolean;
    onCancel: () => void;
    onConfirm: (folder: FileNode) => void;
}

interface TreeContentProps extends ModalContentProps {
    rootFolder: FileNode;
    fetchFolder: (path: string) => Promise<FileNode>,
}

const SelectFolderModal = createCallable<SelectFolderModalProps, FileNode | null>(({ onlyFolders, call }) => {

    return (
        <Modal
            open={!call.ended}
            showHeader
            onOpenChange={() => call.end(null)}
            title="Créer un nouveau projet"
            description="Formulaire pour créer un nouveau projet"
            className="sm:max-w-120"
        >
            <ModalContent
                onlyFolders={onlyFolders}
                onCancel={() => call.end(null)}
                onConfirm={call.end}
            />
        </Modal>
    );
}, 1000);


const ModalContent = ({ onCancel, onConfirm, onlyFolders }: ModalContentProps) => {

    const { rootFolder, isFetching, fetchFolder } = useFileFetch(onlyFolders);

    if (isFetching || !rootFolder) {
        return (
            <div className="flex h-full w-full items-center justify-center">
                <Spinner />
            </div>
        );
    }

    return (
        <TreeContent
            rootFolder={rootFolder}
            onCancel={onCancel}
            onConfirm={onConfirm}
            fetchFolder={fetchFolder}
        />
    );
}


function TreeContent({ rootFolder, onCancel, onConfirm, fetchFolder }: TreeContentProps) {
    'use no memo';

    const { tree, indent, selectedFolder, selectedItemId } = useFileTree(rootFolder, fetchFolder);

    return (
        <div
            className={cn(
                "flex h-full flex-col gap-4 p-4",
            )}
            {...tree.getContainerProps()}
        >
            <Input value={selectedItemId ?? ''} readOnly />
            <div className="flex-1 overflow-y-auto rounded-md bg-background p-2">
                <Tree indent={indent} tree={tree}>
                    {tree.getItems().map((item) => (
                        <TreeItem key={item.getId()} item={item}>
                            <TreeItemLabel
                                className={cn(
                                    'relative before:absolute before:inset-x-0 before:-inset-y-0.5 before:-z-10 before:bg-background',
                                    item.isSelected() &&
                                        'bg-accent text-accent-foreground',
                                    !item.isFolder() &&
                                        'text-muted-foreground/70',
                                )}
                            >
                                <span className="flex items-center gap-2">
                                    {getFileIcon(
                                        item.getItemData(),
                                        item.isExpanded(),
                                    )}
                                    {limit(item.getItemData().name, 30)}
                                </span>
                            </TreeItemLabel>
                        </TreeItem>
                    ))}
                </Tree>
            </div>
            <div className="flex justify-end gap-2">
                <Button variant="outline" size="sm" onClick={onCancel}>
                    Annuler
                </Button>
                <Button
                    size="sm"
                    disabled={!selectedFolder}
                    onClick={() =>
                        selectedFolder && onConfirm(selectedFolder)
                    }
                >
                    Sélectionner le dossier
                </Button>
            </div>
        </div>
    );
}

export default SelectFolderModal;
