import React, { useMemo } from 'react';
import type { AvailableStrategy, CloningColumn } from '@/components/cloning/cloning';
import { FAKE_TABLES_COLUMNS } from '@/components/cloning/fake-tables-columns';
import type { SelectItemInterface } from '@/components/form/select';
import Select from '@/components/form/select';
import { Badge } from '@/components/reui/badge';
import {
    useSmallSideBar,
} from '@/components/sm-side-bar/small-side-bar';
import Tooltip from '@/components/tooltip';
import { Muted } from '@/components/typograpfy/typography';

const anonymisationStrategies: SelectItemInterface<AvailableStrategy>[] = [
    {
        value: 'keep',
        label: 'Conserver',
    },
    {
        value: 'fake',
        label: 'Générer (Faker)',
    },
    {
        value: 'hash',
        label: 'Hacher',
    },
    {
        value: 'mask',
        label: 'Masquer',
    },
    {
        value: 'null',
        label: 'Vider (NULL)',
    },
    {
        value: 'static',
        label: 'Valeur statique',
    },
    {
        value: 'remapping',
        label: 'Remapper',
    },
];


const TablesAnonymisationColumn = ({ column } : { column: CloningColumn }) => {

    return (
        <div
            className="flex flex-wrap items-center justify-between gap-4 border-b border-border p-2 last:border-b-0"
        >
            <div className="flex flex-col gap-1">
                <span className="font-medium">{column.name}</span>
                <Muted className="text-xs uppercase">
                    {column.type}
                </Muted>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {column.detectedMatcher && (
                    <Tooltip
                        value={`Groupe "${column.detectedMatcher.groupLabel}" détecté`}
                        variant="warning"
                    >
                        <Badge variant="warning-light">
                            {column.detectedMatcher.name} détecté
                        </Badge>
                    </Tooltip>
                )}
                <Select
                    defaultValue={column.strategy}
                    position="item-aligned"
                    items={anonymisationStrategies}
                />
            </div>
        </div>
    )
}

const TablesAnonymisationColumns = () => {

    const { currentPageData } = useSmallSideBar()

    const table = useMemo(() => {
        return FAKE_TABLES_COLUMNS.find((t) => t.name === currentPageData?.value)
    }, [currentPageData])

    if(!table) {
        return null;
    }

    return (
        <div>
            {table.columns.map((column) => (
                <TablesAnonymisationColumn
                    key={`${table.name}-${column.name}`}
                    column={column}
                />
            ))}
        </div>
    );
}


export default TablesAnonymisationColumns;
