<?php

return [
    'default_username' => 'opendb',
    'default_password' => 'opendb',

    /*
     * The executable of an installed copy, given by its launcher: what an MCP client runs to start the server. A
     * checkout has none, and is started through PHP and its artisan script.
     */
    'binary' => env('OPENDB_BINARY'),
];
