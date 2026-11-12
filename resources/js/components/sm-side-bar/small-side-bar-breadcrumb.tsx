import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { ReactNode } from 'react';
import Button from '@/components/form/button';
import { useSmallSideBar } from '@/components/sm-side-bar/small-side-bar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';

/**
 * A step of the breadcrumb that goes back to it.
 */
export function SmallSideBarBreadcrumbLink({
    onClick,
    children,
}: {
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <BreadcrumbLink asChild>
            <Button variant="link" onClick={onClick} className="h-auto p-0">
                {children}
            </Button>
        </BreadcrumbLink>
    );
}

/**
 * Where the user is in the sidebar pages: the root, the current page, then the page opened inside it, the way back
 * to the page instead of a back button.
 */
export default function SmallSideBarBreadcrumb({ root }: { root: ReactNode }) {
    const { t } = useLaravelReactI18n();
    const { currentPageData, subPage } = useSmallSideBar();
    const pageName = currentPageData?.name ?? '';

    return (
        <Breadcrumb aria-label={t('common.breadcrumb')} className="min-w-0">
            <BreadcrumbList className="flex-nowrap">
                <BreadcrumbItem className="shrink-0">{root}</BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem className="shrink-0">
                    {subPage ? (
                        <SmallSideBarBreadcrumbLink onClick={subPage.onBack}>
                            {pageName}
                        </SmallSideBarBreadcrumbLink>
                    ) : (
                        <BreadcrumbPage>{pageName}</BreadcrumbPage>
                    )}
                </BreadcrumbItem>
                {subPage && (
                    <>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem className="min-w-0">
                            <BreadcrumbPage className="truncate">
                                {subPage.label}
                            </BreadcrumbPage>
                        </BreadcrumbItem>
                    </>
                )}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
