import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowDownIcon, CopyIcon, SettingsIcon } from 'lucide-react';
import React, { useMemo } from 'react';
import CardFrame from '@/components/card-frame';
import { CLONING_SWITCH_OPTIONS } from '@/components/cloning/cloning-configuration';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type { CloningPayload } from '@/components/cloning/cloning.types';
import { useCloningLabels } from '@/components/cloning/use-cloning-labels';
import { DatabaseStatusBar } from '@/components/database-status-bar';
import Button from '@/components/form/button';
import Modal from '@/components/modals/modal';
import { Badge } from '@/components/reui/badge';
import { Small } from '@/components/typograpfy/typography';
import { useDriverStore } from '@/store/driver-store';
import type { DriverConfig } from '@/types/databases';

type Props = Pick<React.ComponentProps<typeof Modal>, 'open' | 'onOpenChange'> & {
    source: DriverConfig;
    payload: CloningPayload;
    onConfirm: () => void;
};

const SummaryRow = ({ label, children }: { label: string; children: React.ReactNode }) => {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-border p-3 last:border-b-0">
            <Small>{label}</Small>
            <div className="flex flex-wrap items-center justify-end gap-1">
                {children}
            </div>
        </div>
    );
};

const BooleanBadge = ({ value }: { value: boolean }) => {
    const { t } = useLaravelReactI18n();

    return (
        <Badge variant={value ? 'success-light' : 'secondary'}>
            {value ? t('common.yes') : t('common.no')}
        </Badge>
    );
};

export default function CloningConfirmationModal({ open, onOpenChange, source, payload, onConfirm }: Props) {
    const { t } = useLaravelReactI18n();
    const drivers = useDriverStore((state) => state.drivers);
    const cloningLabels = useCloningLabels();
    const cloningTables = useCloningStore((state) => state.originalPayload?.tables);

    const destination = useMemo(() => {
        return {
            ...payload.cloningDriverConfig,
            driver: drivers.find((driver) => driver.id === payload.cloningDriverConfig.driver_id),
        } as DriverConfig;
    }, [drivers, payload.cloningDriverConfig]);

    const summary = useMemo(() => {
        const tables = source.tables_info ?? [];
        const skipped = new Set((cloningTables ?? []).filter((table) => table.skipReason).map((table) => table.name));
        const clonedTables = tables.filter((table) => !payload.cloningDisabled.includes(table.name) && !skipped.has(table.name));
        const strategies: Record<string, number> = {};
        let anonymisedColumns = 0;

        for (const table of clonedTables) {
            for (const { strategy } of Object.values(payload.anonymisation.tables[table.name] ?? {})) {
                if (strategy === 'keep') {
                    continue;
                }

                anonymisedColumns++;
                strategies[strategy] = (strategies[strategy] ?? 0) + 1;
            }
        }

        return {
            total: tables.length,
            cloned: clonedTables.length,
            ignored: tables.length - clonedTables.length,
            agentAccessible: clonedTables.filter((table) => !payload.agentDisabled.includes(table.name)).length,
            anonymisedColumns,
            strategies: Object.entries(strategies),
        };
    }, [cloningTables, payload, source.tables_info]);

    return (
        <Modal
            showHeader
            scrollable
            className="h-auto"
            open={open}
            onOpenChange={onOpenChange}
            title={t('cloning.ui.confirm')}
            description={t('cloning.ui.confirm_description')}
            icon={CopyIcon}
        >
            <div className="flex flex-col">
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex flex-col gap-2">
                        <DatabaseStatusBar driver_config={source} showLabels={false} buttons={['copy', 'reconnect']} />
                        <ArrowDownIcon className="mx-auto size-4 text-muted-foreground" />
                        <DatabaseStatusBar driver_config={destination} showLabels={false} buttons={['copy', 'reconnect']} />
                    </div>

                    <CardFrame title={t('cloning.ui.tables_and_anonymisation')} innerPadding={false}>
                        <SummaryRow label={t('cloning.ui.tables_to_clone')}>
                            <Badge variant="primary-light">{t('cloning.ui.count_of', { count: summary.cloned, total: summary.total })}</Badge>
                        </SummaryRow>
                        <SummaryRow label={t('cloning.ui.skipped_tables')}>
                            <Badge variant="secondary">{summary.ignored}</Badge>
                        </SummaryRow>
                        <SummaryRow label={t('cloning.ui.agent_tables')}>
                            <Badge variant="secondary">{t('cloning.ui.count_of', { count: summary.agentAccessible, total: summary.cloned })}</Badge>
                        </SummaryRow>
                        <SummaryRow label={t('cloning.ui.anonymised_columns')}>
                            {summary.strategies.map(([strategy, count]) => (
                                <Badge key={strategy} variant="outline">
                                    {t('cloning.ui.strategy_count', { strategy: cloningLabels.strategy(strategy), count })}
                                </Badge>
                            ))}
                            <Badge variant="secondary">{summary.anonymisedColumns}</Badge>
                        </SummaryRow>
                    </CardFrame>

                    <CardFrame title={t('cloning.ui.configuration_options')} innerPadding={false}>
                        <SummaryRow label={t('projects.form.create_database')}>
                            <BooleanBadge value={!!payload.cloningDriverConfig.create_database_if_not_exists} />
                        </SummaryRow>
                        {CLONING_SWITCH_OPTIONS.map(({ name, label }) => (
                            <SummaryRow key={name} label={t(label)}>
                                <BooleanBadge value={payload.options[name]} />
                            </SummaryRow>
                        ))}
                    </CardFrame>
                </div>

                <div className="flex items-center justify-between gap-2.5 border-t border-border bg-accent p-4">
                    <Button
                        variant="outline"
                        icon={SettingsIcon}
                        onClick={() => onOpenChange?.(false)}
                    >
                        {t('cloning.ui.edit_configuration')}
                    </Button>
                    <Button
                        icon={CopyIcon}
                        className="flex-row-reverse"
                        onClick={onConfirm}
                    >
                        {t('cloning.ui.confirm')}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
