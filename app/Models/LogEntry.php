<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LogEntry extends Model
{
    protected static string $table = 'logs';

    public static function countForJobSource(int $jobId, string $source): int
    {
        return static::count(['job_id' => $jobId, 'source' => $source]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forJob(int $jobId, int $limit = 500): array
    {
        return static::where(['job_id' => $jobId], 'created_at ASC', $limit);
    }
}
