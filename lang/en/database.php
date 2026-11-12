<?php

return [
    'operations' => [
        'accounts' => 'the management of accounts and privileges',
        'server' => 'the management of databases and the server',
        'files' => 'the access to the files of the server',
        'dynamic_sql' => 'the execution of dynamic SQL',
    ],
    'guards' => [
        'administrative' => 'Query rejected: :operation is reserved to a database administrator and is never allowed, whatever the mode. If the query targets a column with this name, specify the COLUMN keyword.',
        'read_only_statements' => 'Query rejected: only SELECT, WITH, EXPLAIN, SHOW and DESCRIBE statements are allowed by this tool.',
        'select_only' => 'Query rejected: only SELECT and WITH statements are allowed by this tool.',
        'explain_analyze' => 'Query rejected: EXPLAIN ANALYZE runs the query, only the estimated plan can be computed.',
        'select_into' => 'Query rejected: SELECT … INTO writes the result elsewhere and cannot be analyzed.',
        'locking' => 'Query rejected: a query locking rows (FOR UPDATE, FOR SHARE) cannot be analyzed.',
        'keyword' => 'Query rejected: the :keyword keyword is not allowed in this read-only tool.',
        'pragma_assignment' => 'Query rejected: configuration PRAGMAs (with \'=\') are not allowed in read-only mode.',
        'pragma' => 'Query rejected: the \':pragma\' pragma is not in the allowed whitelist.',
        'stacked' => 'Query rejected: multiple queries (stacked statements) are not allowed. If there is a single statement, remove the comments and pass the values containing a semicolon in `bindings`.',
    ],
    'hidden' => [
        'named' => 'The [:table] table is disabled for the agent in the project settings: you can neither read its structure or data, nor change it.',
        'cited' => 'Query rejected: it cites the [:table] table, disabled for the agent in the project settings. A column name, an alias or a value identical to the table name is enough to reject the query.',
        'ask_user' => 'Explain it to the user and, if their request needs it, suggest making the table accessible in the project settings ("Tables" section, AI agent access). Do not try to get around this restriction.',
        'system_catalog' => 'Query rejected: the system catalogs (information_schema, pg_catalog, sqlite_master…) cannot be reached while some tables are disabled for the agent, because they describe these tables too. Use the `ListTables` and `FocusTable` tools to explore the structure of the database.',
        'dynamic_sql' => 'Query rejected: dynamic SQL (PREPARE, EXECUTE, functions running a query passed as text…) is forbidden while some tables are disabled for the agent, because it would reach them without naming them. Write the query directly.',
    ],
    'errors' => [
        'no_driver' => 'No driver available for [:driver].',
        'directory_not_writable' => 'the folder [:directory] is not writable',
        'cannot_create' => 'The database [:database] does not exist and the user is not allowed to create it.',
        'cannot_create_because' => 'The database [:database] does not exist and the user is not allowed to create it (:reason).',
        'creation_failed' => 'The creation of the database [:database] failed.',
        'unsupported' => 'The operation [:operation] is not supported by the driver [:driver].',
        'table_not_found' => 'The [:table] table does not exist in the database or cannot be reached. Use the `ListTables` tool to check the exact names of the available tables.',
        'sort_column' => 'The sort column [:column] does not exist in the result of the query.',
        'focused_table_missing' => 'The table given in `focusedTable.tableName` does not exist in the database. Use the `ListTables` tool to check the exact names of the available tables.',
    ],
    'records' => [
        'invalid_key' => 'The key must be a `column => value` object on columns of the [:table] table: :columns.',
        'not_found' => 'No record of the [:table] table matches the key :key.',
    ],
];
