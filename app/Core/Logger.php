<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Writes structured log entries to the `logs` table (queryable from the UI)
 * and mirrors everything to a rolling file log so the app is debuggable even
 * before the database is reachable (e.g. during install).
 */
final class Logger
{
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    public const SOURCE_PHP = 'php';
    public const SOURCE_PYTHON = 'python';
    public const SOURCE_FFMPEG = 'ffmpeg';
    public const SOURCE_OPENAI = 'openai';

    public static function log(
        string $level,
        string $source,
        string $message,
        array $context = [],
        ?int $jobId = null
    ): void {
        self::writeToFile($level, $source, $message, $context, $jobId);

        try {
            Database::instance()->execute(
                'INSERT INTO logs (job_id, level, source, message, context, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$jobId, $level, $source, $message, $context === [] ? null : json_encode($context)]
            );
        } catch (Throwable) {
            // Database not ready yet (e.g. pre-install) — file log above already captured it.
        }
    }

    public static function info(string $message, array $context = [], ?int $jobId = null, string $source = self::SOURCE_PHP): void
    {
        self::log(self::LEVEL_INFO, $source, $message, $context, $jobId);
    }

    public static function warning(string $message, array $context = [], ?int $jobId = null, string $source = self::SOURCE_PHP): void
    {
        self::log(self::LEVEL_WARNING, $source, $message, $context, $jobId);
    }

    public static function error(string $message, array $context = [], ?int $jobId = null, string $source = self::SOURCE_PHP): void
    {
        self::log(self::LEVEL_ERROR, $source, $message, $context, $jobId);
    }

    private static function writeToFile(string $level, string $source, string $message, array $context, ?int $jobId): void
    {
        $dir = Config::get('paths.logs');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $line = sprintf(
            '[%s] %s.%s %s%s%s',
            date('Y-m-d H:i:s'),
            $source,
            $level,
            $jobId !== null ? "job#{$jobId} " : '',
            $message,
            $context !== [] ? ' ' . json_encode($context) : ''
        );

        file_put_contents($dir . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
