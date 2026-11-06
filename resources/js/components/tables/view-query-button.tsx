import { CodeIcon, SquareTerminalIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    CodeBlockActions,
    CodeBlockCopyButton,
    CodeBlockHeader,
    CodeBlockTitle,
} from '@/components/code-block';
import Button from '@/components/form/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { uppercaseSqlKeywords } from '@/lib/sql';
import SqlCodeBlock from './sql-code-block';

type Props = {
    sql: string | null;
    /**
     * Whether the tab is shown: the statement of a hidden tab would otherwise stay over the shown one.
     */
    active: boolean;
    onOpenInConsole: (sql: string) => void;
};

/**
 * The statement that read the rows shown in the grid, with the values of the filters written in.
 *
 * The console opens in another tab, which hides this one: it is opened once the popover has finished closing,
 * since a popover still closing in a hidden tab never ends its animation and stays at the top left of the page.
 */
export default function ViewQueryButton({
    sql,
    active,
    onOpenInConsole,
}: Props) {
    const [open, setOpen] = useState(false);
    const sqlToOpen = useRef<string | null>(null);

    return (
        <Popover open={open && active} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    disabled={!sql}
                    aria-label="Voir la requête SQL"
                    tooltip="Voir la requête SQL de ces lignes"
                >
                    <CodeIcon />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="w-[min(36rem,90vw)] p-0"
                onCloseAutoFocus={(event) => {
                    const pending = sqlToOpen.current;

                    if (pending !== null) {
                        event.preventDefault();
                        sqlToOpen.current = null;
                        onOpenInConsole(uppercaseSqlKeywords(pending));
                    }
                }}
            >
                {sql && (
                    <SqlCodeBlock
                        code={sql}
                        className="border-0 [&_pre]:break-all [&_pre]:whitespace-pre-wrap"
                    >
                        <CodeBlockHeader>
                            <CodeBlockTitle>
                                Requête des lignes affichées
                            </CodeBlockTitle>
                            <CodeBlockActions>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7"
                                    onClick={() => {
                                        sqlToOpen.current = sql;
                                        setOpen(false);
                                    }}
                                >
                                    <SquareTerminalIcon />
                                    Ouvrir dans la console
                                </Button>
                                <CodeBlockCopyButton />
                            </CodeBlockActions>
                        </CodeBlockHeader>
                    </SqlCodeBlock>
                )}
            </PopoverContent>
        </Popover>
    );
}
