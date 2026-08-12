import { useHttp } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { DriverConfig} from '@/types/databases';
import  { DefaultDrivers } from '@/types/databases';
import DriverConfigController from '@/wayfinder/actions/App/Http/Controllers/API/DriverConfigController';


type TestResultProps = { success: boolean; message: string }

const purgeConfig = (driverConfig: DriverConfig): Omit<DriverConfig, "id"> => {
    if (driverConfig.driver_id === DefaultDrivers.SQLITE) {
        return {
            database: driverConfig.database,
            driver_id: driverConfig.driver_id,
        };
    }

    return {
        driver_id: driverConfig.driver_id,
        host: driverConfig.host,
        port: driverConfig.port,
        username: driverConfig.username,
        password: driverConfig.password,
        database: driverConfig.database,
    };
};

const useTestConnection = () => {

    const { post, errors, processing, resetAndClearErrors, optimistic } = useHttp<{ driver_config: Omit<DriverConfig, "id"> }, TestResultProps>();

    const testConnection = useCallback(
        (driverConfig: DriverConfig, successCallback?: (result: TestResultProps) => void) => {

            resetAndClearErrors();

            optimistic(() => ({ driver_config: purgeConfig(driverConfig) }));

            post(DriverConfigController.testConnection.url(), {
                onSuccess: (result) => {
                    successCallback?.(result);
                    toast[result.success ? 'success' : 'error'](result.message);
                },
                onError: (errors) => {
                    if (errors.unexpectedError) {
                        toast.error(errors.unexpectedError);
                    }
                },
            });
        },
        [optimistic, post, resetAndClearErrors],
    );


    return {
        processing,
        errors,
        testConnection,
        resetAndClearErrors,
    };
};


const useCopyConfig = (driverConfig: DriverConfig, duration: number = 1000) => {
    const [copying, setCopying] = useState(false);

    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleCopy = useCallback(() => {
            setCopying(true);

            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }

            if (driverConfig.connection_string) {
                navigator.clipboard
                    .writeText(driverConfig.connection_string.toString())
                    .then(() => {
                        toast.success(
                            'Configuration copiée : ' +
                                driverConfig.connection_string,
                        );
                    });

                timeoutRef.current = setTimeout(() => {
                    setCopying(false);
                }, duration);
            }
        },
        [driverConfig.connection_string, duration],
    );

    return { copying, handleCopy };
};


export { useTestConnection, useCopyConfig };
