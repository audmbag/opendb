<?php

return [
    'errors' => [
        'unknown_columns' => 'These columns do not exist in the table: :columns.',
        'no_mapping' => 'Map at least one column of the file to a column of the table.',
        'duplicate_mapping' => 'A column of the table can only receive one column of the file.',
        'rows_refused' => 'Rows :first to :last of the file refused, nothing was imported: :error',
        'unreadable' => 'The file :file cannot be read: it is empty or badly compressed.',
        'zip_missing' => 'The PHP zip extension is not available to read this archive.',
        'zip_unreadable' => 'The zip archive cannot be opened.',
        'no_file' => 'The archive contains no .:extension file.',
        'empty_script' => 'The script contains no SQL statement.',
    ],
];
