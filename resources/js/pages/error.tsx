import { Head, Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowLeftIcon, HouseIcon, RotateCwIcon, ShuffleIcon, TerminalIcon } from 'lucide-react';
import { MotionConfig } from 'motion/react';
import React, { useState } from 'react';
import CardFrame from '@/components/card-frame';
import AsciiStatus from '@/components/error-page/ascii-status';
import DatabaseMascot from '@/components/error-page/database-mascot';
import type { MascotMood } from '@/components/error-page/database-mascot';
import QueryTerminal from '@/components/error-page/query-terminal';
import Button from '@/components/form/button';
import SocialButtons from '@/components/social-buttons';
import { H2, Muted } from '@/components/typograpfy/typography';
import { buttonVariants } from '@/components/ui/button';
import HomeController from '@/wayfinder/actions/App/Http/Controllers/HomeController';

type ErrorStory = {
    key: string;
    mood: MascotMood;
    query: (path: string) => string;
    excuses: string[];
    failed: boolean;
    reloadable: boolean;
};

/**
 * Each error tells its story as a query run in the console of opendb, answered by the database.
 */
const ERROR_PAGES: Record<number, ErrorStory> = {
    403: {
        key: 'forbidden',
        mood: 'stern',
        query: (path) => `GRANT SELECT ON '${path}' TO CURRENT_USER;`,
        excuses: ['bouncer', 'grant', 'root', 'velvet'],
        failed: true,
        reloadable: false,
    },
    404: {
        key: 'not_found',
        mood: 'curious',
        query: (path) => `SELECT * FROM pages WHERE path = '${path}';`,
        excuses: ['dropped', 'join', 'index', 'null', 'vacation'],
        failed: false,
        reloadable: false,
    },
    419: {
        key: 'page_expired',
        mood: 'sleepy',
        query: () => 'COMMIT;',
        excuses: ['coffee', 'timeout', 'savepoint'],
        failed: true,
        reloadable: true,
    },
    429: {
        key: 'too_many_requests',
        mood: 'flustered',
        query: () => 'SELECT 1; SELECT 1; SELECT 1; SELECT 1; SELECT 1;',
        excuses: ['breath', 'queue', 'limit'],
        failed: true,
        reloadable: true,
    },
    500: {
        key: 'server_error',
        mood: 'dizzy',
        query: () => "UPDATE server SET mood = 'happy' WHERE id = 1;",
        excuses: ['deadlock', 'semicolon', 'friday', 'cosmic'],
        failed: true,
        reloadable: true,
    },
    503: {
        key: 'service_unavailable',
        mood: 'sleepy',
        query: () => 'VACUUM FULL ANALYZE opendb;',
        excuses: ['vacuum', 'reindex', 'nap'],
        failed: false,
        reloadable: true,
    },
};

const MAX_PATH_LENGTH = 48;

/**
 * The path of the page as an SQL string: its quotes doubled, and shortened when it is too long to read.
 */
const sqlPath = (url: string): string => {
    const rawPath = url.split(/[?#]/)[0];
    let path = rawPath;

    try {
        path = decodeURI(rawPath);
    } catch {
        // A malformed address is shown as it was typed.
    }

    const shortened = path.length > MAX_PATH_LENGTH ? `${path.slice(0, MAX_PATH_LENGTH - 1)}…` : path;

    return shortened.replaceAll("'", "''");
};

/**
 * The first excuse of an address, always the same for the server and the browser to render the same page.
 */
const firstExcuse = (url: string, count: number): number =>
    [...url].reduce((hash, character) => (hash * 31 + character.charCodeAt(0)) >>> 0, 7) % count;

const goBack = (): void => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.assign(HomeController.url());
    }
};

type Props = {
    status: number;
};

/**
 * Every error starts its story again, even when the interface goes from one error page to another.
 */
export default function ErrorPage({ status }: Props) {
    const { url } = usePage();

    return <ErrorStoryView key={`${status} ${url}`} status={status} url={url} />;
}

function ErrorStoryView({ status, url }: Props & { url: string }) {
    const { t } = useLaravelReactI18n();
    const page = ERROR_PAGES[status] ?? ERROR_PAGES[500];
    const [excuseIndex, setExcuseIndex] = useState(() => firstExcuse(url, page.excuses.length));
    const prefix = `errors.pages.${page.key}`;
    const title = t(`${prefix}.title`);

    const nextExcuse = (): void => setExcuseIndex((index) => (index + 1) % page.excuses.length);

    return (
        <MotionConfig reducedMotion="user">
            <Head title={title} />
            <div className="flex min-h-svh flex-col p-4">
                <main className="flex flex-1 flex-col items-center justify-center gap-8 py-8">
                    <AsciiStatus status={status} />

                    <div className="flex max-w-md flex-col items-center gap-2 text-center">
                        <Muted className="font-mono text-xs">{t('errors.code', { status })}</Muted>
                        <H2 className="border-none pb-0 font-semibold">{title}</H2>
                        <Muted className="text-balance">{t(`${prefix}.description`)}</Muted>
                    </div>

                    <div className="relative w-full max-w-xl pt-20">
                        <DatabaseMascot
                            mood={page.mood}
                            label={t('errors.mascot')}
                            onPoke={nextExcuse}
                            className="absolute top-0 right-6 z-10 size-24 translate-y-2.5"
                        />
                        <CardFrame
                            title="opendb - console"
                            icon={TerminalIcon}
                            className="w-full"
                            actions={
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    icon={ShuffleIcon}
                                    tooltip={t('errors.another_excuse')}
                                    aria-label={t('errors.another_excuse')}
                                    onClick={nextExcuse}
                                    className="mr-24"
                                />
                            }
                        >
                            <QueryTerminal
                                className="min-h-28"
                                query={page.query(sqlPath(url))}
                                result={t(`${prefix}.result`)}
                                excuse={t(`${prefix}.excuses.${page.excuses[excuseIndex]}`)}
                                failed={page.failed}
                            />
                        </CardFrame>
                    </div>

                    <div className="flex flex-wrap justify-center gap-2">
                        <Button variant="outline" icon={ArrowLeftIcon} onClick={goBack}>
                            {t('errors.back')}
                        </Button>
                        {page.reloadable && (
                            <Button variant="outline" icon={RotateCwIcon} onClick={() => window.location.reload()}>
                                {t('errors.reload')}
                            </Button>
                        )}
                        <Link href={HomeController()} className={buttonVariants()}>
                            <HouseIcon />
                            {t('errors.home')}
                        </Link>
                    </div>
                </main>
                <footer className="pt-4">
                    <SocialButtons />
                </footer>
            </div>
        </MotionConfig>
    );
}
