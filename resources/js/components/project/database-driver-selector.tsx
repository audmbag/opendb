import { memo, useCallback, useEffect, useRef } from 'react';
import Alert from '@/components/alert';
import DatabaseIconHelper from '@/components/database-icon-helper';
import { Field, FieldContent, FieldLabel, FieldTitle } from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { useDriverConfig } from '@/hooks/use-driver-config';
import { DriverStore } from '@/store/driver-store';
import { DefaultDrivers, Driver } from '@/types/databases';
import type { Project } from '@/types/models/project';

type Props = {
    project?: Project;
    selectedDriver?: string;
    onDriverChange?: (driver: Driver) => void;
    defaultSelected?: string;
};

const DatabaseDriverSelector = memo(({ project, defaultSelected, selectedDriver, onDriverChange }: Props) => {

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
                    {project && (
                        <Alert
                            variant="warning"
                            title="Attention"
                            message="La modification d'une base de données déjà utilisée par l'agent peut entrainer la confusion, préférez créer un nouveau projet avec une nouvelle base de donnés."
                        />
                    )}
                    <input
                        type="hidden"
                        readOnly
                        name="driver_config.driver_id"
                        defaultValue={selectedDriver}
                    />
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
