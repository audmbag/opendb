import {
    DatabaseBackupIcon,
    RefreshCwIcon,
    Table2Icon,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import CardFrame from '@/components/card-frame';
import { FAKE_TABLES_COLUMNS } from '@/components/cloning/fake-tables-columns';
import TablesAnonymisationColumns from '@/components/cloning/tables-anonymisation-columns';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import {
    SmallSideBar,
    SmallSideBarContent,
    SmallSideBarProvider,
} from '@/components/sm-side-bar/small-side-bar';
import type { SmallSidebarNavItem } from '@/components/sm-side-bar/types';


type Props = {
    ref?: React.ComponentRef<any>;
};


const TablesAnonymisation = () => {

    const [search, setSearch] = useState('');

    const tables: SmallSidebarNavItem[] = useMemo(() => {
        return FAKE_TABLES_COLUMNS.map((table) => ({
            name: table.name,
            value: table.name,
            icon: Table2Icon,
            badge: table.columns.length.toString(),
        })).filter(table => table.name.toLowerCase().includes(search.toLowerCase()));
    }, [search])

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <Input
                    placeholder="Rechercher une table..."
                    className="w-full max-w-xs"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <div className="flex items-center gap-2">
                    <Button
                        icon={DatabaseBackupIcon}
                        tooltip="Conserver les données existantes dans la base de données et ne pas les anonymiser."
                        variant="secondary"
                    >
                        Tout conserver
                    </Button>
                    <Button
                        icon={RefreshCwIcon}
                        tooltip="Réinitialiser les données à leur état d'origine."
                        variant="secondary"
                    />
                </div>
            </div>

            <CardFrame innerPadding={false}>
                {tables.length > 0 ? (
                    <SmallSideBarProvider pages={tables}>
                        <SmallSideBar className="max-h-96 bg-transparent px-1 py-1" />
                        <SmallSideBarContent className="max-h-96 p-0">
                            <TablesAnonymisationColumns />
                        </SmallSideBarContent>
                    </SmallSideBarProvider>
                ) : (
                    <EmptyState
                        title="Aucune table trouvée"
                        description={
                            search ? `Aucune table ne correspond à votre recherche "${search}".` : 'La base de données ne contient aucune table.'
                        }
                        icon={Table2Icon}
                    />
                )}
            </CardFrame>
        </div>
    );
};

export default TablesAnonymisation;
