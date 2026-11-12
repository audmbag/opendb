import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { useProvider } from '@/hooks/use-provider';
import { numberFormat } from '@/lib/utils';
import type { AiModel } from '@/types/models/provider';

const ModelItem = ({
    providerId,
    model,
    onToggle,
}: {
    providerId: string;
    model: AiModel;
    onToggle: (status: boolean) => void;
}) => {
    const { t } = useLaravelReactI18n();
    const { toggleProvider, toggleModelProcessing } = useProvider();

    return (
        <div
            className={
                'flex justify-between gap-2 border-b border-border p-3 px-0'
            }
        >
            <div className="flex items-center gap-2">
                <span className="text-base">{model.label}</span>
                {model.context_window && (
                    <Badge variant="outline" className="text-xs">
                        {t('settings.models.context')}
                        <span className="font-semibold">
                            {numberFormat(model.context_window)}
                        </span>
                    </Badge>
                )}
            </div>
            <Switch
                id={`switch-${model.id}`}
                checked={model.active}
                onCheckedChange={() =>
                    toggleProvider(providerId, model.id, onToggle)
                }
                disabled={toggleModelProcessing}
            />
        </div>
    );
};

export default ModelItem;
