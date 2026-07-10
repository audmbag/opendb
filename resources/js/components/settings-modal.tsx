import {
    Bell,
    Check,
    Globe,
    Home,
    Keyboard,
    Link,
    Lock,
    Menu,
    MessageCircle,
    Paintbrush,
    Settings,
    Video,
} from 'lucide-react';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { SidebarMenuButton, SidebarProvider } from './ui/sidebar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from './ui/breadcrumb';
import { Frame, FramePanel } from './reui/frame';
import { useSettingsDialog } from '@/hooks/use-settings-modal';
import { useEffect } from 'react';
import Modal from '@/components/modal';

const data = {
    nav: [
        { name: 'Notifications', icon: Bell },
        { name: 'Navigation', icon: Menu },
        { name: 'Home', icon: Home },
        { name: 'Appearance', icon: Paintbrush },
        { name: 'Messages & media', icon: MessageCircle },
        { name: 'Language & region', icon: Globe },
        { name: 'Accessibility', icon: Keyboard },
        { name: 'Mark as read', icon: Check },
        { name: 'Audio & video', icon: Video },
        { name: 'Connected accounts', icon: Link },
        { name: 'Privacy & visibility', icon: Lock },
        { name: 'Advanced', icon: Settings },
    ],
};

export default function SettingsDialog() {

    const { open, setOpen } = useSettingsDialog();

    return (
        <Modal title="Settings" description="Manage your settings" open={open} onOpenChange={setOpen}>
            <SettingsContent />
        </Modal>
    );
}



const SettingsContent = () => {
    useEffect(() => {
        console.log("Settings mounted");
    }, []);

    return <SidebarProvider className="overflow-hidden">
        <div className="h-120 overflow-hidden w-full">
            <div className="p-0 h-120 flex w-full flex-col overflow-hidden md:flex-row">
                <header className="flex md:hidden h-13 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                    <div className="flex items-center gap-2 px-4">
                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem>
                                    <BreadcrumbLink href="#">
                                        Settings
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbPage>
                                        Messages & media
                                    </BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>
                </header>
                <div className="flex flex-row gap-1 overflow-auto p-1 md:flex-col">
                    {data.nav.map((item) => (
                        <SidebarMenuButton
                            key={item.name}
                            className="w-auto shrink-0 flex-nowrap md:w-full"
                        >
                            <item.icon />
                            {item.name}
                        </SidebarMenuButton>
                    ))}
                </div>
                <Separator orientation="vertical" />
                <div className="flex flex-col flex-1 overflow-y-hidden">
                    <header className="hidden md:flex h-12 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                        <div className="flex items-center gap-2 px-4 justify-between">
                            <Breadcrumb>
                                <BreadcrumbList>
                                    <BreadcrumbItem>
                                        <BreadcrumbLink href="#">
                                            Settings
                                        </BreadcrumbLink>
                                    </BreadcrumbItem>
                                    <BreadcrumbSeparator />
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>
                                            Messages & media
                                        </BreadcrumbPage>
                                    </BreadcrumbItem>
                                </BreadcrumbList>
                            </Breadcrumb>
                        </div>
                    </header>
                    <main className="flex flex-1 flex-col overflow-auto px-4 pb-4 max-h-150 h-120 mt-4 md:mt-0">
                        {Array.from({ length: 10 }).map((_, i) => (
                            <div
                                key={i}
                                className="aspect-video max-w-3xl rounded-xl bg-muted/50 mb-4 last:mb-0"
                            />
                        ))}
                    </main>
                </div>
            </div>
        </div>
    </SidebarProvider>
}