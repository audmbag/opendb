import { useLaravelReactI18n } from 'laravel-react-i18n';
import React from 'react';
import type { CloningProgress } from '@/components/cloning/use-cloning-progress';
import { Muted, Small } from '@/components/typograpfy/typography';
import { numberFormatter } from '@/lib/intl';

type Props = {
    progress: CloningProgress | null;
    elapsedSeconds: number;
};

/**
 * Temps écoulé au format 02:15, ou 1:02:15 au-delà d'une heure.
 */
export const formatElapsed = (seconds: number): string => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = seconds % 60;
    const pad = (value: number) => String(value).padStart(2, '0');

    return hours > 0
        ? `${hours}:${pad(minutes)}:${pad(rest)}`
        : `${pad(minutes)}:${pad(rest)}`;
};

/**
 * Part du clonage déjà faite, entre 0 et 1 : les tables copiées, plus la part copiée de la table en cours.
 */
const completedFraction = (progress: CloningProgress | null): number => {
    if (!progress || progress.phase !== 'tables' || progress.tableCount === 0) {
        return 0;
    }

    const tableFraction = progress.tableExpectedRows
        ? Math.min(progress.tableRows / progress.tableExpectedRows, 1)
        : 0;

    return (progress.tablePosition - 1 + tableFraction) / progress.tableCount;
};

/**
 * L'étape en cours du clonage, la table copiée et ses lignes, une barre d'avancement et le temps écoulé.
 */
export default function CloningProgressDetails({
    progress,
    elapsedSeconds,
}: Props) {
    const { t } = useLaravelReactI18n();
    const format = numberFormatter();
    const percent = Math.round(completedFraction(progress) * 100);
    const copyingTable =
        progress?.phase === 'tables' && progress.table !== null;

    return (
        <div className="flex w-full flex-col gap-2 text-left">
            <Small
                className="truncate"
                title={copyingTable ? (progress.table ?? undefined) : undefined}
            >
                {copyingTable
                    ? t('cloning.ui.progress.table', {
                          position: progress.tablePosition,
                          count: progress.tableCount,
                          table: progress.table ?? '',
                      })
                    : t(
                          `cloning.ui.progress.phases.${progress?.phase ?? 'preparing'}`,
                      )}
            </Small>
            <div
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width] duration-500 ease-out"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <div className="flex items-center justify-between gap-4 tabular-nums">
                <Muted className="truncate text-xs">
                    {copyingTable
                        ? progress.tableExpectedRows !== null
                            ? t('cloning.ui.progress.rows', {
                                  copied: format.format(progress.tableRows),
                                  expected: format.format(
                                      progress.tableExpectedRows,
                                  ),
                              })
                            : t('cloning.ui.progress.rows_unknown', {
                                  copied: format.format(progress.tableRows),
                              })
                        : ' '}
                </Muted>
                <Muted className="shrink-0 text-xs">
                    {t('cloning.ui.progress.elapsed', {
                        time: formatElapsed(elapsedSeconds),
                    })}
                </Muted>
            </div>
        </div>
    );
}
