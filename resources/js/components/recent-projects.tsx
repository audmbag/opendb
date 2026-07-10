import { DatabaseIcon, ExternalLinkIcon, FolderIcon, FolderOpenIcon, FolderPlusIcon, icons, TrashIcon } from "lucide-react";
import { Frame, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import Button from "@/components/form/button";
import { H2, H4, H6, Muted, Small } from "./typograpfy/typography";
import { EmptyState } from "@/components/empty-state";
import { ButtonGroup, ButtonGroupSeparator } from "./ui/button-group";
import { buttonVariants } from "./ui/button";
import { show } from "@/wayfinder/actions/App/Http/Controllers/ProjectController";
import CreateProjectModal from "./create-project-modal";
import { Link } from "@inertiajs/react";
import DatabaseIconHelper from "./database-icon-helper";
import { RecentProject } from "@/types/models/recent-project";

const recentProjects: RecentProject[] = [
    {
        id: 'zc1h1cDYeQO2vj26yuFyjr8kZYl13BJG',
        name: 'Commerce App',
        description: 'Une application de commerce électronique pour la vente de produits en ligne.',
        createdAt: "Il y'a 1h",
        databaseConnexion: "PostgreSQL",
        path: "/home/audmbag/projects/ecommerce-app",
        database: {
            type: 'postgresql',
            label: 'PostgreSQL',
            host: 'localhost',
            port: 5432,
            databaseName: 'ecommerce_db',
        }
    },
    {
        id: 'KQDpfBwdVqipWSiHLtUI1hXcFAVJIsl0',
        name: 'Blog Platform',
        description: 'Une plateforme de blogging pour partager des articles et des idées.',
        createdAt: "Il y'a 2h",
        databaseConnexion: "MySQL",
        path: "/home/audmbag/projects/blog-platform",
        database: {
            type: 'mysql',
            label: 'MySQL',
            host: 'localhost',
            port: 3306,
            databaseName: 'blog_db',
        }
    },
    {
        id: 'IceG4ajJMrNglslbi31lbMzxnrazKrIT',
        name: 'Task Manager',
        description: 'Une application de gestion de tâches pour organiser et suivre les projets.',
        createdAt: "Il y'a 2 jours",
        databaseConnexion: "SQLite",
        path: "/home/audmbag/projects/task-manager",
        database: {
            type: 'sqlite',
            label: 'SQLite',
            databasePath: '/home/audmbag/projects/task-manager/task_manager.db',
            databaseName: 'task_manager.db',
        }
    },
];

export function RecentProjects() {
    return <>
        <Frame className="w-full">
            {!!recentProjects.length && <FrameHeader className="flex justify-between items-center flex-row">
                <FrameTitle>Projet récents</FrameTitle>
                <div>
                    <CreateProjectModal />
                </div>
            </FrameHeader>}
            {recentProjects.length ? recentProjects.map((project) => (
                <FramePanel key={project.id} className="flex flex-col sm:flex-row gap-4 justify-between bg-card dark:bg-muted">
                    <div>
                        <div className="flex items-center gap-2 pb-2">
                            <H6>{project.name}</H6>
                            <Muted className="text-xs">•</Muted>
                            <Muted className="text-xs">{project.path}</Muted>
                        </div>
                        <Muted>{project.description}</Muted>
                        <div className="flex items-center gap-2 pt-2">
                            <Muted className="text-xs">{project.createdAt}</Muted>
                            <Muted className="text-xs">•</Muted>
                            <div className="flex gap-1 items-center">
                                <DatabaseIconHelper databaseType={project.database.type} />
                                <Muted className="text-xs">{project.database.label}</Muted>
                                <Muted className="text-xs">•</Muted>
                                <Muted className="text-xs">
                                    {project.database.type === 'sqlite' ? project.database.databasePath : `${project.database.host}:${project.database.port}`}
                                </Muted>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <ButtonGroup>
                            <Link href={show(project.id).url} className={buttonVariants({ variant: "outline", size: "sm" })}>
                                <ExternalLinkIcon />
                                Ouvrir
                            </Link>
                            <Button variant="outline" size="icon-sm" icon={TrashIcon} tooltip={"Rétirer des projets récents"} />
                        </ButtonGroup>
                    </div>
                </FramePanel>
            )) : <FramePanel>
                <EmptyState
                    title="Aucun projet récent trouvé"
                    description="Vous n'avez pas encore ouvert de projet. Créez un nouveau projet pour commencer."
                    icon={FolderOpenIcon}
                    actions={[
                        {
                            label: "Créer un projet",
                            onClick: () => {
                                console.log("Créer un projet");
                            },
                            icon: FolderPlusIcon,
                        }
                    ]}
                />
            </FramePanel>}
        </Frame>
    </>
}