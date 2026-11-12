import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CircleAlertIcon,
    InfoIcon,
    StethoscopeIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import Button from '@/components/form/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { findingText } from './diagnostics';
import type { SchemaDiagnostics, SchemaDoctorFinding } from './types';

export const SEVERITY_ICONS = {
    error: { icon: CircleAlertIcon, className: 'text-destructive' },
    warning: { icon: TriangleAlertIcon, className: 'text-warning' },
    info: { icon: InfoIcon, className: 'text-sky-500' },
} as const;

type Props = {
    /**
     * Undefined until the doctor has answered.
     */
    diagnostics: SchemaDiagnostics | undefined;
    onShowTable: (table: string) => void;
};

/**
 * The findings of the doctor over the whole structure, grouped by table. Choosing one brings its table into view.
 */
export default function DiagnosticsPopover({
    diagnostics,
    onShowTable,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [isOpen, setIsOpen] = useState(false);
    const byTable = useMemo(() => {
        const groups = new Map<string, SchemaDoctorFinding[]>();

        diagnostics?.findings.forEach((finding) =>
            groups.set(finding.table, [
                ...(groups.get(finding.table) ?? []),
                finding,
            ]),
        );

        return [...groups.entries()].sort(([first], [second]) =>
            first.localeCompare(second),
        );
    }, [diagnostics]);
    const total = diagnostics?.summary.total ?? 0;

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    icon={StethoscopeIcon}
                    processing={diagnostics === undefined}
                    tooltip={
                        diagnostics === undefined
                            ? t('erd.doctor.loading')
                            : undefined
                    }
                >
                    {t('erd.doctor.label')}
                    {diagnostics !== undefined && (
                        <span
                            className={cn(
                                'text-xs tabular-nums',
                                diagnostics.summary.error > 0
                                    ? 'text-destructive'
                                    : diagnostics.summary.warning > 0
                                      ? 'text-warning'
                                      : 'text-muted-foreground',
                            )}
                        >
                            {total}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-96 gap-0 p-0">
                <div className="border-b px-3 py-2 text-sm">
                    <p className="font-medium">{t('erd.doctor.title')}</p>
                    <p className="text-xs text-muted-foreground">
                        {t('erd.doctor.summary', {
                            error: String(diagnostics?.summary.error ?? 0),
                            warning: String(diagnostics?.summary.warning ?? 0),
                            info: String(diagnostics?.summary.info ?? 0),
                        })}
                    </p>
                </div>
                {total === 0 ? (
                    <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                        {t('erd.doctor.none')}
                    </p>
                ) : (
                    <ul className="max-h-96 overflow-y-auto p-1">
                        {byTable.map(([table, findings]) => (
                            <li key={table} className="py-1">
                                <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                    {table}
                                </p>
                                <ul>
                                    {findings.map((finding) => {
                                        const { icon: Icon, className } =
                                            SEVERITY_ICONS[finding.severity];

                                        return (
                                            <li key={finding.fingerprint}>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setIsOpen(false);
                                                        onShowTable(table);
                                                    }}
                                                    className="flex w-full items-start gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted"
                                                >
                                                    <Icon
                                                        className={cn(
                                                            'mt-0.5 size-3.5 shrink-0',
                                                            className,
                                                        )}
                                                        aria-label={t(
                                                            `erd.doctor.severity.${finding.severity}`,
                                                        )}
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        {findingText(
                                                            t,
                                                            finding,
                                                            'title',
                                                        )}
                                                        {finding.column && (
                                                            <span className="ml-1.5 font-mono text-xs text-muted-foreground">
                                                                {finding.column}
                                                            </span>
                                                        )}
                                                    </span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </li>
                        ))}
                    </ul>
                )}
            </PopoverContent>
        </Popover>
    );
}
