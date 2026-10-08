import React, { memo } from 'react';
import type { CloningTable } from '@/components/cloning/cloning.types';
import TablesAnonymisationColumn from '@/components/cloning/tables-anonymisation-column';


const TablesAnonymisationColumns = memo(({ table }: { table: CloningTable }) => {
    return (
        <div>
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
