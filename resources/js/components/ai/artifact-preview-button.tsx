import ChartPreview from '@/components/ai/artifacts/chart-preview';
import ExplainPreview from '@/components/ai/artifacts/explain-preview';
import ExportPreview from '@/components/ai/artifacts/export-preview';
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
    ExportDatabaseToolUIPart,
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

export function ArtifactPreview({ artifactable, isMessageSaved }: { artifactable: CustomOutputToolUIPart; isMessageSaved: boolean }) {
    switch (artifactable.type) {
        case AgentTools.ShowSchemaDiagram:
            return <SchemaDiagramPreview part={artifactable as ShemaDiagramToolUIPart} />;
        case AgentTools.ShowQueryResults:
            return <QueryResultsPreview part={artifactable as QueryResultsToolUIPart} enabled={isMessageSaved} />;
        case AgentTools.ShowKpis:
            return <KpisPreview part={artifactable as KpisToolUIPart} enabled={isMessageSaved} />;
        case AgentTools.ShowChart:
            return <ChartPreview part={artifactable as ChartToolUIPart} enabled={isMessageSaved} />;
        case AgentTools.ProfileTable:
            return <TableProfilePreview part={artifactable as ProfileTableToolUIPart} enabled={isMessageSaved} />;
        case AgentTools.ShowRecord:
            return <RecordPreview part={artifactable as RecordToolUIPart} enabled={isMessageSaved} />;
        case AgentTools.ExplainQuery:
            return <ExplainPreview part={artifactable as ExplainQueryToolUIPart} enabled={isMessageSaved} />;
        case AgentTools.ExportDatabase:
            return <ExportPreview part={artifactable as ExportDatabaseToolUIPart} enabled={isMessageSaved} />;
        default:
            return null;
    }
}

export default function ArtifactPreviewButton({ artifactables, isMessageSaved }: Props) {

    return (
        <div className="flex flex-col gap-2">
            {artifactables.map((artifactable) => (
                <ArtifactPreview key={artifactable.toolCallId} artifactable={artifactable} isMessageSaved={isMessageSaved} />
            ))}
        </div>
    );
}
