import { useLaravelReactI18n } from 'laravel-react-i18n';
import { WorkflowIcon } from 'lucide-react';
import { useState } from 'react';
import ArtifactFrame from '@/components/ai/artifacts/artifact-frame';
import { useConversationId } from '@/components/ai/artifacts/format';
import { planVersion } from '@/components/ai/artifacts/query-plan/format';
import QueryPlanCard, {
    QueryPlanSummary,
} from '@/components/ai/artifacts/query-plan/query-plan-card';
import type { PlanVersion } from '@/components/ai/artifacts/query-plan/query-plan-card';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import type { ExplainQueryToolUIPart, QueryPlan } from '@/types/agent';
import { explain } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: ExplainQueryToolUIPart;
    enabled: boolean;
};

/**
 * The plan stored by an ExplainQuery call, loaded once the message is saved. It is never computed again.
 */
export default function ExplainPreview({ part, enabled }: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const conversationId = useConversationId();
    const [version, setVersion] = useState<PlanVersion>('after');
    const failed = !part.output.success;

    const { ref, data, error, processing } = useToolArtifact<QueryPlan>(
        explain.url({
            conversation: conversationId,
            toolCallId: part.toolCallId,
        }),
        enabled && !failed,
    );

    const failure = failed
        ? part.output.error === 'not_select'
            ? [t('query_plan.not_select'), part.output.message].filter(Boolean).join(' ')
            : (part.output.message ?? t('query_plan.failed'))
        : error;
    const shown = data ? planVersion(data, version) : null;

    return (
        <ArtifactFrame
            ref={ref}
            icon={WorkflowIcon}
            title={t('query_plan.title')}
            description={
                shown
                    ? tChoice('query_plan.steps', shown.nodes.length)
                    : t('query_plan.select_query')
            }
            badge={
                shown && (
                    <span className="inline-flex h-5 shrink-0 items-center rounded-full border px-1.75 text-[11px] font-medium">
                        {shown.driverLabel}
                    </span>
                )
            }
            actions={data && <QueryPlanSummary plan={data} version={version} />}
            data={data}
            error={failure}
            processing={processing && !failed}
            skeletonClassName="h-160"
        >
            {data && (
                <QueryPlanCard
                    plan={data}
                    version={version}
                    onVersionChange={setVersion}
                />
            )}
        </ArtifactFrame>
    );
}
