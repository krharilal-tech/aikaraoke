<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use Throwable;

final class Setting extends Model
{
    protected static string $table = 'settings';

    protected static string $primaryKey = 'key';

    private const DEFAULTS = [
        'image_model' => 'gpt-image-1',
        'prompt_model' => 'gpt-4o-mini',
        'background_source' => 'openai',
        'local_backgrounds_path' => 'storage/backgrounds',
        'ffmpeg_path' => 'ffmpeg',
        'ffprobe_path' => 'ffprobe',
        'python_path' => 'python',
        'yt_dlp_path' => 'yt-dlp',
        'demucs_model' => 'htdemucs',
        'vocal_bleed_back_percent' => '0',
        'whisperx_model' => 'base',
        'transcription_language' => 'auto',
        'max_video_length_seconds' => '600',
        'temp_storage_path' => 'storage/jobs',
        'youtube_cookies' => '',
    ];

    /**
     * @return array<string, string>
     */
    public static function allAsMap(): array
    {
        $map = self::DEFAULTS;

        try {
            $rows = static::all();
        } catch (Throwable) {
            return $map;
        }

        foreach ($rows as $row) {
            $map[$row['key']] = $row['value'];
        }

        return $map;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $row = static::find($key);
        } catch (Throwable) {
            $row = null;
        }

        if ($row !== null) {
            return $row['value'];
        }

        return $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, string $value): void
    {
        $db = Database::instance();
        $db->execute(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value]
        );
    }

    /**
     * @param array<string, string> $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }
}
