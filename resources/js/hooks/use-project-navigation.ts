import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { LucideIcon } from 'lucide-react';
import {
    Code2Icon,
    GitCompareArrowsIcon,
    MessageSquareTextIcon,
    Table2Icon,
} from 'lucide-react';
import { useMemo } from 'react';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';
import ErdController from '@/wayfinder/actions/App/Http/Controllers/Erd/ErdController';
import SqlEditorController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlEditorController';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';

export type ProjectPage = {
    label: string;
    icon: LucideIcon;
    href: string;
    isActive: boolean;
};

/**
 * The pages of the current project, shown in its sidebar and in the command palette. Empty outside of a project.
 */
export function useProjectNavigation(): ProjectPage[] {
    const { t } = useLaravelReactI18n();
    const {
        url,
        props: { project },
    } = usePage<{ project?: Project }>();

    const currentUrl = url.split('?')[0];

    return useMemo(() => {
        if (!project) {
            return [];
        }

        const chatUrl = ConversationController.home(project.id).url;
        const tablesUrl = TableBrowserController.index.url({ project: project.id });
        const sqlEditorUrl = SqlEditorController.index.url({ project: project.id });
        const erdUrl = ErdController.index.url({ project: project.id });

        return [
            {
                label: t('projects.menu.chat'),
                icon: MessageSquareTextIcon,
                href: chatUrl,
                isActive: currentUrl === chatUrl || currentUrl.startsWith(`${chatUrl}/chat/`),
            },
            {
                label: t('projects.menu.tables'),
                icon: Table2Icon,
                href: tablesUrl,
                isActive: currentUrl === tablesUrl || currentUrl.startsWith(`${tablesUrl}/`),
            },
            {
                label: t('projects.menu.sql_editor'),
                icon: Code2Icon,
                href: sqlEditorUrl,
                isActive: currentUrl === sqlEditorUrl,
            },
            {
                label: t('projects.menu.erd'),
                icon: GitCompareArrowsIcon,
                href: erdUrl,
                isActive: currentUrl === erdUrl,
            },
        ];
    }, [project, currentUrl, t]);
}
