#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron entry point: deletes the on-disk files (source audio, stems,
 * generated images, and the final video) for jobs finished more than
 * MAX_AGE_DAYS ago, to keep storage from growing forever. The `jobs` row
 * itself — title, thumbnail, state, timestamps — is kept, so "My Videos"
 * still shows the job existed; only the heavy files disappear, and
 * `expired_at` gets set so the app knows not to offer a now-broken
 * download link for it.
 *
 * Run this once a day via cron — see docs/CONFIGURATION.md.
 *
 * Usage: php bin/cleanup-expired-jobs.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Env;
use App\Models\Job;

const MAX_AGE_DAYS = 7;

$rootPath = dirname(__DIR__);
Env::load($rootPath . '/.env');
date_default_timezone_set('UTC');

$jobs = Job::expiredCandidates(MAX_AGE_DAYS);

echo sprintf("[%s] Found %d job(s) older than %d day(s) to clean up...\n", gmdate('c'), count($jobs), MAX_AGE_DAYS);

foreach ($jobs as $job) {
    $jobId = (int) $job['id'];
    $jobDir = Config::get('paths.jobs') . '/' . $jobId;

    if (is_dir($jobDir)) {
        deleteDirectory($jobDir);
        echo sprintf("  job #%d: deleted %s\n", $jobId, $jobDir);
    } else {
        echo sprintf("  job #%d: no directory to delete (already gone)\n", $jobId);
    }

    Job::update($jobId, ['expired_at' => gmdate('Y-m-d H:i:s')]);
}

echo sprintf("[%s] Done.\n", gmdate('c'));

function deleteDirectory(string $dir): void
{
    $items = scandir($dir);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;

        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}
