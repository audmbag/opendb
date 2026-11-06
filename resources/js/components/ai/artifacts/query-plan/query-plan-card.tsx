import { TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';
import {
    createdIndex,
    defaultSelectedNode,
    ENGINE_NAMES,
    executionOrder,
    formatChange,
    formatCost,
    formatRows,
    planVersion,
    stepsInOrder,
} from '@/components/ai/artifacts/query-plan/format';
import PlanClassic from '@/components/ai/artifacts/query-plan/plan-classic';
import PlanDetailPanel, {
    resolvedAlertOf,
} from '@/components/ai/artifacts/query-plan/plan-detail-panel';
import PlanJson from '@/components/ai/artifacts/query-plan/plan-json';
import QueryPlanFlow from '@/components/ai/artifacts/query-plan/query-plan-flow';
import QueryPlanSql from '@/components/ai/artifacts/query-plan/query-plan-sql';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type { QueryPlan } from '@/types/agent';

export type PlanVersion = 'before' | 'after';

type Tab = 'tree' | 'table' | 'json';

type Props = {
    plan: QueryPlan;
    version: PlanVersion;
    onVersionChange: (version: PlanVersion) => void;
};

const pillClassName =
    'inline-flex h-6.5 items-center gap-1.5 rounded-full border px-2.5 text-xs';

function AlertsPill({ plan }: { plan: QueryPlan }) {
    const { danger, warn } = plan.totals.alerts;
    const total = danger + warn;

    if (total === 0) {
        return (
            <span className={cn(pillClassName, 'text-muted-foreground')}>
                Aucune alerte
            </span>
        );
    }

    return (
        <span
            className={cn(
                pillClassName,
                'font-medium',
                danger > 0
                    ? 'border-red-500 text-red-600 dark:text-red-400'
                    : 'border-amber-500 text-amber-700 dark:text-amber-400',
            )}
        >
            <TriangleAlertIcon aria-hidden="true" className="size-3.25" />
            {total} {total > 1 ? 'alertes' : 'alerte'}
        </span>
    );
}

/**
 * The figures of the plan shown in the header of the artifact: total cost and its change since the previous plan,
 * rows read, alerts.
 */
export function QueryPlanSummary({
    plan,
    version,
}: {
    plan: QueryPlan;
    version: PlanVersion;
}) {
    const shown = planVersion(plan, version);
    const change =
        version === 'after' ? (plan.comparison?.costChange ?? null) : null;

    return (
        <div className="flex flex-wrap items-center justify-end gap-2">
            {shown.hasCosts && shown.totals.cost !== null && (
                <span className={pillClassName}>
                    <span className="text-muted-foreground">Coût</span>
                    <span className="font-medium tabular-nums">
                        {formatCost(shown.totals.cost)}
                    </span>
                    {change !== null && (
                        <span
                            className={cn(
                                'font-medium tabular-nums',
                                change < 0
                                    ? 'text-green-700 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400',
                            )}
                        >
                            {formatChange(change)}
                        </span>
                    )}
                </span>
            )}
            <span className={pillClassName}>
                <span className="font-medium tabular-nums">
                    {formatRows(shown.totals.rowsRead)}
                </span>
                <span className="text-muted-foreground">lignes lues</span>
            </span>
            <AlertsPill plan={shown} />
        </div>
    );
}

/**
 * The execution plan of a query, frozen when the agent asked for it: the query, whose lines lead to their steps, then
 * the tree beside the detail of the selected step, the EXPLAIN table and the raw output. When the same query was
 * analysed before, both versions of the plan can be compared.
 */
export default function QueryPlanCard({
    plan,
    version,
    onVersionChange,
}: Props) {
    const [tab, setTab] = useState<Tab>('tree');
    const shown = planVersion(plan, version);
    const [selectedId, setSelectedId] = useState(() =>
        defaultSelectedNode(shown),
    );
    const selected =
        shown.nodes.find((node) => node.id === selectedId) ?? shown.nodes[0];
    const order = executionOrder(shown);
    const ordered = stepsInOrder(shown);
    const position = ordered.findIndex((node) => node.id === selected.id);
    const previousStep = ordered[position - 1];
    const nextStep = ordered[position + 1];
    const index = createdIndex(plan);

    const changeVersion = (value: PlanVersion) => {
        onVersionChange(value);
        setSelectedId(defaultSelectedNode(planVersion(plan, value)));
    };

    const selectStep = (id: string) => {
        setSelectedId(id);
        setTab('tree');
    };

    return (
        <>
            {plan.previous && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/40 py-2.5 pr-4 pl-4 text-[13px]">
                    <div className="text-muted-foreground">
                        {index ? (
                            <>
                                Plan recalculé après la création de{' '}
                                <span className="font-mono text-foreground">
                                    {index}
                                </span>
                            </>
                        ) : (
                            'Plan recalculé'
                        )}
                    </div>
                    <Tabs
                        value={version}
                        onValueChange={(value) =>
                            changeVersion(value as PlanVersion)
                        }
                    >
                        <TabsList aria-label="Version du plan">
                            <TabsTrigger value="before" className="px-3">
                                Avant
                            </TabsTrigger>
                            <TabsTrigger value="after" className="px-3">
                                Après
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
            )}

            <div className="pt-3">
                <QueryPlanSql
                    query={shown.query}
                    bindings={shown.bindings}
                    plan={shown}
                    selectedId={tab === 'tree' ? selected.id : null}
                    onSelect={selectStep}
                />
            </div>

            <Tabs
                value={tab}
                onValueChange={(value) => setTab(value as Tab)}
                className="gap-0"
            >
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 px-4 pb-3">
                    <TabsList aria-label="Affichage du plan">
                        <TabsTrigger value="tree" className="px-3">
                            Arbre
                        </TabsTrigger>
                        <TabsTrigger value="table" className="px-3">
                            Tableau
                        </TabsTrigger>
                        <TabsTrigger value="json" className="px-3">
                            JSON brut
                        </TabsTrigger>
                    </TabsList>
                    {tab === 'tree' && (
                        <span className="text-xs text-muted-foreground">
                            Clique sur une étape ou une ligne de la requête ·{' '}
                            <kbd className="rounded border bg-muted px-1 font-sans text-[10px]">
                                ← ↑ → ↓
                            </kbd>{' '}
                            pour parcourir l'arbre
                        </span>
                    )}
                </div>

                <TabsContent value="tree">
                    <QueryPlanFlow
                        plan={shown}
                        selectedId={selected.id}
                        onSelect={setSelectedId}
                        detailPanel={
                            <PlanDetailPanel
                                plan={shown}
                                node={selected}
                                step={order.get(selected.id) ?? 0}
                                stepCount={shown.nodes.length}
                                onPrevious={
                                    previousStep
                                        ? () => setSelectedId(previousStep.id)
                                        : null
                                }
                                onNext={
                                    nextStep
                                        ? () => setSelectedId(nextStep.id)
                                        : null
                                }
                                resolvedAlert={
                                    version === 'after'
                                        ? resolvedAlertOf(selected, plan)
                                        : null
                                }
                            />
                        }
                    />
                </TabsContent>
                <TabsContent value="table">
                    <PlanClassic classic={shown.classic} />
                </TabsContent>
                <TabsContent value="json">
                    <PlanJson raw={shown.raw} />
                </TabsContent>
            </Tabs>

            <div className="border-t px-4 py-3 text-xs text-muted-foreground">
                {shown.hasCosts
                    ? `Plan estimé par ${ENGINE_NAMES[shown.driver]} sans exécuter la requête. Les nombres de lignes et les coûts sont des estimations de l'optimiseur.`
                    : 'SQLite ne fournit ni coût ni estimation de lignes : seule la structure du plan est affichée.'}
            </div>
        </>
    );
}
