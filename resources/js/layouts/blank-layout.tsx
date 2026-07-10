import { AppSidebar } from "@/components/app-sidebar";
import CommandModal from "@/components/command-modal";
import Button from "@/components/form/button";
import { GitHubIcon } from "@/components/icons/icons";
import SettingsDialog from "@/components/settings-modal";
import { buttonVariants } from "@/components/ui/button";
import { CommandModalProvider } from "@/hooks/use-command-modal";
import { SettingsProvider } from "@/hooks/use-settings-modal";
import { CircleQuestionMarkIcon } from "lucide-react";


export default function BlankLayout({ children }: { children: React.ReactNode }) {
    return (
        <SettingsProvider>
            <CommandModalProvider>
                <div className="h-screen w-full flex flex-col p-4">
                    <CommandModal />
                    <SettingsDialog />
                    <main className="flex-1 flex flex-col">
                        {children}
                    </main>
                    <footer className="pt-4">
                        <div className="flex justify-center items-center gap-2">
                            <a target="_blank" href="https://github.com/audmbag/opendb" className={buttonVariants({ size: "icon-lg", variant: "secondary" })}>
                                <GitHubIcon />
                            </a>
                            <a target="_blank" href="https://opendb.com/docs" className={buttonVariants({ size: "icon-lg", variant: "secondary" })}>
                                <CircleQuestionMarkIcon />
                            </a>
                        </div>
                    </footer>
                </div>
            </CommandModalProvider>
        </SettingsProvider>
    )
}