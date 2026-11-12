<?php

return [
    'project_deletion' => [
        'required' => 'Type the name of the project to confirm its deletion.',
        'in' => 'The typed name does not match the name of the project.',
    ],
    'writable_directory' => 'The path must be an existing and writable directory.',
    'cloning' => [
        'same_database' => 'The target database cannot be the same as the source database.',
        'table_columns' => 'The columns of the ":table" table must form an array.',
        'column_transformation' => 'The transformation of the ":column" column must be an array.',
        'session_expired' => 'The cloning session cannot be found or has expired, start the cloning configuration again.',
    ],
    'scalar' => 'The :attribute must be a scalar value.',
    'writable_file' => 'The :attribute must be a valid and writable file path.',
    'file' => 'The :attribute must be a valid file path.',
    'snippets' => [
        'label' => 'The name starts with a letter, then only contains letters, digits, "-" or "_" (40 at most).',
        'distinct' => 'Two snippets already have this name.',
        'empty' => 'The snippet is empty.',
    ],
    'changes' => [
        'required' => 'No change to apply.',
        'max' => 'Cannot apply more than :max changes at once.',
    ],
    'design' => [
        'distinct' => 'Two columns have this name.',
        'options' => 'An enumeration needs at least one value.',
        'default_expression' => 'Type the SQL expression of the default value.',
        'scale' => 'The scale cannot exceed the precision.',
        'columns' => 'A table needs at least one column.',
        'single_auto_increment' => 'A table can only have one auto-incremented column.',
        'integer_auto_increment' => 'Only an integer column can be auto-incremented.',
        'auto_increment_key' => 'The auto-incremented column is the whole primary key.',
        'referenced_columns' => 'Choose as many referenced columns as columns.',
        'type' => 'Choose a type for this column.',
    ],
    'identifier' => 'A name starts with a letter or "_", then only contains letters, digits, "_" or "$".',
];
