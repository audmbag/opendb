import { useLaravelReactI18n } from 'laravel-react-i18n';
import { BracesIcon } from 'lucide-react';
import { useState } from 'react';
import { createCallable } from 'react-call';
import Button from '@/components/form/button';
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ParameterValues = Record<string, string | null>;

type Props = {
    names: string[];
    /**
     * The values given the last time, kept for the parameters that are still there.
     */
    initial: ParameterValues;
};

/**
 * The values of the `:name` parameters of the statements about to run, asked before they run. Resolves with the
 * values, NULL included, or null when the user gives up.
 */
const ParametersDialog = createCallable<Props, ParameterValues | null>(
    ({ names, initial, call }) => {
        const { t } = useLaravelReactI18n();
        const [values, setValues] = useState<ParameterValues>(() =>
            Object.fromEntries(
                names.map((name) => [
                    name,
                    name in initial ? initial[name] : '',
                ]),
            ),
        );

        return (
            <Modal
                open={!call.ended}
                showHeader
                scrollable
                className="h-auto sm:max-w-md"
                onOpenChange={(open) => !open && call.end(null)}
                icon={BracesIcon}
                title={t('tables.parameters.title')}
                description={t('tables.parameters.description')}
            >
                <form
                    className="flex flex-col"
                    onSubmit={(event) => {
                        event.preventDefault();
                        call.end(values);
                    }}
                >
                    <div className="flex max-h-[50vh] flex-col gap-3 overflow-y-auto p-4">
                        {names.map((name, index) => {
                            const isNull = values[name] === null;

                            return (
                                <div
                                    key={name}
                                    className="flex flex-col gap-1.5"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <Label
                                            htmlFor={`parameter-${name}`}
                                            className="font-mono text-xs"
                                        >
                                            :{name}
                                        </Label>
                                        <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <Checkbox
                                                checked={isNull}
                                                onCheckedChange={(checked) =>
                                                    setValues({
                                                        ...values,
                                                        [name]: checked
                                                            ? null
                                                            : '',
                                                    })
                                                }
                                            />
                                            NULL
                                        </label>
                                    </div>
                                    <Input
                                        id={`parameter-${name}`}
                                        value={values[name] ?? ''}
                                        disabled={isNull}
                                        placeholder={isNull ? 'NULL' : ''}
                                        autoFocus={index === 0}
                                        autoComplete="off"
                                        className="font-mono"
                                        onChange={(event) =>
                                            setValues({
                                                ...values,
                                                [name]: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                            );
                        })}
                    </div>

                    <ModalFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => call.end(null)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit">{t('tables.console.run')}</Button>
                    </ModalFooter>
                </form>
            </Modal>
        );
    },
);

export default ParametersDialog;
