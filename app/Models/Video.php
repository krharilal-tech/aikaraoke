<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Video extends Model
{
    protected static string $table = 'videos';

    public static function forJob(int $jobId): ?array
    {
        return static::firstWhere(['job_id' => $jobId]);
    }
}
