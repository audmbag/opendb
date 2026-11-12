import { useLaravelReactI18n } from 'laravel-react-i18n';
import React, {
    createContext,
    Fragment,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import Input from '@/components/form/input';
import { Badge } from '@/components/reui/badge';
import type {
    SmallSidebarNavGroupItem,
    SmallSidebarNavItem,
    SmallSidebarNavItemValue,
} from '@/components/sm-side-bar/types';
import { Muted } from '@/components/typograpfy/typography';
import { Separator } from '@/components/ui/separator';
import { SidebarMenuButton, SidebarProvider } from '@/components/ui/sidebar';
import { useValueAsRef } from '@/hooks/use-value-as-ref';
import { cn } from '@/lib/utils';

/**
 * A page opened inside the current one, like a user from the list of the users: it follows the page in the
 * breadcrumb, which goes back to the page.
 */
export type SmallSideBarSubPage = {
    label: string;
    onBack: () => void;
};


const SmallSideBarContext = createContext<{
    pages: SmallSidebarNavGroupItem[];
    currentPage: string;
    currentPageData?: SmallSidebarNavItem;
    setCurrentPage: (value: SmallSidebarNavItemValue) => void;
    subPage: SmallSideBarSubPage | null;
    setSubPage: (subPage: SmallSideBarSubPage | null) => void;
}>({
    pages: [],
    currentPage: '',
    setCurrentPage: () => null,
    currentPageData: undefined,
    subPage: null,
    setSubPage: () => null,
});


export type SmallSideBarProps = {
    pages: SmallSidebarNavItem[];
    children?: React.ReactNode;
    defaultPage?: SmallSidebarNavItemValue;
};

const SmallSideBarProvider = ({
    pages,
    defaultPage,
    children,
}: SmallSideBarProps) => {
    const [currentPage, setCurrentPage] = useState<SmallSidebarNavItemValue>(
        defaultPage ?? pages[0]?.value,
    );

    const [subPage, setSubPage] = useState<SmallSideBarSubPage | null>(null);

    const currentPageData = useMemo(() => pages.find((section) => section.value === currentPage), [currentPage, pages])

    const navItemToNavGroup = useMemo(() => {
        const groups: SmallSidebarNavGroupItem[] = [];

        for (const item of pages) {
            if (item.group) {
                const group = groups.find((g) => g.label === item.group);

                if (group) {
                    group.items.push(item);
                } else {
                    groups.push({
                        label: item.group,
                        items: [item],
                    });
                }
            } else {
                groups.push({
                    label: item.group,
                    items: [item],
                });
            }
        }

        return groups;
    }, [pages])

    return (
        <SmallSideBarContext.Provider
            value={{
                pages: navItemToNavGroup,
                currentPage,
                setCurrentPage,
                currentPageData,
                subPage,
                setSubPage,
            }}
        >
            <SidebarProvider className="h-full min-h-[initial] overflow-hidden">
                {children}
            </SidebarProvider>
        </SmallSideBarContext.Provider>
    );
};

const useSmallSideBar = () => {
    const context = useContext(SmallSideBarContext);

    if (!context) {
        throw new Error('useSmallSideBar must be used within a SmallSideBarContextProvider');
    }

    return context;
};

/**
 * Shows the page opened inside the current one in the breadcrumb, as long as the caller is mounted: null when the
 * caller shows the page itself.
 */
const useSmallSideBarSubPage = (subPage: SmallSideBarSubPage | null) => {
    const { setSubPage } = useSmallSideBar();
    const label = subPage?.label ?? null;
    // The breadcrumb calls the latest callback: a new one does not register the page again.
    const onBack = useValueAsRef(subPage?.onBack);

    useEffect(() => {
        setSubPage(
            label === null
                ? null
                : { label, onBack: () => onBack.current?.() },
        );

        return () => setSubPage(null);
    }, [label, onBack, setSubPage]);
};

const SmallSideBar = ({
    className,
    searchBar,
    searchPlaceholder,
}: React.ComponentProps<'div'> & { searchBar?: boolean; searchPlaceholder?: string }) => {
    const { t } = useLaravelReactI18n();

    const { pages, currentPage, setCurrentPage, subPage } = useSmallSideBar();

    // The page already shown, a page opened inside it, comes back to it as its breadcrumb does.
    const openPage = (value: SmallSidebarNavItemValue) => {
        if (value === currentPage) {
            subPage?.onBack();
        } else {
            setCurrentPage(value);
        }
    };

    const [search, setSearch] = useState('');

    const filteredPages = useMemo(() => {
        if (!search) {
            return pages;
        }

        const lowerSearch = search.toLowerCase();

        return pages.map((section) => {
            const filteredItems = section.items.filter((item) =>
                item.name.toLowerCase().includes(lowerSearch),
            );

            if (filteredItems.length > 0) {
                return {
                    ...section,
                    items: filteredItems,
                };
            }

            return null;
        }).filter(Boolean) as SmallSidebarNavGroupItem[];
    }, [pages, search]);

    return (
        <>
            <div
                className={cn(
                    'flex basis-auto flex-row gap-1 overflow-auto bg-sidebar px-3 py-2 md:basis-50 md:flex-col',
                    {
                        'overflow-hidden': searchBar,
                    },
                    className,
                )}
                data-slot="small-sidebar"
            >
                {searchBar && (
                    <div className="hidden w-full md:block pb-2">
                        <Input
                            placeholder={searchPlaceholder ?? t('common.search')}
                            className="w-full max-w-xs"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                )}
                {filteredPages.length === 0 && (
                    <Muted className="text-sm pt-2">
                        {search
                            ? t('common.no_search_match', { search })
                            : t('common.nothing_found')}
                    </Muted>
                )}
                <div className="flex flex-1 flex-row gap-1 md:flex-col overflow-auto">
                    {filteredPages.map((section, index) => {
                        if (!section.label) {
                            return section.items.map((item) => (
                                <SidebarMenuButton
                                    key={item.value}
                                    className="w-auto shrink-0 flex-nowrap md:w-full"
                                    onClick={() => openPage(item.value)}
                                    isActive={currentPage === item.value}
                                >
                                    <item.icon />
                                    {item.name}
                                    {item.badge && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-auto"
                                        >
                                            {item.badge}
                                        </Badge>
                                    )}
                                </SidebarMenuButton>
                            ));
                        }

                        return (
                            <Fragment key={section.label}>
                                <h3
                                    className={cn(
                                        'hidden text-xs font-medium text-muted-foreground md:block',
                                        index > 0 && 'pt-4',
                                    )}
                                >
                                    {section.label}
                                </h3>
                                {section.items.map((item) => (
                                    <SidebarMenuButton
                                        key={item.value}
                                        className="w-auto shrink-0 flex-nowrap md:w-full"
                                        onClick={() => openPage(item.value)}
                                        isActive={currentPage === item.value}
                                    >
                                        <item.icon />
                                        {item.name}
                                        {item.badge && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-auto"
                                            >
                                                {item.badge}
                                            </Badge>
                                        )}
                                    </SidebarMenuButton>
                                ))}
                            </Fragment>
                        );
                    })}
                </div>
            </div>
            <Separator orientation="vertical" className="hidden md:block" />
            <Separator orientation="horizontal" className="block md:hidden" />
        </>
    );
}

const SmallSideBarContent = ({
    className,
    children,
    header,
}: React.ComponentProps<'main'> & { header?: React.ReactElement }) => {
    const { currentPageData } = useSmallSideBar();

    return (
        <div
            className="flex flex-1 flex-col overflow-y-hidden"
            data-slot="small-sidebar-content"
        >
            {header}
            <main
                className={cn(
                    'flex h-120 max-h-150 flex-1 flex-col overflow-auto px-4 pb-4',
                    className,
                )}
            >
                {children
                    ? children
                    : currentPageData?.component && currentPageData.component}
            </main>
        </div>
    );
};



export {
    SmallSideBar,
    useSmallSideBar,
    useSmallSideBarSubPage,
    SmallSideBarProvider,
    SmallSideBarContent,
};
