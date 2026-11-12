import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CheckIcon,
    Code2Icon,
    PencilRulerIcon,
    TriangleAlertIcon,
    XIcon,
} from 'lucide-react';
import { useMemo } from 'react';
import Alert from '@/components/alert';
import {
    CodeBlockCopyButton,
    CodeBlockHeader,
    CodeBlockTitle,
} from '@/components/code-block';
import Button from '@/components/form/button';
import SqlCodeBlock from '@/components/tables/sql-code-block';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { formatSql, uppercaseSqlKeywords } from '@/lib/sql';
import type { SchemaChangeKind, SchemaChangePreview } from './types';

type Props = {
    /**
     * The changes read by the server, null while it reads them.
     */
    preview: SchemaChangePreview | null;
    /**
     * The engine the statements are written for, to lay them out.
     */
    driver: string;
    isResponding: boolean;
    onApply: () => void;
    onReject: () => void;
};

const KINDS: SchemaChangeKind[] = [
    'create_table',
    'alter_table',
    'drop_table',
    'raw_sql',
];

/**
 * The changes the assistant proposes, drawn in the diagram behind it: what they do, the statements they would run,
 * and the choice of the user to apply or reject them.
 */
/**
 * The statement laid out one clause per line, or as it is when the formatter cannot read it.
 */
function laidOut(statement: string, driver: string): string {
    try {
        return formatSql(statement, driver);
    } catch {
        return uppercaseSqlKeywords(statement);
    }
}

export default function DraftBar({
    preview,
    driver,
    isResponding,
    onApply,
    onReject,
}: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const summary = preview
        ? KINDS.map((kind) => ({
              kind,
              count: preview.changes.filter((change) => change.kind === kind)
                  .length,
          })).filter(({ count }) => count > 0)
        : [];
    const canApply = !!preview?.valid && !preview.failure && !isResponding;
    const statements = preview?.statements;
    const script = useMemo(
        () =>
            (statements ?? [])
                .map((statement) => `${laidOut(statement, driver)};`)
                .join('\n\n'),
        [driver, statements],
    );

    return (
        <div
            role="region"
            aria-label={t('erd.assistant.draft.title')}
            className="pointer-events-auto flex w-full max-w-2xl flex-col gap-2 rounded-lg border bg-background/95 p-3 shadow-lg backdrop-blur"
        >
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <PencilRulerIcon className="size-4 shrink-0 text-emerald-600" />
                <div className="flex min-w-0 flex-1 flex-col">
                    <span className="text-sm font-medium">
                        {t('erd.assistant.draft.title')}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {preview
                            ? summary
                                  .map(({ kind, count }) =>
                                      tChoice(
                                          `erd.assistant.draft.summary.${kind}`,
                                          count,
                                          { count: String(count) },
                                      ),
                                  )
                                  .join(' · ')
                            : t('erd.assistant.draft.loading')}
                    </span>
                </div>
                {!preview && <Spinner className="size-4" />}
                {preview && preview.statements.length > 0 && (
                    <Popover>
                        <PopoverTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <Code2Icon />
                                {t('erd.assistant.draft.sql')}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            align="end"
                            className="w-[min(48rem,calc(100vw-2rem))] gap-0 p-0"
                        >
                            {/* The statements scroll inside the block: the popover lays its children out in a column that would shrink them. */}
                            <SqlCodeBlock
                                code={script}
                                showLineNumbers
                                className="border-0 [&_pre]:max-h-[60vh] [&_pre]:overflow-auto [&_pre]:text-xs [&_pre_code]:text-xs"
                            >
                                <CodeBlockHeader>
                                    <CodeBlockTitle>
                                        {tChoice(
                                            'erd.assistant.draft.statements',
                                            preview.statements.length,
                                            {
                                                count: String(
                                                    preview.statements.length,
                                                ),
                                            },
                                        )}
                                    </CodeBlockTitle>
                                    <CodeBlockCopyButton
                                        className="size-7"
                                        aria-label={t(
                                            'erd.assistant.draft.copy',
                                        )}
                                        title={t('erd.assistant.draft.copy')}
                                    />
                                </CodeBlockHeader>
                            </SqlCodeBlock>
                        </PopoverContent>
                    </Popover>
                )}
                <Button
                    variant="outline"
                    size="sm"
                    disabled={isResponding}
                    onClick={onReject}
                >
                    <XIcon />
                    {t('erd.assistant.draft.reject')}
                </Button>
                <Button
                    size="sm"
                    disabled={!canApply}
                    processing={isResponding}
                    onClick={onApply}
                >
                    <CheckIcon />
                    {t('erd.assistant.draft.apply')}
                </Button>
            </div>

            {preview?.valid && preview.destructive && (
                <p className="flex items-center gap-1.5 text-xs text-warning">
                    <TriangleAlertIcon className="size-3.5 shrink-0" />
                    {t('erd.assistant.draft.destructive')}
                </p>
            )}
            {preview && !preview.valid && (
                <Alert
                    variant="warning"
                    title={t('erd.assistant.draft.invalid')}
                    message={preview.errors.join('\n')}
                />
            )}
            {preview?.failure && (
                <Alert
                    variant="destructive"
                    title={t('erd.assistant.draft.preview_failed')}
                    message={preview.failure.message}
                >
                    {preview.failure.statement && (
                        <code className="font-mono text-xs break-all opacity-80">
                            {uppercaseSqlKeywords(preview.failure.statement)}
                        </code>
                    )}
                </Alert>
            )}
        </div>
    );
}
