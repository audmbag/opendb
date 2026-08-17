import {} from 'react';
import { Switch } from '@/components/ui/switch';
import type { AiModel } from '@/types/models/provider';

const ModelItem = ({
    providerId,
    model,
}: {
    providerId: string;
    model: AiModel;
}) => {
    return (
        <div
            className={
                'flex justify-between gap-2 border-b border-border p-3 px-0'
            }
        >
            <span className="text-base">{model.label}</span>
            <Switch id={`switch-${model.id}`} checked={model.active} />
        </div>
    );
};

export default ModelItem;
