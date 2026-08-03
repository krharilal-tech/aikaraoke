#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron entry point: syncs every still-running (or not-yet-notified) job's
 * status.json into the database, and — as a side effect of
 * JobService::getStatus(), which this reuses rather than duplicating —
 * sends the "your video is ready/failed" email exactly once per job.
 *
 * This exists because the normal sync path only runs when a browser is
 * actively polling GET /api/jobs/{id}/status. If the user closes the tab
 * (which is the entire point of emailing them instead of making them
 * wait), nothing else would notice the job finished. Run this every 1-2
 * minutes via cron — see docs/CONFIGURATION.md for the crontab line.
 *
 * Usage: php bin/sync-jobs.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Env;
use App\Models\Job;
use App\Services\JobService;

$rootPath = dirname(__DIR__);
Env::load($rootPath . '/.env');
date_default_timezone_set('UTC');

$jobService = new JobService();
$jobs = Job::pendingSyncOrNotify();

echo sprintf("[%s] Syncing %d job(s)...\n", gmdate('c'), count($jobs));

foreach ($jobs as $job) {
    $jobId = (int) $job['id'];

    try {
        $jobService->getStatus($jobId);
    } catch (\Throwable $e) {
        echo sprintf("  job #%d: ERROR - %s\n", $jobId, $e->getMessage());
    }
}

echo sprintf("[%s] Done.\n", gmdate('c'));
