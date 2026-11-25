import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    DatabaseIcon,
    ComputerIcon,
    EthernetPortIcon,
    UserIcon,
    RefreshCwIcon,
    CopyIcon,
    UnplugIcon,
    FolderIcon,
    FolderOpenIcon,
    CheckIcon,
} from 'lucide-react';
import React, { useCallback } from 'react';
import DatabaseIconHelper from '@/components/database-icon-helper';
import Button from '@/components/form/button';
import Tooltip from '@/components/tooltip';
import { H6, Muted, Small } from '@/components/typograpfy/typography';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    useCopyConfig,
    useDriverConfigTestConnection,
} from '@/hooks/use-driver-config';
import { useOpenProjectFolder } from '@/hooks/use-project';
import { cn } from '@/lib/utils';
import type { DriverConfig } from '@/types/databases';
import type { Project } from '@/types/models/project';

const DatabaseConnectionStatus = () => {
    const { t } = useLaravelReactI18n();

    return (
        <Tooltip value={t('projects.status.connected')}>
            <Badge
                variant="outline"
                className="bg-green-50 p-1.25 text-green-700 dark:bg-green-950 dark:text-green-300"
            >
                <span className="relative flex h-2 w-2">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
            </Badge>
        </Tooltip>
    );
};

type Props = {
    driver_config: DriverConfig;
    path?: string;
    projectId?: Project['id'];
    buttons?: ('open-folder' | 'reconnect' | 'copy' | 'disconnect')[];
    showLabels?: boolean;
    /**
     * Leaves the project: without it, the bar has no disconnect button.
     */
    onDisconnect?: () => void;
    disconnecting?: boolean;
} & React.ComponentProps<'div'>;

export function DatabaseStatusBar({
    driver_config,
    path,
    projectId,
    buttons = ['open-folder', 'reconnect', 'copy', 'disconnect'],
    showLabels = true,
    onDisconnect,
    disconnecting = false,
    className,
}: Props) {
    const { t } = useLaravelReactI18n();
    const { processing, testConnection } = useDriverConfigTestConnection();
    const { copying, handleCopy } = useCopyConfig(driver_config, 5000);
    const { openFolder, processing: openingFolder } =
        useOpenProjectFolder(projectId);

    const handleTestConnection = useCallback(async () => {
        await testConnection(driver_config);
    }, [driver_config, testConnection]);

    return (
        <div
            className={cn(
                'flex w-full animate-in flex-wrap items-center justify-between gap-4 overflow-hidden rounded-lg border-border bg-sidebar p-2 shadow-sm ring-1 ring-sidebar-border',
                className,
            )}
        >
            <div className="flex flex-1 flex-wrap items-center gap-4 overflow-hidden">
                <div className="flex items-center gap-2">
                    <DatabaseIconHelper
                        icon={driver_config.driver?.icon}
                        forDriver={driver_config.driver_id}
                        fill
                        className="size-8"
                    />
                    <H6>
                        {driver_config.driver?.name ||
                            driver_config?.driver_id?.toUpperCase()}
                    </H6>
                </div>
                <DatabaseConnectionStatus />
                <div className="flex flex-1 scrollbar-none items-center gap-4 overflow-x-auto text-xs whitespace-nowrap">
                    {path && (
                        <>
                            <Separator orientation="vertical" />
                            <div className="flex items-center gap-2">
                                <FolderIcon className="size-4 text-muted-foreground" />
                                <Small>{path}</Small>
                            </div>
                        </>
                    )}
                    <Separator orientation="vertical" />
                    {driver_config.driver?.id !== 'sqlite' && (
                        <>
                            <div className="flex items-center gap-2">
                                <ComputerIcon className="size-4 text-muted-foreground" />
                                {showLabels && (
                                    <Muted>{t('projects.status.host')}</Muted>
                                )}
                                <Small>{driver_config.host}</Small>
                            </div>
                            <div className="flex items-center gap-2">
                                <EthernetPortIcon className="size-4 text-muted-foreground" />
                                {showLabels && (
                                    <Muted>{t('projects.status.port')}</Muted>
                                )}
                                <Small>{driver_config.port}</Small>
                            </div>
                            <div className="flex items-center gap-2">
                                <UserIcon className="size-4 text-muted-foreground" />
                                {showLabels && (
                                    <Muted>
                                        {t('projects.status.username')}
                                    </Muted>
                                )}
                                <Small>{driver_config.username}</Small>
                            </div>
                        </>
                    )}
                    <div className="flex items-center gap-2">
                        <DatabaseIcon className="size-4 text-muted-foreground" />
                        {showLabels && (
                            <Muted>{t('projects.status.database')}</Muted>
                        )}
                        <Small>{driver_config.database}</Small>
                    </div>
                </div>
            </div>
            <div className="flex items-center gap-1">
                {buttons.includes('open-folder') && path && projectId && (
                    <Button
                        icon={FolderOpenIcon}
                        size="icon"
                        variant="secondary"
                        tooltip={t('projects.status.open_folder')}
                        onClick={openFolder}
                        processing={openingFolder}
                    />
                )}
                {buttons.includes('reconnect') && (
                    <Button
                        icon={RefreshCwIcon}
                        size="icon"
                        variant="secondary"
                        tooltip={t('projects.status.reconnect')}
                        onClick={handleTestConnection}
                        processing={processing}
                    />
                )}
                {buttons.includes('copy') && (
                    <Button
                        icon={copying ? CheckIcon : CopyIcon}
                        size="icon"
                        variant="secondary"
                        tooltip={
                            copying
                                ? t('projects.status.configuration_copied')
                                : t('projects.status.copy_configuration')
                        }
                        onClick={handleCopy}
                        stateAppearance={copying ? 'success' : undefined}
                    />
                )}
                {buttons.includes('disconnect') && onDisconnect && (
                    <Button
                        icon={UnplugIcon}
                        size="icon"
                        variant="destructive"
                        tooltip={t('projects.status.disconnect')}
                        onClick={onDisconnect}
                        processing={disconnecting}
                    />
                )}
            </div>
        </div>
    );
}
