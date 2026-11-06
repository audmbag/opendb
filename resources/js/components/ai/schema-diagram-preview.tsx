import { usePage } from '@inertiajs/react';
import { WorkflowIcon } from 'lucide-react';
import ArtifactFrame from '@/components/ai/artifacts/artifact-frame';
import { formatNumber } from '@/components/ai/artifacts/format';
import SchemaCanvas from '@/components/schema-viewer/schema-canvas';
import type { SchemaExport } from '@/components/schema-viewer/types';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import type { ShemaDiagramToolUIPart } from '@/types/agent';
import type { Project } from '@/types/models/project';
import DatabaseSchemaController from '@/wayfinder/actions/App/Http/Controllers/Chat/DatabaseSchemaController';

type Props = {
    part: ShemaDiagramToolUIPart;
};

export default function SchemaDiagramPreview({ part }: Props) {
    const { props: { project } } = usePage<{ project: Project }>();
    const { focusedTable, withRelatedTables } = part.output;

    const { ref, data, error, processing } = useToolArtifact<SchemaExport>(
        focusedTable
            ? DatabaseSchemaController.table.url(
                  { project: project.id, table: focusedTable },
                  { query: { withRelatedTables } },
              )
            : DatabaseSchemaController.database.url(project.id),
    );

    const tablesCount = data?.length ?? part.output.displayedTablesCount;

    return (
        <ArtifactFrame
            ref={ref}
            icon={WorkflowIcon}
            title={focusedTable ? `Schéma de la table ${focusedTable}` : 'Schéma de la base de données'}
            description={tablesCount ? `${formatNumber(tablesCount)} table${tablesCount > 1 ? 's' : ''}` : undefined}
            data={data}
            error={error}
            processing={processing}
            skeletonClassName="h-152"
        >
            <div className="h-160">
                <SchemaCanvas schema={data} />
            </div>
        </ArtifactFrame>
    );
}
