import { useHttp, usePage } from '@inertiajs/react';
import { LoaderIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import SchemaCanvas from '@/components/schema-viewer/schema-canvas';
import type { SchemaExport } from '@/components/schema-viewer/types';
import type { ShemaDiagramToolUIPart } from '@/types/agent';
import type { Project } from '@/types/models/project';
import DatabaseSchemaController from '@/wayfinder/actions/App/Http/Controllers/Chat/DatabaseSchemaController';


type Props = {
    schemaDiagram: ShemaDiagramToolUIPart;
};

export default function SchemaDiagramPreview({ schemaDiagram }: Props) {

    const { props: { project } } = usePage<{ project: Project; }>();

    const [data, setData] = useState<SchemaExport | null>(null);

    const { get, processing } = useHttp<null, { data: SchemaExport }>();

    useEffect(() => {
        const url = schemaDiagram.output.focusedTable
            ? DatabaseSchemaController.table.url(
                  {
                      project: project.id,
                      table: schemaDiagram.output.focusedTable,
                  },
                  {
                      query: {
                          withRelatedTables: schemaDiagram.output.withRelatedTables,
                      },
                  },
              )
            : DatabaseSchemaController.database.url(project.id);

        get(url, {
            onSuccess: (response) => setData(response.data),
        });
    }, [project.id, schemaDiagram.output.focusedTable, schemaDiagram.output.withRelatedTables]);

    return (
        <div className="rounded-lg border border-border">
            {processing && (
                <div className="flex justify-center p-2">
                    <LoaderIcon className="animate-spin size-4" />
                </div>
            )}

            {data && <div className="h-160">
                <SchemaCanvas schema={data} />
            </div>}
        </div>
    );
}
