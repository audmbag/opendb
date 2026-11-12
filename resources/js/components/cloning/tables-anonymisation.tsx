import { useHttp } from '@inertiajs/react'
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CircleSlashIcon,
    DatabaseBackupIcon,
    Loader2Icon,
    RefreshCwIcon,
    Table2Icon,
} from 'lucide-react';
import React, { useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';
import Alert from '@/components/alert';
import CardFrame from '@/components/card-frame';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type {
    CloningClientPayload,
    CloningDraft,
} from '@/components/cloning/cloning.types';
import TablesAnonymisationColumns from '@/components/cloning/tables-anonymisation-columns';
import Button from '@/components/form/button';
import {
    SmallSideBar,
    SmallSideBarContent,
    SmallSideBarProvider,
} from '@/components/sm-side-bar/small-side-bar';
import type { SmallSidebarNavItem } from '@/components/sm-side-bar/types';
import { Muted } from '@/components/typograpfy/typography';
import { Separator } from '@/components/ui/separator';
import type { ProjectPartFromRef } from '@/hooks/use-project';
import { useTempProjectStore } from '@/store/use-project-store';
import DriverConfigController from '@/wayfinder/actions/App/Http/Controllers/API/DriverConfigController';

const TablesAnonymisationContent = ({ cloningPayload, }: { cloningPayload: CloningClientPayload; }) => {
    const { t, tChoice } = useLaravelReactI18n();

    const tables: SmallSidebarNavItem[] = useMemo(() => {
        return cloningPayload.tables.map((table) => ({
            name: table.name,
            value: table.name,
            icon: table.skipReason ? CircleSlashIcon : Table2Icon,
            badge: table.skipReason ? t('cloning.ui.skipped') : table.columns.length.toString(),
            component: <TablesAnonymisationColumns table={table} />,
        }));
    }, [cloningPayload.tables, t]);

    const skippedTables = useMemo(() => {
        return cloningPayload.tables.filter((table) => table.skipReason).map((table) => table.name);
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
                <div className="flex gap-2">
                    <Button
                        icon={DatabaseBackupIcon}
                        tooltip={t('cloning.ui.keep_all_tooltip')}
                        variant="secondary"
                        onClick={keepAll}
                    >
                        {t('cloning.ui.keep_all')}
                    </Button>
                    <Button
                        icon={RefreshCwIcon}
                        tooltip={t('cloning.ui.reset_all_tooltip')}
                        variant="secondary"
                        onClick={resetAll}
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Muted>{tChoice('tables.counts.tables', tables.length)}</Muted>
                    <Separator orientation="vertical" />
                    <Muted>{tChoice('cloning.ui.detected_columns', columnCount)}</Muted>
                </div>
            </div>

            {skippedTables.length > 0 && (
                <Alert
                    variant="warning"
                    title={tChoice('cloning.ui.skipped_count', skippedTables.length)}
                    message={t('cloning.ui.skipped_hint', { tables: skippedTables.join(', ') })}
                />
            )}

            <CardFrame innerPadding={false}>
                {tables.length > 0 && tableOrder.length > 0 && (
                    <SmallSideBarProvider pages={tables}>
                        <div className="flex h-full w-full flex-col overflow-hidden p-0 md:flex-row">
                            <SmallSideBar
                                searchBar
                                searchPlaceholder={t('tables.search_table')}
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


export type TableAnonymisationRef = ProjectPartFromRef<CloningDraft>


type Props = {
    ref?: React.Ref<TableAnonymisationRef>;
};

const TablesAnonymisation = ({ ref } : Props) => {
    const { t } = useLaravelReactI18n();

    const { get, processing } = useHttp<any, { data: CloningClientPayload }>();

    const loadPayload = useCloningStore((s) => s.loadPayload);
    const buildDraft = useCloningStore((s) => s.buildDraft);
    const originalPayload = useCloningStore((s) => s.originalPayload);
    const config = useTempProjectStore(state => state.cloningDriverConfig);

    const [cloningPayload, setCloningPayload] = useState<null | CloningClientPayload>(originalPayload);
    const [cloningError, setCloningError] = useState<string | null>(null);

    const isLoaded = useRef(false);

    useEffect(() => {

        if (isLoaded.current || originalPayload) {
            return;
        }

        if (!config?.from_id) {
            return;
        }

        isLoaded.current = true;

        get(
            DriverConfigController.cloningClientPayload({ config: config.from_id }).url,
            {
                onSuccess: ({ data }) => {
                    loadPayload(data);
                    setCloningPayload(data);
                },
                onError: (error) => {
                    setCloningError(error.error);
                },
                onHttpException: (error) => {
                    setCloningError(JSON.parse(error.data).error);
                },
            },
        ).then(() => null);

    }, [config?.from_id, loadPayload])

    useImperativeHandle(ref, () => ({
        submit: () => buildDraft(),
    }));

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

            {cloningError && (
                <Alert
                    title={t('cloning.ui.payload_failed')}
                    message={cloningError}
                    variant="destructive"
                />
            )}
        </div>
    );
};

export default TablesAnonymisation;
