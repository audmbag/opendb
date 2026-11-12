import { useHttp } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ClipboardCopyIcon,
    KeyRoundIcon,
    LoaderIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import type React from 'react';
import { Fragment, useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import {
    CodeBlock,
    CodeBlockCopyButton,
    CodeBlockHeader,
    CodeBlockTitle,
} from '@/components/code-block';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import { Separator } from '@/components/ui/separator';
import { dateFormatter } from '@/lib/intl';
import type { APIData } from '@/types/global';
import type { IssuedMcpToken, McpToken } from '@/types/models/mcp';
import type { Project } from '@/types/models/project';
import McpTokenController from '@/wayfinder/actions/App/Http/Controllers/McpTokenController';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';

/**
 * The configuration of the MCP client for a token just created: the token in clear is never sent again.
 */
const IssuedToken = ({
    issued,
    onDone,
}: {
    issued: IssuedMcpToken;
    onDone: () => void;
}) => {
    const { t } = useLaravelReactI18n();
    const config = JSON.stringify(issued.config, null, 2);

    /**
     * The token is never shown again: the panel only closes once the configuration is in the clipboard.
     */
    const copyAndClose = () =>
        navigator.clipboard
            .writeText(config)
            .then(() => {
                toast.success(t('settings.mcp.copied'), { duration: 1500 });
                onDone();
            })
            .catch(() => toast.error(t('settings.mcp.copy_failed')));

    return (
        <div className="flex flex-col gap-3 p-3">
            <div className="flex flex-col gap-1">
                <span className="text-sm font-medium">
                    {t('settings.mcp.created_title', {
                        name: issued.data.name,
                    })}
                </span>
                <span className="text-xs text-muted-foreground">
                    {t('settings.mcp.created_description')}
                </span>
            </div>
            <CodeBlock
                code={config}
                language="json"
                className="[&_pre]:max-h-72 [&_pre]:overflow-auto [&_pre]:text-xs [&_pre_code]:text-xs"
            >
                <CodeBlockHeader>
                    <CodeBlockTitle>mcp.json</CodeBlockTitle>
                    <CodeBlockCopyButton
                        className="size-7"
                        aria-label={t('settings.mcp.copy')}
                        title={t('settings.mcp.copy')}
                    />
                </CodeBlockHeader>
            </CodeBlock>
            <div className="flex justify-end">
                <Button
                    size="sm"
                    icon={ClipboardCopyIcon}
                    onClick={copyAndClose}
                >
                    {t('settings.mcp.done')}
                </Button>
            </div>
        </div>
    );
};

export default function McpSettings() {
    const { t } = useLaravelReactI18n();
    const [tokens, setTokens] = useState<McpToken[]>([]);
    const [projects, setProjects] = useState<Project[]>([]);
    const [issued, setIssued] = useState<IssuedMcpToken | null>(null);
    const [revokingId, setRevokingId] = useState<string | undefined>();

    const loader = useHttp<null, APIData<McpToken>>();
    const projectsLoader = useHttp<null, APIData<Project>>();
    const remover = useHttp<null, { message: string }>();
    const form = useHttp<{ name: string; project_id: string }, IssuedMcpToken>({
        name: '',
        project_id: '',
    });

    const load = useCallback(() => {
        loader.get(McpTokenController.index.url(), {
            onSuccess: (response) => setTokens(response.data),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        load();
        projectsLoader.get(ProjectController.index.url(), {
            onSuccess: (response) => setProjects(response.data),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [load]);

    const create = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(McpTokenController.store.url(), {
            onSuccess: (response) => {
                setIssued(response);
                form.reset();
                load();
            },
        });
    };

    const revoke = (token: McpToken) => {
        setRevokingId(token.id);

        remover.delete(McpTokenController.destroy.url(token.id), {
            onSuccess: (response) => {
                setTokens((current) =>
                    current.filter(({ id }) => id !== token.id),
                );
                toast.success(response.message);
            },
            onFinish: () => setRevokingId(undefined),
        });
    };

    const lastUsed = (token: McpToken) =>
        token.last_used_at
            ? t('settings.mcp.last_used', {
                  date: dateFormatter({
                      dateStyle: 'medium',
                      timeStyle: 'short',
                  }).format(new Date(token.last_used_at)),
              })
            : t('settings.mcp.never_used');

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <CardFrame
                title={t('settings.mcp.title')}
                description={t('settings.mcp.description')}
                innerPadding={false}
            >
                {issued ? (
                    <IssuedToken
                        issued={issued}
                        onDone={() => setIssued(null)}
                    />
                ) : (
                    <form
                        className="flex items-start gap-2 p-3"
                        onSubmit={create}
                    >
                        <Input
                            name="name"
                            placeholder={t('settings.mcp.name_placeholder')}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            error={form.errors.name}
                        />
                        <Select
                            name="project_id"
                            className="min-w-48"
                            optionsLabel={t('settings.mcp.project_placeholder')}
                            items={projects.map((project) => ({
                                label: project.name,
                                value: project.id,
                            }))}
                            value={form.data.project_id}
                            onValueChange={(value) =>
                                form.setData('project_id', String(value ?? ''))
                            }
                            error={form.errors.project_id}
                        />
                        <Button
                            type="submit"
                            icon={PlusIcon}
                            processing={form.processing}
                        >
                            {t('settings.mcp.create')}
                        </Button>
                    </form>
                )}
                <Separator />
                {loader.processing && tokens.length === 0 ? (
                    <div className="flex justify-center py-4">
                        <LoaderIcon className="animate-spin" />
                    </div>
                ) : tokens.length === 0 ? (
                    <EmptyState
                        icon={KeyRoundIcon}
                        title={t('settings.mcp.no_tokens')}
                        description={t('settings.mcp.no_tokens_description')}
                    />
                ) : (
                    tokens.map((token, index) => (
                        <Fragment key={token.id}>
                            <div className="flex items-center justify-between gap-2 px-3 py-2">
                                <div className="flex min-w-0 flex-col">
                                    <span className="truncate text-sm">
                                        {token.name}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {token.project?.name} ·{' '}
                                        {lastUsed(token)}
                                    </span>
                                </div>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    icon={Trash2Icon}
                                    tooltip={t('settings.mcp.revoke')}
                                    onClick={() => revoke(token)}
                                    processing={revokingId === token.id}
                                    confirmation={{
                                        title: t('settings.mcp.revoke_token', {
                                            name: token.name,
                                        }),
                                        description: t(
                                            'settings.mcp.revoke_description',
                                        ),
                                        icon: Trash2Icon,
                                    }}
                                />
                            </div>
                            {index !== tokens.length - 1 && <Separator />}
                        </Fragment>
                    ))
                )}
            </CardFrame>
        </div>
    );
}
