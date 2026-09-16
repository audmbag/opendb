import SchemaDiagramPreview from '@/components/ai/schema-diagram-preview';
import { AgentTools } from '@/types/agent';
import type {ShemaDiagramToolUIPart, CustomOutputToolUIPart } from '@/types/agent';

type Props = {
    artifactables: CustomOutputToolUIPart[];
};

export default function ArtifactPreviewButton({ artifactables }: Props) {

    return (
        <div className="flex flex-col gap-2">
            {artifactables.map((artifactable) => {
                switch (artifactable.type) {
                    case AgentTools.ShowSchemaDiagram:
                        return (
                            <SchemaDiagramPreview
                                key={artifactable.toolCallId}
                                schemaDiagram={
                                    artifactable as ShemaDiagramToolUIPart
                                }
                            />
                        );
                }
            })}
        </div>
    );
}
