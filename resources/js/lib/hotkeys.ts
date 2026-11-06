import type { Options } from 'react-hotkeys-hook';

/**
 * Options of the shortcuts usable while typing a prompt. The character is matched rather than the physical key,
 * so the shortcuts shown in the tooltips also work on an AZERTY keyboard.
 */
export const PROMPT_HOTKEY_OPTIONS = {
    enableOnFormTags: true,
    preventDefault: true,
    useKey: true,
} satisfies Options;
