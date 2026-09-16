import { ChevronRightIcon, SquareTerminalIcon } from 'lucide-react';
import type { FormDataErrors } from 'node_modules/@inertiajs/core/types/types';
import { useState } from 'react';
import Input from '@/components/form/input';
import InputFile from '@/components/form/input-file';
import DatabaseDriverSelector from '@/components/project/database-driver-selector';
import SSHConfigForm from '@/components/project/ssh-config-form';
import { buttonVariants } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { Driver } from '@/types/databases';
import { DefaultDrivers } from '@/types/databases';
import type { Project } from '@/types/models/project';

type Props = {
    errors?: FormDataErrors<Project | Project['driver_config']>;
    onDriverChanged?: (driver: string) => void;
    project?: Project;
};

const DriverConfigForm = ({ errors, onDriverChanged, project }: Props) => {

    const [selectedDatabase, setSelectedDatabase] = useState<string>(
        project?.driver_config?.driver_id?.toString() || DefaultDrivers.MYSQL,
    );

    const handleValueChange = (value: Driver) => {
        setSelectedDatabase(value.id);
        onDriverChanged?.(value.id);
    };

    return (
        <div className="flex flex-col gap-4">
            <DatabaseDriverSelector
                project={project}
                selectedDriver={selectedDatabase}
                onDriverChange={handleValueChange}
            />
            {selectedDatabase !== 'sqlite' && (
                <>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="col-span-1 flex flex-col gap-4 sm:col-span-2 sm:flex-row">
                            <Input
                                label="Hôte"
                                placeholder="Ex: localhost"
                                className="flex-2"
                                name="driver_config.host"
                                value={project?.driver_config?.host}
                                error={
                                    errors?.['driver_config.host'] || errors?.host
                                }
                            />
                            <Input
                                label="Port"
                                placeholder={'Ex: 3306'}
                                className="flex-1"
                                name="driver_config.port"
                                value={project?.driver_config?.port}
                                error={
                                    errors?.['driver_config.port'] || errors?.port
                                }
                                type="number"
                            />
                        </div>
                        <Input
                            label="Nom d'utilisateur"
                            placeholder="Ex: root"
                            name="driver_config.username"
                            error={
                                errors?.['driver_config.username'] ||
                                errors?.username
                            }
                            defaultValue={
                                project?.driver_config?.username || ''
                            }
                        />
                        <Input
                            label="Mot de passe"
                            placeholder="Ex: motdepasse"
                            type="password"
                            name="driver_config.password"
                            error={
                                errors?.['driver_config.password'] ||
                                errors?.password
                            }
                            defaultValue={
                                project?.driver_config?.password || ''
                            }
                        />
                        <Input
                            label="Nom de la base de données"
                            placeholder="Ex: ma_base_de_donnees"
                            className="sm:col-span-2"
                            name="driver_config.database"
                            error={
                                errors?.['driver_config.database'] ||
                                errors?.database
                            }
                            defaultValue={
                                project?.driver_config?.database || ''
                            }
                        />
                    </div>
                    <Collapsible defaultOpen={!!(project && project.driver_config.ssh)} className="rounded-md data-[state=open]:bg-muted">
                        <CollapsibleTrigger
                            className={buttonVariants({
                                variant: 'secondary',
                                className: 'group w-full justify-start',
                            })}
                        >
                            <SquareTerminalIcon />
                            Utiliser un tunnel SSH
                            <ChevronRightIcon className="ml-auto size-4 transition-transform group-data-[state=open]:rotate-90" />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="p-2.5 pt-0">
                            <SSHConfigForm project={project} />
                        </CollapsibleContent>
                    </Collapsible>
                </>
            )}

            {selectedDatabase === DefaultDrivers.SQLITE && (
                <div>
                    <InputFile
                        name="driver_config.database"
                        error={
                            errors?.['driver_config.database'] || errors?.database
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
