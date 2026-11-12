<?php

namespace App\Console\Commands\Chat;

use RuntimeException;

/**
 * The user left the chat with Ctrl+C while it was asking them something.
 */
class ChatClosed extends RuntimeException {}
