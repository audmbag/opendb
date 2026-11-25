/**
 * Every keyboard shortcut of the application, listed in the settings. Each key is shown as written on the keyboard.
 * The labels, the descriptions and the named keys are keys of lang/{locale}/shortcuts.php.
 */
export type ShortcutGroup = {
    label: string;
    shortcuts: { description: string; keys: string[][] }[];
};

export const SHORTCUT_GROUPS: ShortcutGroup[] = [
    {
        label: 'shortcuts.groups.application',
        shortcuts: [
            {
                description: 'shortcuts.command_palette',
                keys: [['Ctrl', 'K']],
            },
            { description: 'shortcuts.settings', keys: [['Ctrl', ',']] },
            { description: 'shortcuts.open_project', keys: [['Ctrl', 'O']] },
            {
                description: 'shortcuts.new_project',
                keys: [['Ctrl', 'Shift', 'N']],
            },
        ],
    },
    {
        label: 'shortcuts.groups.conversations',
        shortcuts: [
            {
                description: 'shortcuts.new_conversation',
                keys: [
                    ['Alt', 'N'],
                    ['Ctrl', 'N'],
                ],
            },
            {
                description: 'shortcuts.search_conversation',
                keys: [['Alt', 'K']],
            },
        ],
    },
    {
        label: 'shortcuts.groups.message',
        shortcuts: [
            { description: 'shortcuts.attach_file', keys: [['Ctrl', 'U']] },
            { description: 'shortcuts.access_mode', keys: [['Ctrl', '.']] },
            { description: 'shortcuts.effort', keys: [['Ctrl', 'Shift', 'D']] },
            { description: 'shortcuts.model', keys: [['Ctrl', "'"]] },
        ],
    },
    {
        label: 'shortcuts.groups.tables',
        shortcuts: [
            { description: 'shortcuts.search_table', keys: [['/']] },
            {
                description: 'shortcuts.run_script',
                keys: [['Ctrl', 'shortcuts.keys.enter']],
            },
            {
                description: 'shortcuts.format_script',
                keys: [['Shift', 'Alt', 'F']],
            },
            {
                description: 'shortcuts.copy_cells',
                keys: [['Ctrl', 'C']],
            },
            {
                description: 'shortcuts.open_referenced_row',
                keys: [['Ctrl', 'shortcuts.keys.click']],
            },
        ],
    },
    {
        label: 'shortcuts.groups.diagrams',
        shortcuts: [
            {
                description: 'shortcuts.exit_fullscreen',
                keys: [['shortcuts.keys.escape']],
            },
        ],
    },
];
