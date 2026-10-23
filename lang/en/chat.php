<?php

return [
    'stream_errors' => [
        'interrupted' => 'The AI provider ended the answer before it was complete. Please try again.',
        'provider' => 'The AI provider returned an error: ":message"',
        'rate_limited' => 'The AI provider is receiving too many requests right now. Wait a moment before trying again.',
        'overloaded' => 'The AI provider is overloaded right now. Please try again in a moment.',
        'insufficient_credits' => 'The AI provider account does not have enough credits or quota left to answer.',
        'connection' => 'Could not reach the AI provider. Check the connection and try again.',
    ],
];
