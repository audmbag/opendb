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

const NOT_SELECT_MESSAGE = 'Seules les requêtes SELECT peuvent être analysées.';

/**
 * The plan stored by an ExplainQuery call, loaded once the message is saved. It is never computed again.
 */
export default function ExplainPreview({ part, enabled }: Props) {
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
            ? [NOT_SELECT_MESSAGE, part.output.message].filter(Boolean).join(' ')
            : (part.output.message ?? "Impossible de calculer le plan d'exécution.")
        : error;
    const shown = data ? planVersion(data, version) : null;

    return (
        <ArtifactFrame
            ref={ref}
            icon={WorkflowIcon}
            title="Plan d'exécution"
            description={
                shown
                    ? `${shown.nodes.length} étape${shown.nodes.length > 1 ? 's' : ''}`
                    : 'Requête SELECT'
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
