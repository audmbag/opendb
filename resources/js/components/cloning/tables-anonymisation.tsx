import { useHttp } from '@inertiajs/react'
import {
    DatabaseBackupIcon,
    Loader,
    Loader2Icon,
    RefreshCwIcon,
    Table2Icon,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import CardFrame from '@/components/card-frame';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type { CloningClientPayload } from '@/components/cloning/cloning.types';
import TablesAnonymisationColumns from '@/components/cloning/tables-anonymisation-columns';
import Button from '@/components/form/button';
import {
    SmallSideBar,
    SmallSideBarContent,
    SmallSideBarProvider,
} from '@/components/sm-side-bar/small-side-bar';
import type { SmallSidebarNavItem } from '@/components/sm-side-bar/types';
import DriverConfigController from '@/wayfinder/actions/App/Http/Controllers/API/DriverConfigController';
import { useTempProjectStore } from '@/store/use-project-store';
import Alert from '@/components/alert';
import error from 'eslint-plugin-react/lib/util/error';
import { APIData } from '@/types/global';

const TablesAnonymisationContent = ({
    cloningPayload,
}: {
    cloningPayload: CloningClientPayload;
}) => {

    const tables: SmallSidebarNavItem[] = useMemo(() => {
        return cloningPayload.tables.map((table) => ({
            name: table.name,
            value: table.name,
            icon: Table2Icon,
            badge: table.columns.length.toString(),
            component: <TablesAnonymisationColumns table={table} />,
        }));
    }, [cloningPayload.tables]);

    const columnCount = useMemo(() => {
        return cloningPayload.tables.reduce((acc, table) => acc + table.columns.length, 0);
    }, [cloningPayload.tables]);

    const tableOrder = useCloningStore((s) => s.tableOrder);
    const keepAll = useCloningStore((s) => s.keepAll);
    const resetAll = useCloningStore((s) => s.resetAll);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-4">
                <span>
                    {tables.length} tables et {columnCount} colonnes détectées.
                </span>
                <div className="flex gap-2">
                    <Button
                        icon={DatabaseBackupIcon}
                        tooltip="Conserver les données existantes dans la base de données et ne pas les anonymiser."
                        variant="secondary"
                        onClick={keepAll}
                    >
                        Tout conserver
                    </Button>
                    <Button
                        icon={RefreshCwIcon}
                        tooltip="Réinitialiser les données à leur état d'origine."
                        variant="secondary"
                        onClick={resetAll}
                    />
                </div>
            </div>

            <CardFrame innerPadding={false}>
                {tables.length > 0 && tableOrder.length > 0 && (
                    <SmallSideBarProvider pages={tables}>
                        <div className="flex h-full w-full flex-col overflow-hidden p-0 md:flex-row">
                            <SmallSideBar
                                searchBar
                                className="max-h-96 bg-transparent px-1 py-1"
                            />
                            <SmallSideBarContent className="max-h-96 p-0" />
                        </div>
                    </SmallSideBarProvider>
                )}
            </CardFrame>
        </div>
    );
};

const TablesAnonymisation = () => {

    const { get, processing } = useHttp<any, { data: CloningClientPayload }>();

    const [cloningPayload, setCloningPayload] = useState<null | CloningClientPayload>(null);

    const loadPayload = useCloningStore((s) => s.loadPayload);
    const config = useTempProjectStore(state => state.cloningDriverConfig);

    useEffect(() => {

        if (!config?.from_id) {
            return;
        }

        get(
            DriverConfigController.cloningClientPayload({ config: config.from_id }).url,
            {
                onSuccess: ({ data }) => {
                    console.log(data);
                    loadPayload(data);
                    setCloningPayload(data);
                },
                onError: (e) => {
                    console.log(e);
                },
                onHttpException: (e) => {
                    console.log(e);
                },
            },
        );

    }, [config?.from_id, loadPayload])


    return (
        <div>
            {processing && (
                <div className="flex items-center justify-center p-4">
                    <Loader2Icon className="size-4 animate-spin" />
                </div>
            )}

            {cloningPayload && (
                <TablesAnonymisationContent cloningPayload={cloningPayload} />
            )}

            {/*<Alert*/}
            {/*    title={'Erreur'}*/}
            {/*    message={'Impossible de charger les données de clonage.'}*/}
            {/*    variant="destructive"*/}
            {/*/>*/}
        </div>
    );
};

export default TablesAnonymisation;
