<?php

return [
    'fields' => [
        'id' => 'Identifier',
        'name' => 'Token name',
        'project' => 'Project',
        'token' => 'Token',
        'created_at' => 'Created at',
        'last_used_at' => 'Last used',
    ],
    'questions' => [
        'revoke' => 'Revoke the token ":name"? The agents using it will lose access to the project.',
    ],
    'messages' => [
        'created' => 'MCP token created on the project ":project".',
        'shown_once' => 'Copy it now: it will never be shown again.',
        'client_config' => 'Add this to the configuration of your MCP client:',
        'no_tokens' => 'No MCP token.',
        'never_used' => 'Never',
        'revoked' => 'The token ":name" is revoked.',
    ],
    'errors' => [
        'missing_token' => 'No MCP token: set the OPENDB_MCP_TOKEN environment variable in the configuration of the MCP client.',
        'unknown_token' => 'Unknown or revoked MCP token.',
        'project_out_of_reach' => 'The project ":project" is out of reach of your privileges.',
        'token_not_found' => 'None of your MCP tokens has the identifier ":token".',
    ],
];
