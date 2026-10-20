import React, { memo } from 'react';
import Alert from '@/components/alert';
import type { CloningTable } from '@/components/cloning/cloning.types';
import TablesAnonymisationColumn from '@/components/cloning/tables-anonymisation-column';


const TablesAnonymisationColumns = memo(({ table }: { table: CloningTable }) => {
    return (
        <div>
            {table.skipReason && (
                <Alert
                    variant="warning"
                    title={`La table « ${table.name} » sera ignorée lors du clonage`}
                    message={table.skipReason}
                    className="m-3 w-auto"
                />
            )}
            {table.remappingNotice && (
                <Alert
                    variant="default"
                    title={`Les identifiants de « ${table.name} » ne seront pas remappés`}
                    message={table.remappingNotice}
                    className="m-3 w-auto"
                />
            )}
            {table.columns.map((column) => (
                <TablesAnonymisationColumn
                    key={`${table.name}-${column.name}`}
                    column={column.name}
                    table={table.name}
                />
            ))}
        </div>
    );
})


export default TablesAnonymisationColumns;
