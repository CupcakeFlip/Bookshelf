<?php
/** Made with AI */

declare(strict_types=1);

require_once __DIR__ . '/../../backend/lib/reading_sessions.php';

// Treat warnings as failures: invalid JSON values must reach validation cleanly.
set_error_handler(
    static function (int $level, string $message): never {
        throw new ErrorException($message, 0, $level);
    }
);
try {
    foreach (
        [
            ['formats' => [['ebook']]],
            ['status' => 'dnf', 'dnf_progress_type' => 'chapters', 'dnf_progress_value' => 2],
        ] as $payload
    ) {
        try {
            validateSessionPayload($payload);
            throw new RuntimeException('Invalid session payload was accepted.');
        } catch (InvalidArgumentException $expected) {
            // Expected validation error, not a PHP warning or SQL failure.
        }
    }
    foreach (['percentage', 'page', 'time'] as $type) {
        $payload = [
            'status' => 'dnf',
            'dnf_progress_type' => $type,
            'dnf_progress_value' => 0,
            'formats' => $type === 'page' ? ['ebook'] : [],
        ];
        if (validateSessionPayload($payload) !== $payload) {
            throw new RuntimeException('Valid zero progress was changed.');
        }
    }
    echo "Reading-session validation checks passed.\n";
} finally {
    restore_error_handler();
}
