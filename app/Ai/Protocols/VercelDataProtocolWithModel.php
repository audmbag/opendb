<?php

namespace App\Ai\Protocols;

use Generator;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Exceptions\StreamErrorException;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Protocols\VercelDataProtocol;
use Throwable;

/**
 * The Vercel data stream protocol, also sending the provider and the model that wrote the answer,
 * as the data parts the conversation shows under each response.
 *
 * A stream ended by a provider error also sends the message the conversation shows to the user,
 * as a transient data part ahead of the error part.
 */
class VercelDataProtocolWithModel extends VercelDataProtocol
{
    protected ?StreamStart $streamStart = null;

    /**
     * {@inheritdoc}
     */
    protected function parts(StreamableAgentResponse $response): Generator
    {
        try {
            foreach (parent::parts($response) as $part) {
                if ($part['type'] === 'finish' && $this->streamStart !== null) {
                    yield ['type' => 'data-provider', 'id' => 'provider', 'data' => $this->streamStart->provider];
                    yield ['type' => 'data-model', 'id' => 'model', 'data' => $this->streamStart->model];
                }

                yield $part;
            }
        } catch (StreamErrorException $exception) {
            $this->exception = $exception;

            yield from $this->streamErrorMessageParts($exception);
            yield from $this->yieldPart(['type' => 'error', 'errorText' => $exception->getMessage()]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function maskedErrorParts(): Generator
    {
        yield from $this->streamErrorMessageParts($this->exception);
        yield from parent::maskedErrorParts();
    }

    /**
     * Get the data part carrying the message that explains the given exception to the user, if it has one.
     *
     * The client stops reading at the error part, so this part has to come first.
     */
    protected function streamErrorMessageParts(?Throwable $exception): Generator
    {
        if (($message = $this->streamErrorMessage($exception)) === null) {
            return;
        }

        yield from $this->yieldPart([
            'type' => 'data-stream-error',
            'data' => ['message' => $message],
            'transient' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapEvent(StreamEvent $event): ?array
    {
        if ($event instanceof StreamStart) {
            $this->streamStart = $event;
        }

        // A provider error ends the step with a stream error exception, which sends the error part instead...
        if ($event instanceof Error) {
            return null;
        }

        return parent::mapEvent($event);
    }

    /**
     * Get the message that explains to the user why the provider ended the stream, keeping any other failure masked.
     */
    protected function streamErrorMessage(?Throwable $exception): ?string
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
