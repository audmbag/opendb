import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowDownUpIcon } from 'lucide-react';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import { DefaultDrivers } from '@/types/databases';
import SqlCodeBlock from './sql-code-block';
import type { ColumnMove } from './use-column-move';

type Props = {
    move: ColumnMove | null;
    driver: string;
    onConfirm: () => void;
    onCancel: () => void;
};

/**
 * The statements moving a column, read before they run: how the engine moves it is said under them.
 */
export default function ColumnMoveDialog({
    move,
    driver,
    onConfirm,
    onCancel,
}: Props) {
    const { t } = useLaravelReactI18n();
    const open = move?.statements != null && !move.applied;
    const code = (move?.statements ?? [])
        .map((statement) => `${statement};`)
        .join('\n');

    return (
        <Modal
            open={open}
            showHeader
            scrollable
            className="h-auto sm:max-w-2xl"
            onOpenChange={(next) => !next && onCancel()}
            icon={ArrowDownUpIcon}
            title={t('tables.structure.move_title', {
                column: move?.column ?? '',
            })}
            description={
                move?.after
                    ? t('tables.structure.move_after', {
                          column: move.column,
                          after: move.after,
                          table: move.base.name,
                      })
                    : t('tables.structure.move_first', {
                          column: move?.column ?? '',
                          table: move?.base.name ?? '',
                      })
            }
        >
            <div className="flex flex-col">
                <div className="flex flex-col gap-3 p-4">
                    <div className="max-h-[50vh] overflow-auto rounded-md border">
                        <SqlCodeBlock
                            code={code}
                            className="border-0 [&_pre]:break-all [&_pre]:whitespace-pre-wrap"
                        />
                    </div>
                    <Alert
                        variant={
                            driver === DefaultDrivers.SQLITE
                                ? 'default'
                                : 'warning'
                        }
                        title={
                            driver === DefaultDrivers.SQLITE
                                ? 'SQLite'
                                : 'MySQL · MariaDB'
                        }
                        message={
                            driver === DefaultDrivers.SQLITE
                                ? t('tables.structure.move_sqlite')
                                : t('tables.structure.move_mysql')
                        }
                        className="w-auto"
                    />
                </div>
                <ModalFooter>
                    <Button
                        variant="outline"
                        onClick={onCancel}
                        disabled={move?.applying}
                    >
                        {t('tables.structure.move_cancel')}
                    </Button>
                    <Button
                        onClick={onConfirm}
                        disabled={move?.applying}
                        processing={move?.applying}
                    >
                        {t('tables.structure.move_confirm')}
                    </Button>
                </ModalFooter>
            </div>
        </Modal>
    );
}
