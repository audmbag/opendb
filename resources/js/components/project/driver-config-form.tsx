import { useHttp } from '@inertiajs/react';
import type { FormDataErrors } from 'node_modules/@inertiajs/core/types/types';
import { useEffect, useMemo, useRef, useState } from 'react';
import Alert from '@/components/alert';
import DatabaseIconHelper from '@/components/database-icon-helper';
import Input from '@/components/form/input';
import InputFile from '@/components/form/input-file';
import {
    Field,
    FieldContent,
    FieldLabel,
    FieldTitle,
} from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { useDriverStore } from '@/store/use-driver-store';
import type { Driver } from '@/types/databases';
import { DefaultDrivers } from '@/types/databases';
import type { APIData } from '@/types/global';
import type { Project } from '@/types/models/project';

type Props = {
    errors: FormDataErrors<Project | Project['driver_config']>;
    onDriverChanged?: (driver: string) => void;
    project?: Project;
};

const DriverConfigForm = ({ errors, onDriverChanged, project }: Props) => {

    const { loadData, data: databasesMs } = useDriverStore();

    const [selectedDatabase, setSelectedDatabase] = useState<string>(
        project?.driver_config?.driver_id?.toString() || '',
    );
    const hasFetchedRef = useRef(false);

    const [changeable, setChangeable] = useState({
        host: project?.driver_config?.host || '',
        port: project?.driver_config?.port?.toString() || '',
    });

    const { get, processing } = useHttp<object, APIData<Driver>>();

    useEffect(() => {
        if (hasFetchedRef.current) {
            return;
        }

        hasFetchedRef.current = true;

        loadData(get, (data) => {
            if (data.length > 0 && !selectedDatabase) {
                setSelectedDatabase(data[0].id.toString());
                setChangeable({
                    host: data[0].default_host || '',
                    port: data[0].default_port?.toString() || '',
                });
            }
        });
    });

    const selectedDatabaseConfig = useMemo(
        () => databasesMs.find((db) => db.id === selectedDatabase),
        [selectedDatabase, databasesMs],
    );

    useEffect(() => {
        function initialize() {
            if (selectedDatabaseConfig) {
                setChangeable({
                    host: selectedDatabaseConfig.default_host || '',
                    port: selectedDatabaseConfig.default_port?.toString() || '',
                });
            }
        }
        initialize();
    }, [selectedDatabaseConfig]);

    const handleValueChange = (value: string) => {
        setSelectedDatabase(value);
        onDriverChanged?.(value);
    };

    return (
        <div className="flex flex-col gap-4">
            {processing ? (
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
                        defaultValue={selectedDatabaseConfig?.id}
                    />
                    <RadioGroup
                        value={selectedDatabase}
                        onValueChange={handleValueChange}
                        className="flex gap-2"
                        defaultValue={
                            project?.driver_config?.driver_id?.toString() || ''
                        }
                    >
                        {databasesMs.map((db) => (
                            <FieldLabel key={db.id} htmlFor={db.id}>
                                <Field orientation="horizontal">
                                    <FieldContent>
                                        <FieldTitle className="flex flex-col items-start">
                                            <DatabaseIconHelper
                                                icon={db.icon}
                                                className="size-8"
                                            />
                                            <span className="hidden text-sm font-semibold sm:inline">
                                                {db.name}
                                            </span>
                                        </FieldTitle>
                                    </FieldContent>
                                    <RadioGroupItem
                                        value={db.id.toString()}
                                        id={db.id}
                                    />
                                </Field>
                            </FieldLabel>
                        ))}
                    </RadioGroup>
                </>
            )}
            {selectedDatabaseConfig?.id !== 'sqlite' && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="col-span-1 flex flex-col gap-4 sm:col-span-2 sm:flex-row">
                        <Input
                            label="Hôte"
                            placeholder="Ex: localhost"
                            className="flex-2"
                            name="driver_config.host"
                            value={changeable.host}
                            error={errors['driver_config.host'] || errors.host}
                            onChange={(e) =>
                                setChangeable((prev) => ({
                                    ...prev,
                                    host: e.target.value,
                                }))
                            }
                        />
                        <Input
                            label="Port"
                            placeholder={`Ex: ${selectedDatabaseConfig?.default_port || ''}`}
                            className="flex-1"
                            name="driver_config.port"
                            value={changeable.port}
                            error={errors['driver_config.port'] || errors.port}
                            type="number"
                            onChange={(e) =>
                                setChangeable((prev) => ({
                                    ...prev,
                                    port: e.target.value,
                                }))
                            }
                        />
                    </div>
                    <Input
                        label="Nom d'utilisateur"
                        placeholder="Ex: root"
                        name="driver_config.username"
                        error={
                            errors['driver_config.username'] || errors.username
                        }
                        defaultValue={project?.driver_config?.username || ''}
                    />
                    <Input
                        label="Mot de passe"
                        placeholder="Ex: motdepasse"
                        type="password"
                        name="driver_config.password"
                        error={
                            errors['driver_config.password'] || errors.password
                        }
                        defaultValue={project?.driver_config?.password || ''}
                    />
                    <Input
                        label="Nom de la base de données"
                        placeholder="Ex: ma_base_de_donnees"
                        className="sm:col-span-2"
                        name="driver_config.database"
                        error={
                            errors['driver_config.database'] || errors.database
                        }
                        defaultValue={project?.driver_config?.database || ''}
                    />
                </div>
            )}

            {selectedDatabaseConfig?.id === DefaultDrivers.SQLITE && (
                <div>
                    <InputFile
                        name="driver_config.database"
                        error={
                            errors['driver_config.database'] || errors.database
                        }
                        targetFileExtensions={['sqlite', 'db']}
                        defaultValue={project?.driver_config?.database || ''}
                    />
                </div>
            )}
        </div>
    );
};

export default DriverConfigForm;
