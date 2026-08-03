<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Lyrics extends Model
{
    protected static string $table = 'lyrics';

    public const SOURCE_LRCLIB = 'lrclib';
    public const SOURCE_MUSIXMATCH = 'musixmatch';
    public const SOURCE_GENIUS = 'genius';
    public const SOURCE_WHISPERX = 'whisperx';

    public static function forJob(int $jobId): ?array
    {
        return static::firstWhere(['job_id' => $jobId]);
    }

    /**
     * @return array<int, array{word: string, start: float, end: float}>
     */
    public static function words(array $lyricsRow): array
    {
        $decoded = json_decode((string) $lyricsRow['words_json'], true);

        return is_array($decoded) ? $decoded : [];
    }
}
