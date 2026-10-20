import { useHttp } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useDriverStore } from '@/store/driver-store';
import type { Driver, DriverConfig } from '@/types/databases';
import  { DefaultDrivers } from '@/types/databases';
import type { APIData } from '@/types/global';
import DriverConfigController from '@/wayfinder/actions/App/Http/Controllers/API/DriverConfigController';
import { CloningDriverConfig } from '@/components/cloning/cloning.types';


export type ConnectionTestResult = { success: boolean; message: string, willCreateDatabase?: boolean };

const purgeConfig = (driverConfig: DriverConfig & Pick<CloningDriverConfig, 'from_id'>): Omit<DriverConfig, "id"> & {id?: number, from_id?: number} => {
    if (driverConfig.driver_id === DefaultDrivers.SQLITE) {
        return {
            database: driverConfig.database,
            driver_id: driverConfig.driver_id,
            create_database_if_not_exists: driverConfig.create_database_if_not_exists,
            id: driverConfig.id,
            from_id: driverConfig.from_id
        };
    }

    return {
        driver_id: driverConfig.driver_id,
        host: driverConfig.host,
        port: driverConfig.port,
        username: driverConfig.username,
        password: driverConfig.password,
        database: driverConfig.database,
        ssh: driverConfig.ssh,
        create_database_if_not_exists: driverConfig.create_database_if_not_exists,
        id: driverConfig.id,
        from_id: driverConfig.from_id
    };
};


const useDriverConfig = () => {
    const loadDriversRequest = useHttp<object, APIData<Driver>>({});

    const drivers = useDriverStore((state) => state.drivers);
    const fillDrivers = useDriverStore((state) => state.fillDrivers);

    const loadDrivers = useCallback((refresh: boolean = false) => {
        if (drivers.length > 0 && !refresh) {
            return;
        }

        loadDriversRequest.get(DriverConfigController.index.url(), {
            onSuccess: ({ data }) => {
                fillDrivers(data);
            },
            onError: () => {
                toast.error('Impossible de charger les Drivers', {
                    id: 'load-database-ms-error',
                });
            },
        });
    }, [drivers.length, fillDrivers]);


    return {
        loadDrivers,
        loadDriversRequest,
        drivers,
    };
}


const useDriverConfigTestConnection = () => {

    const { post, errors, processing, resetAndClearErrors, optimistic } = useHttp<Omit<DriverConfig, "id">, ConnectionTestResult>();

    const testConnection = useCallback(
        async (
            driverConfig: DriverConfig & Pick<CloningDriverConfig, 'from_id'>,
            successCallback?: (result: ConnectionTestResult) => void,
            errorCallback?: () => void,
        ) => {
            resetAndClearErrors();

            optimistic(() => purgeConfig(driverConfig));

            return await post(DriverConfigController.testConnection.url(), {
                onSuccess: (result) => {
                    successCallback?.(result);
                    toast[result.success ? 'success' : 'error'](result.message);
                },
                onError: (errors) => {
                    if (errors.unexpectedError) {
                        toast.error(errors.unexpectedError);
                    }

                    errorCallback?.();
                },
                onHttpException: () => errorCallback?.(),
            });
        },
        [optimistic, resetAndClearErrors],
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


export { useDriverConfigTestConnection, useCopyConfig, useDriverConfig };
