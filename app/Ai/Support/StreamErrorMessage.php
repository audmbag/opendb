<?php

namespace App\Ai\Support;

use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Exceptions\StreamErrorException;
use Throwable;

/**
 * The message explaining to the user why the provider ended an answer, in the web chat as in the console.
 */
final class StreamErrorMessage
{
    /**
     * Null for any other failure, which stays masked: its message may hold details the user must not read.
     */
    public static function for(?Throwable $exception): ?string
    {
        return match (true) {
            $exception instanceof StreamErrorException && $exception->error === null => __('chat.stream_errors.interrupted'),
            $exception instanceof StreamErrorException => __('chat.stream_errors.provider', ['message' => $exception->error->message]),
            $exception instanceof RateLimitedException => __('chat.stream_errors.rate_limited'),
            $exception instanceof ProviderOverloadedException => __('chat.stream_errors.overloaded'),
            $exception instanceof InsufficientCreditsException => __('chat.stream_errors.insufficient_credits'),
            $exception instanceof ProviderConnectionException => __('chat.stream_errors.connection'),
            default => null,
        };
    }
}
