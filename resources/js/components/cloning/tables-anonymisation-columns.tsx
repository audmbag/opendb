import { useLaravelReactI18n } from 'laravel-react-i18n';
import React, { memo } from 'react';
import Alert from '@/components/alert';
import type { CloningTable } from '@/components/cloning/cloning.types';
import TablesAnonymisationColumn from '@/components/cloning/tables-anonymisation-column';


const TablesAnonymisationColumns = memo(({ table }: { table: CloningTable }) => {
    const { t } = useLaravelReactI18n();

    return (
        <div>
            {table.skipReason && (
                <Alert
                    variant="warning"
                    title={t('cloning.ui.table_skipped', { table: table.name })}
                    message={table.skipReason}
                    className="m-3 w-auto"
                />
            )}
            {table.remappingNotice && (
                <Alert
                    variant="default"
                    title={t('cloning.ui.table_not_remapped', { table: table.name })}
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
