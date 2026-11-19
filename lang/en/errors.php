<?php

return [
    'code' => 'Error :status',
    'back' => 'Back',
    'home' => 'Home',
    'reload' => 'Reload',
    'another_excuse' => 'Another excuse',
    'mascot' => 'The opendb database, a bit lost too',
    'pages' => [
        'forbidden' => [
            'title' => 'Access denied',
            'description' => 'Your privileges do not open this page. An administrator can grant them to you.',
            'result' => 'ERROR 1044 (42000): Access denied for the current user',
            'excuses' => [
                'bouncer' => 'The bouncer checked the list twice. You are not on it.',
                'grant' => 'One well-placed GRANT and this page is yours.',
                'root' => 'Even root had to ask, at first.',
                'velvet' => 'This table is behind a velvet rope.',
            ],
        ],
        'not_found' => [
            'title' => 'Page not found',
            'description' => 'This address leads nowhere: the page was moved, deleted, or never existed.',
            'result' => 'Empty set (0.00 sec)',
            'excuses' => [
                'dropped' => 'Someone ran a DROP without a WHERE. Nobody owns up.',
                'join' => 'Not even a FULL OUTER JOIN could find it.',
                'index' => 'It was not indexed. We scanned the whole table, row by row.',
                'null' => 'It is NULL, and NULL equals nothing, not even itself.',
                'vacation' => 'This page went on vacation without leaving a foreign key.',
            ],
        ],
        'page_expired' => [
            'title' => 'Session expired',
            'description' => 'The page stayed open too long and its session expired. Reload it to pick up where you left off.',
            'result' => 'ERROR 40001: session expired, transaction rolled back',
            'excuses' => [
                'coffee' => 'The coffee break lasted longer than the transaction.',
                'timeout' => 'The token waited, and waited… then it left.',
                'savepoint' => 'No SAVEPOINT this time. One reload and we are back.',
            ],
        ],
        'too_many_requests' => [
            'title' => 'Too many requests',
            'description' => 'You are going faster than the server. Take a few seconds, then try again.',
            'result' => 'ERROR 1040 (08004): Too many connections',
            'excuses' => [
                'breath' => 'The connection pool is catching its breath.',
                'queue' => 'Your queries are queuing all the way down the hall.',
                'limit' => 'LIMIT works for humans too.',
            ],
        ],
        'server_error' => [
            'title' => 'Server error',
            'description' => 'Something went wrong on our side. The error is recorded in the opendb logs.',
            'result' => 'ERROR 40P01: deadlock detected',
            'excuses' => [
                'deadlock' => 'Two processes are holding the door for each other. This could take a while.',
                'semicolon' => 'A semicolon was missing. Somewhere. Surely.',
                'friday' => 'Someone deployed on a Friday.',
                'cosmic' => 'A cosmic ray flipped a bit. That is our story and we are sticking to it.',
            ],
        ],
        'service_unavailable' => [
            'title' => 'Under maintenance',
            'description' => 'opendb is doing a bit of housekeeping and will be back in a moment.',
            'result' => 'Query on hold: the database is taking a nap',
            'excuses' => [
                'vacuum' => 'We are vacuuming the tables. The crumbs of dead rows are going.',
                'reindex' => 'We are sorting the indexes alphabetically. It takes a moment.',
                'nap' => 'Even a database needs some sleep.',
            ],
        ],
    ],
];
