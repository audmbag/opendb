import { ArrowDownIcon, CopyIcon, SettingsIcon } from 'lucide-react';
import React, { useMemo } from 'react';
import CardFrame from '@/components/card-frame';
import { CLONING_SWITCH_OPTIONS } from '@/components/cloning/cloning-configuration';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type { CloningPayload } from '@/components/cloning/cloning.types';
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
    return (
        <Badge variant={value ? 'success-light' : 'secondary'}>
            {value ? 'Oui' : 'Non'}
        </Badge>
    );
};

export default function CloningConfirmationModal({ open, onOpenChange, source, payload, onConfirm }: Props) {
    const drivers = useDriverStore((state) => state.drivers);
    const strategyLabels = useCloningStore((state) => state.labels.strategies);
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
            title="Confirmer le clonage"
            description="Vérifiez les détails du clonage avant de le lancer"
            icon={CopyIcon}
        >
            <div className="flex flex-col">
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex flex-col gap-2">
                        <DatabaseStatusBar driver_config={source} showLabels={false} buttons={['copy', 'reconnect']} />
                        <ArrowDownIcon className="mx-auto size-4 text-muted-foreground" />
                        <DatabaseStatusBar driver_config={destination} showLabels={false} buttons={['copy', 'reconnect']} />
                    </div>

                    <CardFrame title="Tables et anonymisation" innerPadding={false}>
                        <SummaryRow label="Tables à cloner">
                            <Badge variant="primary-light">{`${summary.cloned} sur ${summary.total}`}</Badge>
                        </SummaryRow>
                        <SummaryRow label="Tables ignorées au clonage">
                            <Badge variant="secondary">{summary.ignored}</Badge>
                        </SummaryRow>
                        <SummaryRow label="Tables accessibles à l'agent IA">
                            <Badge variant="secondary">{`${summary.agentAccessible} sur ${summary.cloned}`}</Badge>
                        </SummaryRow>
                        <SummaryRow label="Colonnes anonymisées">
                            {summary.strategies.map(([strategy, count]) => (
                                <Badge key={strategy} variant="outline">
                                    {`${strategyLabels[strategy] ?? strategy} : ${count}`}
                                </Badge>
                            ))}
                            <Badge variant="secondary">{summary.anonymisedColumns}</Badge>
                        </SummaryRow>
                    </CardFrame>

                    <CardFrame title="Options de configuration" innerPadding={false}>
                        <SummaryRow label="Créer la base de données si elle n'existe pas">
                            <BooleanBadge value={!!payload.cloningDriverConfig.create_database_if_not_exists} />
                        </SummaryRow>
                        {CLONING_SWITCH_OPTIONS.map(({ name, label }) => (
                            <SummaryRow key={name} label={label}>
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
                        Modifier la configuration
                    </Button>
                    <Button
                        icon={CopyIcon}
                        className="flex-row-reverse"
                        onClick={onConfirm}
                    >
                        Confirmer le clonage
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
