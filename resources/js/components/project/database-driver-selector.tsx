import { useLaravelReactI18n } from 'laravel-react-i18n';
import { memo, useCallback, useEffect, useRef } from 'react';
import Alert from '@/components/alert';
import DatabaseIconHelper from '@/components/database-icon-helper';
import { Spinner } from '@/components/spinner';
import { Field, FieldContent, FieldLabel, FieldTitle } from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useDriverConfig } from '@/hooks/use-driver-config';
import type { Driver } from '@/types/databases';
import { DefaultDrivers } from '@/types/databases';

type Props = {
    selectedDriver?: string;
    onDriverChange?: (driver: Driver) => void;
    defaultSelected?: string;
    exists?: boolean;
};

const DatabaseDriverSelector = memo(({ exists, defaultSelected, selectedDriver, onDriverChange }: Props) => {
    const { t } = useLaravelReactI18n();

    const { drivers, loadDrivers, loadDriversRequest } = useDriverConfig();

    const hasFetchedRef = useRef(false);

    useEffect(() => {
        if (hasFetchedRef.current) {
            return;
        }

        hasFetchedRef.current = true;
        loadDrivers();
    }, []);

    const handleDriverChange = useCallback((value: string) => {
        const driver = drivers.find((db) => db.id === value);

        onDriverChange?.(driver as Driver);
    }, [drivers, onDriverChange]);

    return (
        <>
            {loadDriversRequest.processing ? (
                <div className="flex items-center justify-center">
                    <Spinner />
                </div>
            ) : (
                <>
                    {exists && (
                        <Alert
                            variant="warning"
                            title={t('common.warning')}
                            message={t('projects.form.change_database_warning')}
                        />
                    )}
                    <RadioGroup
                        value={selectedDriver}
                        onValueChange={handleDriverChange}
                        className="flex gap-2"
                        defaultValue={defaultSelected || DefaultDrivers.MYSQL}
                    >
                        {drivers.map((driver) => (
                            <FieldLabel key={driver.id} htmlFor={driver.id}>
                                <Field orientation="horizontal">
                                    <FieldContent>
                                        <FieldTitle className="flex flex-col items-start">
                                            <DatabaseIconHelper
                                                icon={driver.icon}
                                                className="size-8"
                                            />
                                            <span className="hidden text-sm font-semibold sm:inline">
                                                {driver.name}
                                            </span>
                                        </FieldTitle>
                                    </FieldContent>
                                    <RadioGroupItem
                                        value={driver.id.toString()}
                                        id={driver.id}
                                    />
                                </Field>
                            </FieldLabel>
                        ))}
                    </RadioGroup>
                </>
            )}
        </>
    );
})



export default DatabaseDriverSelector;
