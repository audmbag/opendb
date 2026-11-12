<?php

return [
    'formats' => [
        'sql' => 'SQL (script)',
        'csv' => 'CSV (one file per table)',
    ],
    'compressions' => [
        'none' => 'None',
        'zip' => 'Zip (.zip)',
        'gzip' => 'Gzip (.gz)',
        'tar' => 'Tar (.tar)',
    ],
    'short_formats' => [
        'sql' => 'SQL dump',
        'csv' => 'CSV',
    ],
    'all_tables' => 'All the tables',
    'errors' => [
        'empty_query' => 'Nothing to export: the query returns no row.',
        'zip_missing' => 'The PHP zip extension is not available.',
        'archive_create' => 'Could not create the archive :path.',
        'archive_close' => 'Could not finalize the archive :path.',
        'archive_create_because' => 'Could not create the archive :path: :error',
        'write' => 'Could not write the file :path.',
        'unknown_compression' => 'Unknown compression format: :compression. Possible formats: :formats.',
        'unsupported' => 'Export is not available for :driver databases.',
        'no_dialect' => 'No SQL export dialect for :driver databases.',
        'unknown_tables' => 'These tables do not exist in the database: :tables. Use the `ListTables` tool to check the exact names of the available tables.',
        'nothing_selected' => 'Nothing to export: each requested table excludes both its schema and its data.',
    ],
];
