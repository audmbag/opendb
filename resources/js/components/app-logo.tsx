import { cn } from "@/lib/utils"



const appLogo = [
    '  ██████╗  ██████╗  ███████╗ ███╗   ██╗ ██████╗  ██████╗ ',
    ' ██╔═══██╗ ██╔══██╗ ██╔════╝ ████╗  ██║ ██╔══██╗ ██╔══██╗',
    ' ██║   ██║ ██████╔╝ █████╗   ██╔██╗ ██║ ██║  ██║ ██████╔╝',
    ' ██║   ██║ ██╔═══╝  ██╔══╝   ██║╚██╗██║ ██║  ██║ ██╔══██╗',
    ' ╚██████╔╝ ██║      ███████╗ ██║ ╚████║ ██████╔╝ ██████╔╝',
    '  ╚═════╝  ╚═╝      ╚══════╝ ╚═╝  ╚═══╝ ╚═════╝  ╚═════╝ ',
]


const Lightcolors = [
    "text-zinc-500",
    "text-zinc-600",
    "text-zinc-700",
    "text-zinc-800",
    "text-zinc-900",
    "text-zinc-950",
]

const Darkcolors = [
    "dark:text-zinc-600",
    "dark:text-zinc-500",
    "dark:text-zinc-400",
    "dark:text-zinc-300",
    "dark:text-zinc-200",
    "dark:text-zinc-100",
]

export default function AppLogo() {
    return <div>
        {appLogo.map((line, index) => (
            <pre key={index} className={cn("text-zinc-400 text-xs md:text-sm", Lightcolors[index], Darkcolors[index])}>
                {line}
            </pre>
        ))}
    </div>
}