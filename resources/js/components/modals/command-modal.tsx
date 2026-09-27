'use client';

import {
    BellIcon,
    CalculatorIcon,
    CalendarIcon,
    ClipboardPasteIcon,
    CodeIcon,
    CopyIcon,
    CreditCardIcon,
    FileTextIcon,
    FolderClosedIcon,
    FolderIcon,
    FolderPlusIcon,
    HelpCircleIcon,
    HomeIcon,
    ImageIcon,
    InboxIcon,
    LayoutGridIcon,
    ListIcon,
    PlusIcon,
    ScissorsIcon,
    SettingsIcon,
    TrashIcon,
    UserIcon,
    ZoomInIcon,
    ZoomOutIcon,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Command,
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import { useEffect } from 'react';
import { useCommandModal } from '@/hooks/use-command-modal';
import { Frame, FramePanel } from '../reui/frame';

export default function CommandModal() {

    const { open, setOpen } = useCommandModal();

    return (
        <CommandDialog open={open} onOpenChange={setOpen}>
            <CommandeContent />
        </CommandDialog>
    );
}





const CommandeContent = () => {

    const Commands = [
        {
            group: "Navigation",
            items: [
                {
                    name: "Récent projects",
                    shortcut: "Ctrl + R",
                    icon: FolderClosedIcon,
                    action: () => { console.log("Récent projects") }
                },
                {
                    name: "Open project",
                    shortcut: "Ctrl + O",
                    icon: FolderIcon,
                    action: () => { console.log("Open project") }
                },
                {
                    name: "Settings",
                    shortcut: "Ctrl + ,",
                    icon: SettingsIcon,
                    action: () => { console.log("Settings") }
                },
                {
                    name: "Help",
                    shortcut: "Ctrl + H",
                    icon: HelpCircleIcon,
                    action: () => { console.log("Help") }
                }
            ]
        }
    ]

    return <Command className="p-0">
        <Frame className="w-full">
            <FramePanel className="p-0">
                <CommandInput placeholder="Type a command or search..." />
                <CommandList>
                    <CommandEmpty>No results found.</CommandEmpty>
                    {Commands.map((commandGroup, index) => (
                        <CommandGroup key={index} heading={commandGroup.group}>
                            {commandGroup.items.map((command, index) => (
                                <CommandItem key={index} onSelect={command.action}>
                                    <command.icon />
                                    <span>{command.name}</span>
                                    <CommandShortcut>{command.shortcut}</CommandShortcut>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    ))}
                </CommandList>
            </FramePanel>
        </Frame>
    </Command>
}
