import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowLeftIcon, ChevronRightIcon, ShapesIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ArtifactPreview } from '@/components/ai/artifact-preview-button';
import ExportPreview from '@/components/ai/artifacts/export-preview';
import { formatNumber } from '@/components/ai/artifacts/format';
import { describeKey } from '@/components/ai/artifacts/record/format';
import CardFrame from '@/components/card-frame';
import { EmptyState } from '@/components/empty-state';
import IconStackGlow from '@/components/icon-stack-glow';
import { Button } from '@/components/ui/button';
import { useChatLayout } from '@/hooks/use-chat-layout';
import { cn } from '@/lib/utils';
import { AgentTools,  getMetadata } from '@/types/agent';
import type {ExportDatabaseToolUIPart} from '@/types/agent';
import type {
    AgentToolNameType,
    ChartToolUIPart,
    CustomOutputToolUIPart,
    ExplainQueryToolUIPart,
    KpisToolUIPart,
    ProfileTableToolUIPart,
    QueryResultsToolUIPart,
    RecordKey,
    RecordToolUIPart,
    ShemaDiagramToolUIPart,
} from '@/types/agent';

export type ConversationArtifact = {
    part: CustomOutputToolUIPart;
    isMessageSaved: boolean;
};

type ArtifactSummary = {
    title: string;
    description?: string;
};

type Translator = ReturnType<typeof useLaravelReactI18n>;

/**
 * What identifies an artifact in the list, from the output of its tool call.
 */
const summarize = (part: CustomOutputToolUIPart, { t, tChoice }: Translator): ArtifactSummary => {
    switch (part.type) {
        case AgentTools.ShowSchemaDiagram: {
            const { focusedTable, displayedTablesCount } = (part as ShemaDiagramToolUIPart).output;

            return {
                title: focusedTable ? t('ai.artifacts.schema_of', { table: focusedTable }) : t('flow.database_schema'),
                description:
                    displayedTablesCount === undefined
                        ? undefined
                        : tChoice('tables.counts.tables', displayedTablesCount, { count: formatNumber(displayedTablesCount) }),
            };
        }
        case AgentTools.ShowQueryResults: {
            const { title, totalRows } = (part as QueryResultsToolUIPart).output;

            return {
                title: title ?? t('ai.artifacts.query_results'),
                description: tChoice('tables.counts.rows', totalRows, { count: formatNumber(totalRows) }),
            };
        }
        case AgentTools.ShowKpis:
            return { title: t('ai.artifacts.kpis'), description: (part as KpisToolUIPart).output.labels.join(', ') };
        case AgentTools.ShowChart: {
            const { title, chartType, xColumn, yColumns } = (part as ChartToolUIPart).output;

            return {
                title: title ?? t('ai.artifacts.chart_title', { y: yColumns.join(', '), x: xColumn }),
                description: t(`ai.artifacts.chart_types.${chartType}`),
            };
        }
        case AgentTools.ProfileTable:
            return { title: t('ai.artifacts.profile_of', { table: (part as ProfileTableToolUIPart).output.table }) };
        case AgentTools.ShowRecord: {
            const { table, key } = (part as RecordToolUIPart).output;

            return { title: table, description: describeKey(key as RecordKey) };
        }
        case AgentTools.ExplainQuery:
            return {
                title: t('query_plan.title'),
                description: ((part as ExplainQueryToolUIPart).input as { query?: string } | undefined)?.query,
            };
        default:
            return { title: t('ai.artifacts.artifact') };
    }
};

const ArtifactRow = ({ part, onOpen }: { part: CustomOutputToolUIPart; onOpen: () => void }) => {
    const translator = useLaravelReactI18n();
    const metadata = getMetadata(part.type as AgentToolNameType);
    const { title, description } = summarize(part, translator);

    return (
        <button
            type="button"
            onClick={onOpen}
            className="flex w-full items-center gap-3 border-b border-border bg-card p-3 text-left transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
        >
            {metadata && (
                <IconStackGlow className='shrink-0' icon={<metadata.icon />} />
            )}
            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="truncate text-sm font-medium">{title}</span>
                {description && <span className="truncate text-xs text-muted-foreground">{description}</span>}
            </span>
            <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground" />
        </button>
    );
};

/**
 * The artifacts of the conversation in the data preview: a click on one displays its content at the maximum width.
 * An export has no other content than its file, so it is listed as it is.
 */
export default function ArtifactPanel({ artifacts }: { artifacts: ConversationArtifact[] }) {
    const translator = useLaravelReactI18n();
    const { t } = translator;
    const { setDataPreviewExpanded } = useChatLayout();

    const [selectedToolCallId, setSelectedToolCallId] = useState<string | null>(null);

    const selected = artifacts.find(({ part }) => part.toolCallId === selectedToolCallId) ?? null;
    const isExpanded = selected !== null;

    useEffect(() => {
        setDataPreviewExpanded(isExpanded);

        return () => setDataPreviewExpanded(false);
    }, [isExpanded, setDataPreviewExpanded]);

    if (selected) {
        return (
            <CardFrame className="h-full" innerPadding={false} innerClassName="flex h-full flex-col">
                <div className="flex items-center gap-2 border-b px-3 py-2">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        autoFocus
                        aria-label={t('ai.artifacts.back_to_list')}
                        onClick={() => setSelectedToolCallId(null)}
                    >
                        <ArrowLeftIcon />
                    </Button>
                    <span className="truncate text-sm font-medium">
                        {summarize(selected.part, translator).title}
                    </span>
                </div>
                <div className="flex-1 overflow-auto p-3">
                    <ArtifactPreview
                        artifactable={selected.part}
                        isMessageSaved={selected.isMessageSaved}
                    />
                </div>
            </CardFrame>
        );
    }

    return (
        <CardFrame
            className="h-full"
            innerPadding={false}
            innerClassName="flex h-full flex-col"
            title={t('ai.artifacts.list_title', { count: artifacts.length ? formatNumber(artifacts.length) : '' })}
        >
            {artifacts.length === 0 ? (
                <EmptyState
                    icon={ShapesIcon}
                    title={t('ai.artifacts.empty')}
                    description={t('ai.artifacts.empty_description')}
                />
            ) : (
                <ul className="flex min-h-0 flex-1 flex-col overflow-auto">
                    {artifacts.map(({ part, isMessageSaved }, index) => (
                        <li key={part.toolCallId}>
                            {part.type === AgentTools.ExportDatabase ? (
                                <ExportPreview
                                    className={
                                        cn('rounded-none border-x-0 border-t-0', index === artifacts.length - 1 && 'border-b-0')
                                    }
                                    part={part as ExportDatabaseToolUIPart}
                                    enabled={isMessageSaved}
                                />
                            ) : (
                                <ArtifactRow
                                    part={part}
                                    onOpen={() =>
                                        setSelectedToolCallId(part.toolCallId)
                                    }
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </CardFrame>
    );
}
