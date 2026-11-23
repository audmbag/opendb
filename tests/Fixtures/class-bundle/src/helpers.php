<?php

$GLOBALS['fixtureScripts'][] = 'helpers';

if (! function_exists('fixture_greeting')) {
    function fixture_greeting(): string
    {
        return 'hello';
    }
}

return;
