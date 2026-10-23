import ChartPreview from '@/components/ai/artifacts/chart-preview';
import ExplainPreview from '@/components/ai/artifacts/explain-preview';
import KpisPreview from '@/components/ai/artifacts/kpis-preview';
import QueryResultsPreview from '@/components/ai/artifacts/query-results-preview';
import RecordPreview from '@/components/ai/artifacts/record-preview';
import TableProfilePreview from '@/components/ai/artifacts/table-profile-preview';
import SchemaDiagramPreview from '@/components/ai/schema-diagram-preview';
import { AgentTools } from '@/types/agent';
import type {
    ChartToolUIPart,
    CustomOutputToolUIPart,
    ExplainQueryToolUIPart,
    KpisToolUIPart,
    ProfileTableToolUIPart,
    QueryResultsToolUIPart,
    RecordToolUIPart,
    ShemaDiagramToolUIPart,
} from '@/types/agent';

type Props = {
    artifactables: CustomOutputToolUIPart[];
    /**
     * Whether the message is saved, so the artifacts can load their data from the tool calls it holds.
     */
    isMessageSaved: boolean;
};

export default function ArtifactPreviewButton({ artifactables, isMessageSaved }: Props) {

    return (
        <div className="flex flex-col gap-2">
            {artifactables.map((artifactable) => {
                switch (artifactable.type) {
                    case AgentTools.ShowSchemaDiagram:
                        return <SchemaDiagramPreview key={artifactable.toolCallId} schemaDiagram={artifactable as ShemaDiagramToolUIPart} />;
                    case AgentTools.ShowQueryResults:
                        return <QueryResultsPreview key={artifactable.toolCallId} part={artifactable as QueryResultsToolUIPart} enabled={isMessageSaved} />;
                    case AgentTools.ShowKpis:
                        return <KpisPreview key={artifactable.toolCallId} part={artifactable as KpisToolUIPart} enabled={isMessageSaved} />;
                    case AgentTools.ShowChart:
                        return <ChartPreview key={artifactable.toolCallId} part={artifactable as ChartToolUIPart} enabled={isMessageSaved} />;
                    case AgentTools.ProfileTable:
                        return <TableProfilePreview key={artifactable.toolCallId} part={artifactable as ProfileTableToolUIPart} enabled={isMessageSaved} />;
                    case AgentTools.ShowRecord:
                        return <RecordPreview key={artifactable.toolCallId} part={artifactable as RecordToolUIPart} enabled={isMessageSaved} />;
                    case AgentTools.ExplainQuery:
                        return <ExplainPreview key={artifactable.toolCallId} part={artifactable as ExplainQueryToolUIPart} enabled={isMessageSaved} />;
                }
            })}
        </div>
    );
}
