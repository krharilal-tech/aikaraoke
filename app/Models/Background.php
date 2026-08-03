<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Background extends Model
{
    protected static string $table = 'backgrounds';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forJob(int $jobId): array
    {
        return static::where(['job_id' => $jobId], 'id ASC');
    }
}
