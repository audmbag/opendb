import { createCallable } from 'react-call'
import { Tree, TreeItem, TreeItemLabel } from '@/components/reui/tree';
import type { FileNode} from '@/hooks/use-file-tree';
import { getFileIcon, useFileFetch, useFileTree } from '@/hooks/use-file-tree';
import { cn, limit } from '@/lib/utils';
import Button from './form/button';
import Input from './form/input';
import Modal from './modal';
import { Spinner } from './ui/spinner';


interface SelectFolderModalProps {
    onlyFolders?: boolean;
    targetFileExtensions?: string[];
}

interface ModalContentProps extends SelectFolderModalProps {
    onCancel: () => void;
    onConfirm: (folder: FileNode) => void;
}

interface TreeContentProps extends ModalContentProps {
    rootFolder: FileNode;
    fetchFolder: (path: string) => Promise<FileNode>,
}



const SelectFileModal = createCallable<SelectFolderModalProps, FileNode | null>(({ onlyFolders, targetFileExtensions, call }) => {

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
                targetFileExtensions={targetFileExtensions}
                onCancel={() => call.end(null)}
                onConfirm={call.end}
            />
        </Modal>
    );
}, 1000);


const ModalContent = ({ onCancel, onConfirm, onlyFolders, targetFileExtensions }: ModalContentProps) => {

    const { rootFolder, isFetching, fetchFolder } = useFileFetch(onlyFolders, targetFileExtensions);

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
            onlyFolders={onlyFolders}
        />
    );
}


function TreeContent({ rootFolder, onCancel, onConfirm, fetchFolder, onlyFolders }: TreeContentProps) {
    'use no memo';

    const { tree, indent, selectedFolder, selectedItemId } = useFileTree(
        rootFolder,
        fetchFolder,
        onlyFolders,
    );

    return (
        <div
            className={cn('flex h-full flex-col gap-4 p-4')}
            {...tree.getContainerProps()}
        >
            <Input value={selectedItemId ?? ''} readOnly />
            <div className="flex-1 overflow-y-auto rounded-md bg-background p-2 pt-0">
                <Tree indent={indent} tree={tree}>
                    {tree.getItems().map((item) => (
                        <TreeItem key={item.getId()} item={item}>
                            <TreeItemLabel
                                className={cn(
                                    'relative before:absolute before:inset-x-0 before:-inset-y-0.5 before:-z-10 before:bg-background',
                                    item.isSelected() && 'bg-accent text-accent-foreground',
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
    );
}

export default SelectFileModal;
