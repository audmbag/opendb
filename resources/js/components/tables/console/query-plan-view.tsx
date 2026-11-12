import { useLaravelReactI18n } from 'laravel-react-i18n';
import { XIcon } from 'lucide-react';
import { useState } from 'react';
import QueryPlanCard, {
    QueryPlanSummary,
} from '@/components/ai/artifacts/query-plan/query-plan-card';
import type { PlanVersion } from '@/components/ai/artifacts/query-plan/query-plan-card';
import Button from '@/components/form/button';
import type { QueryPlan } from '@/types/agent';

type Props = {
    plan: QueryPlan;
    onClose: () => void;
};

/**
 * The execution plan of a statement of the console, drawn as in the chat. Loaded apart from the console: the
 * diagram is heavy and only needed once a plan is asked for.
 */
export default function QueryPlanView({ plan, onClose }: Props) {
    const { t } = useLaravelReactI18n();
    const [version, setVersion] = useState<PlanVersion>('after');

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex flex-wrap items-center gap-2 px-3 py-1.5">
                <span className="text-xs font-medium">
                    {t('query_plan.title')} · {plan.driverLabel}
                </span>
                <div className="ml-auto flex items-center gap-2">
                    <QueryPlanSummary plan={plan} version={version} />
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={onClose}
                        aria-label={t('tables.plan.close')}
                        tooltip={t('tables.plan.back')}
                    >
                        <XIcon />
                    </Button>
                </div>
            </div>
            <div className="min-h-0 flex-1 overflow-auto border-t p-3">
                <QueryPlanCard
                    plan={plan}
                    version={version}
                    onVersionChange={setVersion}
                />
            </div>
        </div>
    );
}
