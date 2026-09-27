import React, {
    createContext,
    Fragment,
    useContext, useMemo,
    useState,
} from 'react';
import { Badge } from '@/components/reui/badge';
import type {
    SmallSidebarNavGroupItem,
    SmallSidebarNavItem,
    SmallSidebarNavItemValue,
} from '@/components/sm-side-bar/types';
import { Separator } from '@/components/ui/separator';
import { SidebarMenuButton, SidebarProvider } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';


const SmallSideBarContext = createContext<{
    pages: SmallSidebarNavGroupItem[];
    currentPage: string;
    currentPageData?: SmallSidebarNavItem;
    setCurrentPage: (value: SmallSidebarNavItemValue) => void;
}>({
    pages: [],
    currentPage: '',
    setCurrentPage: () => null,
    currentPageData: undefined,
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

const SmallSideBar = ({ className }: React.ComponentProps<'div'>) => {

    const { pages, currentPage, setCurrentPage } = useSmallSideBar();

    return (
        <>
            <div
                className={cn(
                    'flex basis-auto flex-row gap-1 overflow-auto bg-sidebar px-3 py-2 md:basis-50 md:flex-col',
                    className,
                )}
                data-slot="small-sidebar"
            >
                {pages.map((section, index) => {
                    if (!section.label) {
                        return section.items.map((item) => (
                            <SidebarMenuButton
                                key={item.value}
                                className="w-auto shrink-0 flex-nowrap md:w-full"
                                onClick={() =>
                                    setCurrentPage(item.value)
                                }
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
                        ))
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
                                    onClick={() =>
                                        setCurrentPage(item.value)
                                    }
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
            <Separator orientation="vertical" />
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
                    'mt-4 flex h-120 max-h-150 flex-1 flex-col overflow-auto px-4 pb-4 md:mt-0',
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
    SmallSideBarProvider,
    SmallSideBarContent,
};
