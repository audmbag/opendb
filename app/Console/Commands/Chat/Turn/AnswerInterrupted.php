<?php

namespace App\Console\Commands\Chat\Turn;

use RuntimeException;

/**
 * Ctrl+C pressed while the agent answers, on a system where the answer is written by the chat process itself.
 */
final class AnswerInterrupted extends RuntimeException {}
