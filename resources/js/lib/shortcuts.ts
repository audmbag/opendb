/**
 * Every keyboard shortcut of the application, listed in the settings. Each key is shown as written on the keyboard.
 */
export type ShortcutGroup = {
    label: string;
    shortcuts: { description: string; keys: string[][] }[];
};

export const SHORTCUT_GROUPS: ShortcutGroup[] = [
    {
        label: 'Application',
        shortcuts: [
            {
                description: 'Ouvrir la palette de commandes',
                keys: [['Ctrl', 'K']],
            },
            { description: 'Ouvrir les paramètres', keys: [['Ctrl', ',']] },
        ],
    },
    {
        label: 'Conversations',
        shortcuts: [
            {
                description: 'Nouvelle conversation',
                keys: [
                    ['Alt', 'N'],
                    ['Ctrl', 'N'],
                ],
            },
            {
                description: 'Rechercher une conversation',
                keys: [['Alt', 'K']],
            },
        ],
    },
    {
        label: 'Message',
        shortcuts: [
            { description: 'Joindre un fichier', keys: [['Ctrl', 'U']] },
            { description: "Changer de mode d'accès", keys: [['Ctrl', '.']] },
            { description: "Changer l'effort", keys: [['Ctrl', 'Shift', 'D']] },
            { description: 'Choisir le modèle', keys: [['Ctrl', "'"]] },
        ],
    },
    {
        label: 'Tables',
        shortcuts: [
            { description: 'Rechercher une table', keys: [['/']] },
            {
                description: 'Exécuter le script ou la sélection',
                keys: [['Ctrl', 'Entrée']],
            },
            {
                description: 'Formater le script',
                keys: [['Shift', 'Alt', 'F']],
            },
            {
                description: 'Copier les cellules sélectionnées',
                keys: [['Ctrl', 'C']],
            },
            {
                description: 'Ouvrir la ligne référencée',
                keys: [['Ctrl', 'clic']],
            },
        ],
    },
    {
        label: 'Diagrammes',
        shortcuts: [
            { description: 'Quitter le plein écran', keys: [['Échap']] },
        ],
    },
];
