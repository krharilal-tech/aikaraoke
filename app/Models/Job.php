<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Job extends Model
{
    protected static string $table = 'jobs';

    public const STATE_QUEUED = 'queued';
    public const STATE_DOWNLOADING = 'downloading';
    public const STATE_EXTRACTING_AUDIO = 'extracting_audio';
    public const STATE_SEPARATING_VOCALS = 'separating_vocals';
    public const STATE_EXTRACTING_LYRICS = 'extracting_lyrics';
    public const STATE_SYNCHRONIZING = 'synchronizing';
    public const STATE_GENERATING_IMAGES = 'generating_images';
    public const STATE_WAITING_FOR_USER = 'waiting_for_user';
    public const STATE_RENDERING_VIDEO = 'rendering_video';
    public const STATE_COMPLETED = 'completed';
    public const STATE_FAILED = 'failed';

    /**
     * Ordered pipeline states shown on the progress page (terminal states excluded).
     *
     * @return array<int, array{state: string, label: string}>
     */
    public static function pipelineStages(): array
    {
        return [
            ['state' => self::STATE_QUEUED, 'label' => 'Queued'],
            ['state' => self::STATE_DOWNLOADING, 'label' => 'Downloading YouTube Video'],
            ['state' => self::STATE_EXTRACTING_AUDIO, 'label' => 'Extracting Audio'],
            ['state' => self::STATE_SEPARATING_VOCALS, 'label' => 'Removing Vocals'],
            ['state' => self::STATE_EXTRACTING_LYRICS, 'label' => 'Extracting Lyrics'],
            ['state' => self::STATE_SYNCHRONIZING, 'label' => 'Synchronizing Lyrics'],
            ['state' => self::STATE_GENERATING_IMAGES, 'label' => 'Generating AI Backgrounds'],
            ['state' => self::STATE_RENDERING_VIDEO, 'label' => 'Rendering Karaoke Video'],
            ['state' => self::STATE_COMPLETED, 'label' => 'Completed'],
        ];
    }

    /**
     * Jobs a cron sync pass still needs to look at: anything not yet
     * finished (needs its status.json re-read), plus anything finished but
     * not yet emailed (the sync itself is a no-op at that point, but
     * JobService::getStatus() also fires the notification as a side
     * effect — see bin/sync-jobs.php).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pendingSyncOrNotify(): array
    {
        return static::db()->fetchAll(
            "SELECT * FROM `jobs` WHERE `state` NOT IN (?, ?) OR `notified_at` IS NULL",
            [self::STATE_COMPLETED, self::STATE_FAILED]
        );
    }

    /**
     * Finished jobs older than $days (measured from their last state
     * change, i.e. `updated_at` — the closest thing this table has to a
     * "completed_at", since nothing updates a job's row again once it
     * reaches a terminal state) whose files haven't already been cleaned
     * up. Used by bin/cleanup-expired-jobs.php.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function expiredCandidates(int $days): array
    {
        return static::db()->fetchAll(
            "SELECT * FROM `jobs`
             WHERE `state` IN (?, ?)
               AND `expired_at` IS NULL
               AND `updated_at` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)",
            [self::STATE_COMPLETED, self::STATE_FAILED, $days]
        );
    }

    public static function extractYoutubeId(string $url): ?string
    {
        $patterns = [
            '#youtu\.be/([A-Za-z0-9_-]{6,})#',
            '#[?&]v=([A-Za-z0-9_-]{6,})#',
            '#youtube\.com/shorts/([A-Za-z0-9_-]{6,})#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
