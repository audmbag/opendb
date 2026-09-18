import { usePage } from '@inertiajs/react';
import { Table2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import Input from '@/components/form/input';
import Switch from '@/components/form/switch';
import { Badge } from '@/components/reui/badge';
import { Frame, FramePanel } from '@/components/reui/frame';
import { H5, Muted } from '@/components/typograpfy/typography';
import { useTempProjectStore } from '@/store/use-project-store';
import type { Table } from '@/types/databases';
import type { Project } from '@/types/models/project';


const TableItem = ({ table }: { table: Table }) => {
    return (
        <div className="flex items-center flex-wrap justify-between border-b border-border p-2 last:border-b-0">
            <div className="flex gap-2 items-center">
                <H5 className="pb-1">{table.name}</H5>
                <Badge variant="warning-outline">
                    {`${table.columns} colonne${table.columns > 1 ? 's' : ''}`}
                </Badge>
            </div>
            <div className="flex items-center gap-2">
                <Muted>
                    Données et structure
                </Muted>
                <Switch
                    className="w-auto"
                    orientation="horizontal"
                    name={`table[${table.name}]`}
                    defaultChecked={table.enabled}
                />
            </div>
        </div>
    );
};

const TablesSelector = () => {

    const [search, setSearch] = useState('');

    const { props: { project } } = usePage<{project?: Project}>()

    const tmpProject = useTempProjectStore(d => d.tmpProject);

    const filteredTables = useMemo(() => {
        const tables =
            tmpProject?.driver_config?.tables_info ||
            project?.driver_config?.tables_info || [];

        return tables.filter((table) => table.name.toLowerCase().includes(search.toLowerCase()));
    }, [project?.driver_config?.tables_info, search, tmpProject?.driver_config?.tables_info]);

    return (
        <div className="flex flex-col gap-4">
            <div className="items-center justify-between flex flex-wrap gap-4">
                <Input
                    placeholder="Rechercher une table..."
                    className="w-full max-w-xs"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <Muted>
                    {filteredTables.length > 0 && `${filteredTables.length} table${filteredTables.length > 1 ? 's' : ''} trouvée${filteredTables.length > 1 ? 's' : ''}`}
                </Muted>
            </div>
            <Frame className="max-h-96 overflow-y-auto">
                <FramePanel className="overflow-visible p-0">
                    {filteredTables.map((table) => (
                        <TableItem key={table.name} table={table} />
                    ))}
                    {filteredTables.length === 0 && (
                        <EmptyState
                            title="Aucune table trouvée"
                            icon={Table2Icon}
                            description={
                                search && filteredTables.length === 0
                                    ? 'Aucune table ne correspond à votre recherche.'
                                    : "Aucune table n'est disponible dans la base de données."
                            }
                        />
                    )}
                </FramePanel>
            </Frame>
        </div>
    );
};

export default TablesSelector;
