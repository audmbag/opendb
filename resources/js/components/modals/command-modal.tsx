import { router, useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpenIcon,
    ChevronLeftIcon,
    CopyIcon,
    ExternalLinkIcon,
    FolderCogIcon,
    FolderOpenIcon,
    FolderPlusIcon,
    FoldersIcon,
    InfoIcon,
    KeyboardIcon,
    LanguagesIcon,
    LogOutIcon,
    PaletteIcon,
    PlusCircleIcon,
    RefreshCwIcon,
    SearchIcon,
    Settings2Icon,
    SunMoonIcon,
    UnplugIcon,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { useHotkeys } from 'react-hotkeys-hook';
import { toast } from 'sonner';
import { GitHubIcon } from '@/components/icons/icons';
import SearchConversationModal from '@/components/modals/search-conversation-modal';
import { Frame, FramePanel } from '@/components/reui/frame';
import { Spinner } from '@/components/spinner';
import { APPEARANCES } from '@/components/theme-toggle';
import {
    Command,
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandShortcut,
} from '@/components/ui/command';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { useAppearance } from '@/hooks/use-apparence';
import { COLOR_THEMES, useColorTheme } from '@/hooks/use-color-theme';
import { useCommandModal } from '@/hooks/use-command-modal';
import {
    useCopyConfig,
    useDriverConfigTestConnection,
} from '@/hooks/use-driver-config';
import { LOCALES, useLocale } from '@/hooks/use-locale';
import { usePrivileges } from '@/hooks/use-privileges';
import { useOpenProjectFolder } from '@/hooks/use-project';
import { useCreateProject } from '@/hooks/use-project-modal';
import { useProjectNavigation } from '@/hooks/use-project-navigation';
import {
    useProjectSettingsDialog,
    useProjectSettingsNavItems,
} from '@/hooks/use-project-settings-modal';
import {
    useSettingsDialog,
    useSettingsNavItems,
} from '@/hooks/use-settings-modal';
import { trans } from '@/lib/i18n';
import { DOCUMENTATION_URL, REPOSITORY_URL } from '@/lib/links';
import type { DriverConfig } from '@/types/databases';
import type { APIData } from '@/types/global';
import type { Project } from '@/types/models/project';
import LoginController from '@/wayfinder/actions/App/Http/Controllers/Auth/LoginController';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';
import HomeController from '@/wayfinder/actions/App/Http/Controllers/HomeController';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';

/**
 * The lists of the palette: the commands, then the choices of the commands ending with « ... ».
 */
type PalettePage = 'root' | 'projects' | 'theme' | 'color-theme' | 'language';

type PaletteCommand = {
    id: string;
    label: string;
    icon?: LucideIcon | React.ComponentType<{ className?: string }>;
    /**
     * Shown instead of the icon, like the colors of a theme.
     */
    preview?: React.ReactNode;
    /**
     * What the search matches, the label by default: only set when the label is not unique, since the palette
     * tells its commands apart by it.
     */
    value?: string;
    keywords?: string[];
    shortcut?: string[];
    checked?: boolean;
    external?: boolean;
    /**
     * Runs while the palette stays open, to show another of its lists.
     */
    keepOpen?: boolean;
    action: () => void;
};

type PaletteGroupType = {
    heading: string;
    commands: PaletteCommand[];
    /**
     * Shown only while searching: the sections of the settings would make the list too long otherwise.
     */
    searchOnly?: boolean;
};

const PAGE_PLACEHOLDERS: Record<Exclude<PalettePage, 'root'>, string> = {
    projects: 'commands.pages.projects',
    theme: 'commands.pages.theme',
    'color-theme': 'commands.pages.color_theme',
    language: 'commands.pages.language',
};

const openExternal = (url: string) => window.open(url, '_blank', 'noopener');

const Shortcut = ({ keys }: { keys: string[] }) => (
    <CommandShortcut className="tracking-normal">
        <KbdGroup>
            {keys.map((key) => (
                <Kbd key={key}>{key}</Kbd>
            ))}
        </KbdGroup>
    </CommandShortcut>
);

const PaletteGroup = ({
    heading,
    commands,
    onRun,
}: Omit<PaletteGroupType, 'searchOnly'> & {
    onRun: (command: PaletteCommand) => void;
}) => (
    <CommandGroup heading={heading}>
        {commands.map((command) => (
            <CommandItem
                key={command.id}
                value={command.value ?? command.label}
                keywords={command.keywords}
                data-checked={command.checked}
                onSelect={() => onRun(command)}
            >
                {command.preview ??
                    (command.icon && (
                        <command.icon className="text-muted-foreground" />
                    ))}
                <span className="truncate">{command.label}</span>
                {command.shortcut && <Shortcut keys={command.shortcut} />}
                {command.external && (
                    <CommandShortcut>
                        <ExternalLinkIcon className="size-3.5" />
                    </CommandShortcut>
                )}
            </CommandItem>
        ))}
    </CommandGroup>
);

/**
 * The commands of the open project, needing its connection: only rendered on its pages.
 */
const ProjectGroup = ({
    project,
    driverConfig,
    onRun,
    onSearchConversation,
}: {
    project: Project;
    driverConfig: DriverConfig;
    onRun: (command: PaletteCommand) => void;
    onSearchConversation: () => void;
}) => {
    const { t } = useLaravelReactI18n();
    const { component } = usePage();
    const projectPages = useProjectNavigation();
    const { open: openProjectSettings } = useProjectSettingsDialog();
    const canManageProject = usePrivileges().canOnProject('manage_project');
    const { openFolder } = useOpenProjectFolder(project.id);
    const { testConnection } = useDriverConfigTestConnection();
    const { handleCopy } = useCopyConfig(driverConfig);

    // The shortcuts of the conversations belong to their sidebar, only on the pages of the chat.
    const isChatPage = component.startsWith('projects/conversation/');

    const commands: PaletteCommand[] = [
        {
            id: 'project-new-conversation',
            label: t('commands.new_conversation'),
            icon: PlusCircleIcon,
            shortcut: isChatPage ? ['Alt', 'N'] : undefined,
            action: () =>
                router.visit(ConversationController.home(project.id).url, {
                    viewTransition: true,
                }),
        },
        {
            id: 'project-search-conversation',
            label: t('commands.search_conversation'),
            icon: SearchIcon,
            shortcut: isChatPage ? ['Alt', 'K'] : undefined,
            action: onSearchConversation,
        },
        ...projectPages.map((page) => ({
            id: `project-page-${page.href}`,
            label: page.label,
            icon: page.icon,
            checked: page.isActive,
            action: () => router.visit(page.href),
        })),
        {
            id: 'project-settings',
            label: t('commands.project_settings'),
            icon: FolderCogIcon,
            action: () => openProjectSettings(),
        },
        ...(canManageProject
            ? [
                  {
                      id: 'project-open-folder',
                      label: t('commands.open_folder'),
                      icon: FolderOpenIcon,
                      keywords: [project.path],
                      action: openFolder,
                  },
                  {
                      id: 'project-test-connection',
                      label: t('commands.test_connection'),
                      icon: RefreshCwIcon,
                      action: () => testConnection(driverConfig),
                  },
              ]
            : []),
        {
            id: 'project-copy-connection',
            label: t('commands.copy_connection'),
            icon: CopyIcon,
            action: handleCopy,
        },
        {
            id: 'project-close',
            label: t('commands.close_project'),
            icon: UnplugIcon,
            action: () => router.visit(HomeController.url()),
        },
    ];

    return (
        <PaletteGroup
            heading={project.name}
            commands={commands}
            onRun={onRun}
        />
    );
};

/**
 * Every project of the user, loaded when the list is shown, to open another one.
 */
const ProjectsPage = ({
    onRun,
}: {
    onRun: (command: PaletteCommand) => void;
}) => {
    const { t } = useLaravelReactI18n();
    const {
        props: { project: currentProject },
    } = usePage<{ project?: Project }>();

    const [projects, setProjects] = useState<Project[] | null>(null);

    const projectsRequest = useHttp<null, APIData<Project>>();

    useEffect(() => {
        const failed = () => {
            setProjects([]);
            toast.error(trans('commands.projects_failed'));
        };

        projectsRequest
            .get(ProjectController.index.url(), {
                onSuccess: ({ data }) => setProjects(data),
                onError: failed,
                onHttpException: () => {
                    failed();

                    return false;
                },
            })
            .catch(() => null);
    }, []);

    if (projects === null) {
        return (
            <div className="flex justify-center py-6">
                <Spinner />
            </div>
        );
    }

    return (
        <>
            <CommandEmpty>{t('commands.no_projects')}</CommandEmpty>
            {projects.length > 0 && (
                <PaletteGroup
                    heading={t('commands.groups.projects')}
                    onRun={onRun}
                    commands={projects.map((project) => ({
                        id: `project-${project.id}`,
                        label: project.name,
                        value: `${project.name} ${project.path}`,
                        keywords: [project.description].filter(Boolean),
                        checked: project.id === currentProject?.id,
                        preview: (
                            <img
                                src={`/assets/images/${project.driver_config?.driver_id}.png`}
                                alt=""
                                className="size-4"
                            />
                        ),
                        action: () =>
                            router.visit(
                                ConversationController.home(project.id).url,
                            ),
                    }))}
                />
            )}
        </>
    );
};

const CommandContent = ({
    onClose,
    onSearchConversation,
}: {
    onClose: () => void;
    onSearchConversation: () => void;
}) => {
    const { t } = useLaravelReactI18n();
    const {
        props: { project },
    } = usePage<{ project?: Project }>();

    const [page, setPage] = useState<PalettePage>('root');
    const [search, setSearch] = useState('');

    const { open: openSettings } = useSettingsDialog();
    const settingsSections = useSettingsNavItems();
    const { open: openProjectSettings } = useProjectSettingsDialog();
    const projectSettingsSections = useProjectSettingsNavItems();
    const createProject = useCreateProject();
    const canCreateProject = usePrivileges().can('create_project');
    const { appearance, updateAppearance } = useAppearance();
    const { colorTheme, updateColorTheme } = useColorTheme();
    const { locale, updateLocale } = useLocale();

    const showPage = (target: PalettePage) => {
        setPage(target);
        setSearch('');
    };

    const run = (command: PaletteCommand) => {
        if (!command.keepOpen) {
            onClose();
        }

        command.action();
    };

    const rootGroups: PaletteGroupType[] = [
        {
            heading: t('commands.groups.projects'),
            commands: [
                {
                    id: 'open-project',
                    label: t('commands.open_project'),
                    icon: FoldersIcon,
                    keepOpen: true,
                    action: () => showPage('projects'),
                },
                ...(canCreateProject
                    ? [
                          {
                              id: 'new-project',
                              label: t('commands.new_project'),
                              icon: FolderPlusIcon,
                              shortcut: ['Ctrl', 'Shift', 'N'],
                              action: createProject,
                          },
                      ]
                    : []),
            ],
        },
        {
            heading: t('commands.groups.preferences'),
            commands: [
                {
                    id: 'settings',
                    label: t('commands.settings'),
                    icon: Settings2Icon,
                    shortcut: ['Ctrl', ','],
                    action: () => openSettings(),
                },
                {
                    id: 'shortcuts',
                    label: t('commands.shortcuts'),
                    icon: KeyboardIcon,
                    action: () => openSettings('shortcuts'),
                },
                {
                    id: 'theme',
                    label: t('commands.theme'),
                    icon: SunMoonIcon,
                    keepOpen: true,
                    action: () => showPage('theme'),
                },
                {
                    id: 'color-theme',
                    label: t('commands.color_theme'),
                    icon: PaletteIcon,
                    keepOpen: true,
                    action: () => showPage('color-theme'),
                },
                {
                    id: 'language',
                    label: t('commands.language'),
                    icon: LanguagesIcon,
                    keepOpen: true,
                    action: () => showPage('language'),
                },
            ],
        },
        {
            heading: t('commands.groups.settings'),
            searchOnly: true,
            commands: settingsSections.map((section) => ({
                id: `settings-${section.value}`,
                value: `${section.name} ${t('commands.groups.settings')}`,
                label: section.name,
                icon: section.icon,
                action: () => openSettings(section.value),
            })),
        },
        {
            heading: t('commands.groups.project_settings'),
            searchOnly: true,
            commands: project
                ? projectSettingsSections.map((section) => ({
                      id: `project-settings-${section.value}`,
                      value: `${section.name} ${t('commands.groups.project_settings')}`,
                      label: section.name,
                      icon: section.icon,
                      action: () => openProjectSettings(section.value),
                  }))
                : [],
        },
        {
            heading: t('commands.groups.help'),
            commands: [
                {
                    id: 'documentation',
                    label: t('commands.documentation'),
                    icon: BookOpenIcon,
                    external: true,
                    action: () => openExternal(DOCUMENTATION_URL),
                },
                {
                    id: 'repository',
                    label: t('commands.repository'),
                    icon: GitHubIcon,
                    external: true,
                    action: () => openExternal(REPOSITORY_URL),
                },
                {
                    id: 'about',
                    label: t('commands.about'),
                    icon: InfoIcon,
                    action: () => openSettings('about'),
                },
            ],
        },
        {
            heading: t('commands.groups.account'),
            commands: [
                {
                    id: 'logout',
                    label: t('commands.logout'),
                    icon: LogOutIcon,
                    action: () => router.post(LoginController.logout()),
                },
            ],
        },
    ];

    const choiceGroups: Partial<Record<PalettePage, PaletteGroupType>> = {
        theme: {
            heading: t('settings.general.theme'),
            commands: APPEARANCES.map((theme) => ({
                id: `theme-${theme.value}`,
                label: t(theme.label),
                icon: theme.icon,
                checked: appearance === theme.value,
                action: () => updateAppearance(theme.value),
            })),
        },
        'color-theme': {
            heading: t('settings.general.color_theme'),
            commands: COLOR_THEMES.map((theme) => ({
                id: `color-theme-${theme.value}`,
                label: t(theme.label),
                checked: colorTheme === theme.value,
                preview: (
                    <span
                        aria-hidden="true"
                        data-theme={theme.value}
                        className="size-4 shrink-0 rounded-full bg-primary ring-1 ring-border"
                    />
                ),
                action: () => updateColorTheme(theme.value),
            })),
        },
        language: {
            heading: t('settings.general.language'),
            commands: LOCALES.map((language) => ({
                id: `language-${language.value}`,
                label: language.label,
                icon: LanguagesIcon,
                checked: locale === language.value,
                action: () => updateLocale(language.value),
            })),
        },
    };

    const handleKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Backspace' && search === '' && page !== 'root') {
            event.preventDefault();
            showPage('root');
        }
    };

    const isSearching = search.trim() !== '';
    const choiceGroup = choiceGroups[page];

    return (
        <Command className="p-0" onKeyDown={handleKeyDown}>
            <Frame className="w-full">
                <FramePanel className="p-0">
                    {page !== 'root' && (
                        <button
                            type="button"
                            onClick={() => showPage('root')}
                            className="mt-2 ml-2 inline-flex items-center gap-1 rounded-md px-1 py-0.5 text-xs text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <ChevronLeftIcon className="size-3.5" />
                            {t('commands.back')}
                            <Kbd className="ml-1">⌫</Kbd>
                        </button>
                    )}
                    <CommandInput
                        placeholder={t(
                            page === 'root'
                                ? 'commands.placeholder'
                                : PAGE_PLACEHOLDERS[page],
                        )}
                        value={search}
                        onValueChange={setSearch}
                    />
                    <CommandList className="max-h-96">
                        {page === 'projects' ? (
                            <ProjectsPage onRun={run} />
                        ) : (
                            <>
                                <CommandEmpty>
                                    {t('commands.empty')}
                                </CommandEmpty>
                                {page === 'root' && project?.driver_config && (
                                    <ProjectGroup
                                        project={project}
                                        driverConfig={project.driver_config}
                                        onRun={run}
                                        onSearchConversation={
                                            onSearchConversation
                                        }
                                    />
                                )}
                                {(page === 'root'
                                    ? rootGroups
                                    : choiceGroup
                                      ? [choiceGroup]
                                      : []
                                )
                                    .filter(
                                        (group) =>
                                            group.commands.length > 0 &&
                                            (!group.searchOnly || isSearching),
                                    )
                                    .map((group) => (
                                        <PaletteGroup
                                            key={group.heading}
                                            heading={group.heading}
                                            commands={group.commands}
                                            onRun={run}
                                        />
                                    ))}
                            </>
                        )}
                    </CommandList>
                </FramePanel>
            </Frame>
        </Command>
    );
};

/**
 * The command palette, opened with Ctrl+K on every page: the commands of the open project, the other projects, the
 * preferences of the interface and the settings. It also holds the shortcut creating a project, which works everywhere.
 */
export default function CommandModal() {
    const { t } = useLaravelReactI18n();
    const { open, setOpen } = useCommandModal();
    const {
        props: { project },
    } = usePage<{ project?: Project }>();

    const [searchConversationOpen, setSearchConversationOpen] = useState(false);

    const createProject = useCreateProject();

    useHotkeys(
        'mod+shift+n',
        createProject,
        { enableOnFormTags: true, preventDefault: true },
        [createProject],
    );

    return (
        <>
            <CommandDialog
                open={open}
                onOpenChange={setOpen}
                title={t('commands.title')}
                description={t('commands.description')}
                className="sm:max-w-xl"
            >
                <CommandContent
                    onClose={() => setOpen(false)}
                    onSearchConversation={() => setSearchConversationOpen(true)}
                />
            </CommandDialog>
            {project && (
                <SearchConversationModal
                    open={searchConversationOpen}
                    onOpenChange={setSearchConversationOpen}
                />
            )}
        </>
    );
}
