import {
    DatabaseIcon,
    ComputerIcon,
    EthernetPortIcon,
    UserIcon,
    RefreshCwIcon,
    CopyIcon,
    SquarePenIcon,
    UnplugIcon,
    FolderIcon, CheckIcon,
} from 'lucide-react';
import React, { useCallback, useState } from 'react';
import DatabaseIconHelper from '@/components/database-icon-helper';
import Button from '@/components/form/button';
import Tooltip from '@/components/tooltip';
import { H6, Muted, Small } from '@/components/typograpfy/typography';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCopyConfig, useDriverConfigTestConnection } from '@/hooks/use-driver-config';
import type { Project } from '@/types/models/project';
import { router } from '@inertiajs/react';
import HomeController from '@/wayfinder/actions/App/Http/Controllers/HomeController';
import EditProjectInfoModal from '@/components/edit-project-info-modal';

const DatabaseConnectionStatus = () => {
    return (
        <Tooltip value={"Connecté"}>
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

export function DatabaseStatusBar({ project }: { project: Project }) {

    const { processing, testConnection } = useDriverConfigTestConnection();
    const { copying, handleCopy } = useCopyConfig(project.driver_config, 5000);

    const [isEditModalOpen, setIsEditModalOpen] = useState(false);

    const handleTestConnection = useCallback(() => {
        testConnection(project.driver_config);
    }, [project.driver_config, testConnection]);

    const handleDisconnect = useCallback(() => {
        router.visit(HomeController.url())
    }, []);

    const openEditModal = useCallback(() => {
        setIsEditModalOpen(true);
    }, []);


    return (
        <div className="ml-0.5 flex w-full animate-in flex-wrap items-center justify-between gap-4 overflow-hidden rounded-lg border-border bg-sidebar p-2 shadow-sm ring-1 ring-sidebar-border">
            <EditProjectInfoModal
                open={isEditModalOpen}
                onOpenChange={setIsEditModalOpen}
                part={"config"}
                project={project}
            />
            <div className="flex flex-1 flex-wrap items-center gap-4 overflow-hidden">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="flex md:hidden" />
                    <DatabaseIconHelper
                        icon={project.driver_config.driver?.icon}
                        fill
                        className="size-8"
                    />
                    <H6>{project.driver_config.driver?.name}</H6>
                </div>
                <DatabaseConnectionStatus />
                <div className="flex flex-1 scrollbar-none items-center gap-4 overflow-x-auto text-xs whitespace-nowrap">
                    <Separator orientation="vertical" />
                    <div className="flex items-center gap-2">
                        <FolderIcon className="size-4" />
                        <Small>{project.path}</Small>
                    </div>
                    <Separator orientation="vertical" />
                    {project.driver_config.driver?.id !== 'sqlite' && (
                        <>
                            <div className="flex items-center gap-2">
                                <ComputerIcon className="size-4" />
                                <Muted>Host</Muted>
                                <Small>{project.driver_config.host}</Small>
                            </div>
                            <div className="flex items-center gap-2">
                                <EthernetPortIcon className="size-4" />
                                <Muted>Port</Muted>
                                <Small>{project.driver_config.port}</Small>
                            </div>
                            <div className="flex items-center gap-2">
                                <UserIcon className="size-4" />
                                <Muted>Username</Muted>
                                <Small>{project.driver_config.username}</Small>
                            </div>
                        </>
                    )}
                    <div className="flex items-center gap-2">
                        <DatabaseIcon className="size-4" />
                        <Muted>Base de données</Muted>
                        <Small>{project.driver_config.database}</Small>
                    </div>
                </div>
            </div>
            <div className="flex items-center gap-1">
                <Button
                    icon={RefreshCwIcon}
                    size="icon"
                    variant="secondary"
                    tooltip="Réconnecter"
                    onClick={handleTestConnection}
                    processing={processing}
                />
                <Button
                    icon={copying ? CheckIcon : CopyIcon}
                    size="icon"
                    variant="secondary"
                    tooltip={
                        copying
                            ? 'Configuration coprié'
                            : 'Copier la configuration'
                    }
                    onClick={handleCopy}
                    stateAppearance={copying ? 'success' : undefined}
                />
                <Button
                    icon={SquarePenIcon}
                    size="icon"
                    variant="secondary"
                    tooltip="Modifier la configuration"
                    onClick={openEditModal}
                />
                <Button
                    icon={UnplugIcon}
                    size="icon"
                    variant="destructive"
                    tooltip="Déconnecter"
                    onClick={handleDisconnect}
                />
            </div>
        </div>
    );
}
