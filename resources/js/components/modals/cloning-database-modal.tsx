import { CopyIcon } from 'lucide-react';
import { useCallback } from 'react';
import CloningConnectionForm from '@/components/cloning/cloning-connection-form';
import { useCloningStore } from '@/components/cloning/cloning-store';
import Modal from '@/components/modals/modal';
import { useProjectModal } from '@/hooks/use-project-modal';
import { useTempProjectStore } from '@/store/use-project-store';

const CloningConnectionModal = () => {
    const { setCloningOpen, cloningOpen, setOpen } = useProjectModal();

    const setCloningDriverConfig = useTempProjectStore((state) => state.setCloningDriverConfig);
    const clear = useCloningStore((state) => state.clear);


    const onOpenChange = useCallback(
        (open: boolean) => {
            setCloningOpen(open);

            if (!open) {
                setOpen(true, 2);

                setCloningDriverConfig(null);
                clear();
            }
        },
        [setCloningOpen, setOpen],
    );

    return (
        <Modal
            showHeader
            scrollable
            className="h-auto" // sm:max-w-250
            open={cloningOpen}
            onOpenChange={onOpenChange}
            title="Cloner la connection"
            description="Formulaire pour cloner la base de donnés connection existante"
            icon={CopyIcon}
        >
            <CloningConnectionForm />
        </Modal>
    );
};



export default CloningConnectionModal;
