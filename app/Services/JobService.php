<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Models\Background;
use App\Models\Credit;
use App\Models\Job;
use App\Models\LogEntry;
use App\Models\Lyrics;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use RuntimeException;

/**
 * Owns the job lifecycle: creation, spawning the Python worker, and
 * reconciling the `jobs` table with the JSON status file the worker writes.
 */
final class JobService
{
    public function create(string $youtubeUrl, bool $keepVocals, ?int $userId = null, ?string $language = null): array
    {
        $jobId = (int) Job::create([
            'user_id' => $userId,
            'youtube_url' => $youtubeUrl,
            'youtube_video_id' => Job::extractYoutubeId($youtubeUrl),
            'keep_vocals' => $keepVocals ? 1 : 0,
            'state' => Job::STATE_QUEUED,
            'progress_percent' => 0,
        ]);

        $jobDir = $this->jobDirectory($jobId);

        if (!is_dir($jobDir) && !mkdir($jobDir, 0775, true) && !is_dir($jobDir)) {
            throw new RuntimeException("Could not create job directory: {$jobDir}");
        }

        $this->writeStatusFile($jobId, [
            'job_id' => $jobId,
            'state' => Job::STATE_QUEUED,
            'stage_label' => 'Queued',
            'progress_percent' => 0,
            'eta_seconds' => null,
            'message' => 'Job created, waiting for the worker to start.',
            'logs' => [],
            'error' => null,
            'metadata' => null,
            'updated_at' => gmdate('c'),
        ]);

        Job::update($jobId, ['storage_path' => $jobDir]);

        $this->writeConfigFile($jobId, $youtubeUrl, $keepVocals, $language);

        Logger::info('Job created', ['youtube_url' => $youtubeUrl, 'keep_vocals' => $keepVocals, 'language' => $language], $jobId);

        $this->spawnWorker($jobId, $youtubeUrl, $keepVocals, $language);

        return Job::find($jobId) ?? [];
    }

    /**
     * Everything the Python worker needs (tool paths, model names, API
     * keys) — shared by writeConfigFile() (local subprocess mode, reads
     * this back from config.json) and dispatchToRunPod() (remote mode,
     * sends the same thing as the RunPod job's `input` instead). Local and
     * remote each override a couple of fields afterward that only make
     * sense for that mode (job_dir, local_backgrounds_path).
     *
     * @return array<string, mixed>
     */
    private function buildJobConfig(int $jobId, string $youtubeUrl, bool $keepVocals, ?string $language = null): array
    {
        return [
            'job_id' => $jobId,
            'youtube_url' => $youtubeUrl,
            'keep_vocals' => $keepVocals,
            'job_dir' => $this->jobDirectory($jobId),
            'max_video_length_seconds' => (int) Setting::get('max_video_length_seconds', '600'),
            'ffmpeg_path' => Setting::get('ffmpeg_path', 'ffmpeg'),
            'ffprobe_path' => Setting::get('ffprobe_path', 'ffprobe'),
            'yt_dlp_path' => Setting::get('yt_dlp_path', 'yt-dlp'),
            'demucs_model' => Setting::get('demucs_model', 'htdemucs'),
            'vocal_bleed_back_percent' => (int) Setting::get('vocal_bleed_back_percent', '0'),
            'whisperx_model' => Setting::get('whisperx_model', 'medium'),
            // $language is this one job's explicit override (Home page's
            // "Song language" picker) — auto-detect is unreliable for
            // short/ambiguous clips (confirmed on a real song that flipped
            // between en/ta/ml across model sizes), so a user who already
            // knows the language should be able to just say so, bypassing
            // detection entirely rather than fighting it via global Settings.
            'transcription_language' => $language ?? Setting::get('transcription_language', 'auto'),
            'image_model' => Setting::get('image_model', 'gpt-image-1'),
            'prompt_model' => Setting::get('prompt_model', 'gpt-4o-mini'),
            'background_source' => Setting::get('background_source', 'openai'),
            'local_backgrounds_path' => $this->resolvePath(Setting::get('local_backgrounds_path', 'storage/backgrounds')),
            'youtube_cookies' => Setting::get('youtube_cookies', ''),
            'openai_api_key' => env('OPENAI_API_KEY', ''),
            'musixmatch_api_key' => env('MUSIXMATCH_API_KEY', ''),
            'genius_access_token' => env('GENIUS_ACCESS_TOKEN', ''),
        ];
    }

    /**
     * Snapshot everything the Python worker needs into the job directory
     * at creation time. This is the only channel Python has into app
     * configuration in local subprocess mode — it never touches MySQL or
     * .env directly, keeping "PHP owns config/DB, Python only processes"
     * strictly true. (In RunPod mode, dispatchToRunPod() sends the
     * equivalent as the job's `input` instead — this file still gets
     * written either way since nothing reads config.json to decide which
     * mode is active, but it's harmless/unused in RunPod mode.)
     */
    private function writeConfigFile(int $jobId, string $youtubeUrl, bool $keepVocals, ?string $language = null): void
    {
        $config = $this->buildJobConfig($jobId, $youtubeUrl, $keepVocals, $language);

        file_put_contents(
            $this->jobDirectory($jobId) . '/config.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * Settings store paths like "storage/backgrounds" relative to the
     * project root, but the Python worker runs with no notion of "project
     * root" beyond what we snapshot into config.json — so relative paths
     * are resolved to absolute here, once, in the one place that already
     * knows where the root is. An already-absolute value (e.g. an admin
     * pointing "Local Backgrounds Folder" at a drive outside the project)
     * is passed through untouched.
     */
    private function resolvePath(string $path): string
    {
        $isAbsolute = preg_match('#^([A-Za-z]:[\\\\/]|/)#', $path) === 1;

        return $isAbsolute ? $path : Config::get('paths.root') . '/' . $path;
    }

    public function jobDirectory(int $jobId): string
    {
        return Config::get('paths.jobs') . '/' . $jobId;
    }

    public function statusFilePath(int $jobId): string
    {
        return $this->jobDirectory($jobId) . '/status.json';
    }

    /**
     * @param array<string, mixed> $status
     */
    public function writeStatusFile(int $jobId, array $status): void
    {
        $path = $this->statusFilePath($jobId);
        file_put_contents($path, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Entry point for WorkerCallbackController's status webhook (RunPod
     * mode) — the remote counterpart to a local worker writing
     * status.json directly, which getStatus() below then reads. This
     * writes the exact same file locally first (so getStatus() and
     * everything downstream of it — the poll API, the log console — stay
     * completely unaware of which mode produced it), then runs the same
     * sync-into-DB and notify-if-terminal logic a local poll would.
     *
     * @param array<string, mixed> $status
     */
    public function applyRemoteStatus(int $jobId, array $status): void
    {
        $job = Job::find($jobId);

        if ($job === null) {
            return;
        }

        $this->writeStatusFile($jobId, $status);
        $this->syncFromFileStatus($job, $status);

        $job = Job::find($jobId) ?? $job;
        $this->notifyIfTerminal($job);
    }

    /**
     * Fails and refunds every job stuck mid-pipeline with no status update
     * in $staleAfterMinutes — see Job::staleCandidates() for why this has
     * to exist (a job the worker abandons without ever calling back would
     * otherwise sit "in progress" forever). Intended to run every cron
     * cycle alongside sync-jobs.php's normal sync pass.
     *
     * @return int number of jobs failed
     */
    public function failStaleJobs(int $staleAfterMinutes = 10): int
    {
        $jobs = Job::staleCandidates($staleAfterMinutes);

        foreach ($jobs as $job) {
            $jobId = (int) $job['id'];

            Job::update($jobId, [
                'state' => Job::STATE_FAILED,
                'error_message' => 'The worker never responded — this job was likely dropped before it could start '
                    . '(e.g. the GPU provider ran out of capacity). Any credit used has been refunded automatically.',
            ]);

            if ($job['user_id'] !== null) {
                Credit::refundForJob((int) $job['user_id'], $jobId);
            }

            Logger::error('Job marked failed — no status update in ' . $staleAfterMinutes . ' minute(s)', ['job_id' => $jobId], $jobId, Logger::SOURCE_PHP);

            $updated = Job::find($jobId);

            if ($updated !== null) {
                $this->notifyIfTerminal($updated);
            }
        }

        return count($jobs);
    }

    /**
     * Read the JSON status file the Python worker maintains and mirror any
     * changes into the `jobs` table. Returns the merged view used by the
     * status-poll API (DB row + live status file content).
     *
     * @return array<string, mixed>|null
     */
    public function getStatus(int $jobId): ?array
    {
        $job = Job::find($jobId);

        if ($job === null) {
            return null;
        }

        $statusPath = $this->statusFilePath($jobId);
        $fileStatus = null;

        if (is_file($statusPath)) {
            $raw = file_get_contents($statusPath);
            $decoded = json_decode((string) $raw, true);
            $fileStatus = is_array($decoded) ? $decoded : null;
        }

        if ($fileStatus !== null) {
            $this->syncFromFileStatus($job, $fileStatus);
            $job = Job::find($jobId) ?? $job;
            $this->notifyIfTerminal($job);
        }

        $backgrounds = array_map(
            static fn (array $bg): array => [
                'id' => (int) $bg['id'],
                'image_url' => base_url('jobs/' . $jobId . '/image/' . basename((string) $bg['image_path'])),
                'is_selected' => (bool) $bg['is_selected'],
            ],
            Background::forJob($jobId)
        );

        $video = Video::forJob($jobId);
        $videoInfo = $video === null ? null : [
            'stream_url' => base_url('jobs/' . $jobId . '/video'),
            'download_url' => base_url('jobs/' . $jobId . '/download'),
            'duration_sec' => $video['duration_sec'] !== null ? (int) $video['duration_sec'] : null,
            'resolution' => $video['resolution'],
            'file_size_bytes' => $video['file_size_bytes'] !== null ? (int) $video['file_size_bytes'] : null,
        ];

        return [
            'job_id' => $jobId,
            'state' => $job['state'],
            'progress_percent' => (int) $job['progress_percent'],
            'eta_seconds' => $job['eta_seconds'] !== null ? (int) $job['eta_seconds'] : null,
            'error_message' => $job['error_message'],
            'title' => $job['title'],
            'channel' => $job['channel'],
            'thumbnail_url' => $job['thumbnail_url'],
            'duration_sec' => $job['duration_sec'] !== null ? (int) $job['duration_sec'] : null,
            'message' => $fileStatus['message'] ?? null,
            'logs' => $fileStatus['logs'] ?? [],
            'stages' => Job::pipelineStages(),
            'backgrounds' => $backgrounds,
            'video' => $videoInfo,
            'expired' => $job['expired_at'] !== null,
        ];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $fileStatus
     */
    private function syncFromFileStatus(array $job, array $fileStatus): void
    {
        $this->syncPythonLogs((int) $job['id'], $fileStatus['logs'] ?? []);

        $updates = [];

        if (isset($fileStatus['state']) && $fileStatus['state'] !== $job['state']) {
            $updates['state'] = $fileStatus['state'];

            // Refund the credit this job consumed on submission — this
            // fires once per job (only on the actual queued/whatever ->
            // failed transition, guarded again inside refundForJob() itself
            // against a second refund if this method somehow runs twice for
            // the same transition). Deliberately unconditional: today there
            // is no reliable way to tell "our bug" apart from "bad input"
            // failures this early in the stage pipeline, so every failure
            // refunds rather than risk charging a user for something that
            // wasn't their fault.
            if ($fileStatus['state'] === Job::STATE_FAILED && $job['user_id'] !== null) {
                Credit::refundForJob((int) $job['user_id'], (int) $job['id']);
            }
        }

        if (isset($fileStatus['progress_percent']) && (int) $fileStatus['progress_percent'] !== (int) $job['progress_percent']) {
            $updates['progress_percent'] = (int) $fileStatus['progress_percent'];
        }

        if (array_key_exists('eta_seconds', $fileStatus)) {
            $newEtaSeconds = $fileStatus['eta_seconds'] !== null ? (int) $fileStatus['eta_seconds'] : null;

            // Guard against the "unconditional" version of this that used
            // to be here: writing it on every poll regardless of whether it
            // actually changed touched `updated_at` (ON UPDATE
            // CURRENT_TIMESTAMP) on every single sync — which quietly broke
            // anything using that column to mean "when did this job last
            // meaningfully change" (e.g. notifyIfTerminal()'s bookkeeping).
            if ($newEtaSeconds !== ($job['eta_seconds'] !== null ? (int) $job['eta_seconds'] : null)) {
                $updates['eta_seconds'] = $newEtaSeconds;
            }
        }

        if (array_key_exists('error', $fileStatus)) {
            // Sync in both directions: set a new error, but also *clear* a
            // stale one — e.g. a job that failed once, got resumed (see
            // resume_job.py), and later succeeded would otherwise keep
            // showing its old error message forever, since nothing before
            // this explicitly cleared it once the retry succeeded.
            $newErrorMessage = $fileStatus['error'] !== null && $fileStatus['error'] !== ''
                ? (string) $fileStatus['error']
                : null;

            if ($newErrorMessage !== $job['error_message']) {
                $updates['error_message'] = $newErrorMessage;
            }
        }

        $metadata = $fileStatus['metadata'] ?? null;

        if (is_array($metadata)) {
            foreach (['title', 'channel', 'thumbnail_url'] as $field) {
                if (!empty($metadata[$field]) && $metadata[$field] !== $job[$field]) {
                    $updates[$field] = (string) $metadata[$field];
                }
            }

            if (!empty($metadata['duration_sec']) && (int) $metadata['duration_sec'] !== (int) $job['duration_sec']) {
                $updates['duration_sec'] = (int) $metadata['duration_sec'];
            }
        }

        if ($updates !== []) {
            Job::update((int) $job['id'], $updates);
        }

        $lyrics = $fileStatus['lyrics'] ?? null;

        if (is_array($lyrics)) {
            $this->syncLyrics((int) $job['id'], $lyrics);
        }

        $backgrounds = $fileStatus['backgrounds'] ?? null;

        if (is_array($backgrounds) && $backgrounds !== []) {
            $this->syncBackgrounds((int) $job['id'], $backgrounds);
        }

        $video = $fileStatus['video'] ?? null;

        if (is_array($video)) {
            $this->syncVideo((int) $job['id'], $video);
        }
    }

    /**
     * Called by WorkerCallbackController's upload endpoint once RunPod
     * sends back the finished video — the actual bytes, as a real file
     * upload, not through the JSON status channel applyRemoteStatus() uses.
     *
     * The "rendering_video" stage's own status push (handled by the normal
     * syncVideo() path above) already recorded resolution/duration_sec —
     * those describe the video's content, so they're still correct even
     * though they arrived from a different machine — but its file_path
     * pointed at a temp directory on RunPod's own (now-deleted) disk. This
     * corrects just that field to where the file actually landed on
     * Hostinger, creating the `videos` row instead if the status push
     * somehow hasn't arrived yet.
     */
    public function saveUploadedVideo(int $jobId, string $uploadedTmpPath): void
    {
        $jobDir = $this->jobDirectory($jobId);

        if (!is_dir($jobDir) && !mkdir($jobDir, 0775, true) && !is_dir($jobDir)) {
            throw new RuntimeException("Could not create job directory: {$jobDir}");
        }

        $destination = $jobDir . '/karaoke.mp4';

        if (!move_uploaded_file($uploadedTmpPath, $destination)) {
            throw new RuntimeException('Failed to save the uploaded video.');
        }

        $fileSize = filesize($destination);
        $existing = Video::forJob($jobId);

        if ($existing !== null) {
            Video::update((int) $existing['id'], [
                'file_path' => $destination,
                'file_size_bytes' => $fileSize !== false ? $fileSize : null,
            ]);
        } else {
            Video::create([
                'job_id' => $jobId,
                'file_path' => $destination,
                'resolution' => '1920x1080',
                'file_size_bytes' => $fileSize !== false ? $fileSize : null,
            ]);
        }
    }

    /**
     * Sends the "your video is ready" / "your video failed" email exactly
     * once per job. Called both from the HTTP status-poll endpoint (so it
     * fires immediately while someone has the progress page open) and from
     * bin/sync-jobs.php (a cron job — see docs/CONFIGURATION.md — so it
     * still fires if nobody's browser is polling, which is the entire
     * point of "we'll email you, you can close this tab").
     *
     * Only jobs created after this feature shipped are eligible — see the
     * one-time backfill in database/migrations/004_notifications_and_cleanup.sql,
     * which stamps every already-terminal job as notified so they don't all
     * suddenly email their owners the moment SMTP gets configured.
     *
     * @param array<string, mixed> $job
     */
    private function notifyIfTerminal(array $job): void
    {
        $isTerminal = in_array($job['state'], [Job::STATE_COMPLETED, Job::STATE_FAILED], true);

        if (!$isTerminal || $job['notified_at'] !== null || $job['user_id'] === null) {
            return;
        }

        $user = User::find((int) $job['user_id']);

        if ($user === null) {
            return;
        }

        $jobUrl = base_url('jobs/' . $job['id']);
        $title = $job['title'] ?? ('Job #' . $job['id']);
        $name = $user['name'] ?? 'there';

        if ($job['state'] === Job::STATE_COMPLETED) {
            $subject = 'Your karaoke video is ready';
            $body = '<p>Hi ' . e($name) . ',</p>'
                . '<p>Your karaoke video for <strong>' . e($title) . '</strong> is ready.</p>'
                . '<p><a href="' . e($jobUrl) . '">Watch and download it here</a>.</p>';
        } else {
            $subject = 'Your karaoke video generation failed';
            $body = '<p>Hi ' . e($name) . ',</p>'
                . '<p>Unfortunately, generating your karaoke video for <strong>' . e($title) . '</strong> failed.</p>'
                . '<p>If this used a credit, it\'s been automatically refunded to your account.</p>'
                . '<p><a href="' . e($jobUrl) . '">View the job</a> or head back to the homepage to try again.</p>';
        }

        $sent = (new Mailer())->send((string) $user['email'], (string) $name, $subject, $body);

        if ($sent) {
            Job::update((int) $job['id'], ['notified_at' => gmdate('Y-m-d H:i:s')]);
        }
    }

    /**
     * @param array<string, mixed> $video
     */
    private function syncVideo(int $jobId, array $video): void
    {
        if (Video::forJob($jobId) !== null) {
            return;
        }

        Video::create([
            'job_id' => $jobId,
            'file_path' => (string) ($video['file_path'] ?? ''),
            'duration_sec' => isset($video['duration_sec']) ? (int) round((float) $video['duration_sec']) : null,
            'resolution' => (string) ($video['resolution'] ?? '1920x1080'),
            'file_size_bytes' => isset($video['file_size_bytes']) ? (int) $video['file_size_bytes'] : null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $backgrounds
     */
    private function syncBackgrounds(int $jobId, array $backgrounds): void
    {
        if (Background::forJob($jobId) !== []) {
            return; // already persisted — backgrounds never change after generation
        }

        foreach ($backgrounds as $background) {
            Background::create([
                'job_id' => $jobId,
                'prompt' => (string) ($background['prompt'] ?? ''),
                'image_path' => (string) ($background['image_path'] ?? ''),
                'is_selected' => 0,
            ]);
        }
    }

    /**
     * Mirrors new lines from status.json's cumulative "logs" array into the
     * `logs` table. Stateless by design (no "already synced" marker is kept
     * anywhere) — it just compares against how many python-sourced rows this
     * job already has in the DB and inserts whatever is beyond that, so it's
     * safe to call on every poll.
     *
     * @param array<int, string> $logLines
     */
    private function syncPythonLogs(int $jobId, array $logLines): void
    {
        if ($logLines === []) {
            return;
        }

        $alreadySynced = LogEntry::countForJobSource($jobId, Logger::SOURCE_PYTHON);
        $newLines = array_slice($logLines, $alreadySynced);

        foreach ($newLines as $line) {
            $level = str_contains($line, 'ERROR:') ? Logger::LEVEL_ERROR : Logger::LEVEL_INFO;
            Logger::log($level, Logger::SOURCE_PYTHON, (string) $line, [], $jobId);
        }
    }

    /**
     * @param array<string, mixed> $lyrics
     */
    private function syncLyrics(int $jobId, array $lyrics): void
    {
        $source = $lyrics['source'] ?? 'whisperx';
        $wordsJson = json_encode($lyrics['words'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $existing = Lyrics::forJob($jobId);

        $data = [
            'job_id' => $jobId,
            'source' => in_array($source, ['lrclib', 'musixmatch', 'genius', 'whisperx'], true) ? $source : 'whisperx',
            'raw_text' => $lyrics['raw_text'] ?? '',
            'words_json' => $wordsJson,
        ];

        if ($existing === null) {
            Lyrics::create($data);
        } else {
            unset($data['job_id']);
            Lyrics::update((int) $existing['id'], $data);
        }
    }

    private function spawnWorker(int $jobId, string $youtubeUrl, bool $keepVocals, ?string $language = null): void
    {
        if ($this->runPodConfigured()) {
            $this->dispatchToRunPod($jobId, $youtubeUrl, $keepVocals, $language);

            return;
        }

        $pythonPath = Setting::get('python_path', 'python');
        $rootPath = Config::get('paths.root');
        $workerScript = Config::get('paths.python') . '/worker.py';
        $logFile = $this->jobDirectory($jobId) . '/worker.log';

        $escapedPython = escapeshellarg($pythonPath);
        $escapedScript = escapeshellarg($workerScript);
        $escapedLog = escapeshellarg($logFile);

        if (stripos(PHP_OS, 'WIN') === 0) {
            // "start /B" detaches the child so the HTTP request returns
            // immediately instead of waiting minutes for the job to finish.
            // bypass_shell=true + a hand-built "cmd /c ..." prefix is
            // deliberate: proc_open()'s default Windows behaviour is to
            // *also* wrap the command in its own "cmd /c "..."", and that
            // second layer of quoting collides with the "" empty-title
            // placeholder "start" requires, corrupting the command line
            // ("The filename, directory name, or volume label syntax is
            // incorrect."). Building the single cmd.exe layer ourselves and
            // telling PHP not to add another avoids that entirely.
            $command = sprintf(
                'cmd /c start "" /B %s %s --job-id %d --root %s > %s 2>&1',
                $escapedPython,
                $escapedScript,
                $jobId,
                escapeshellarg($rootPath),
                $escapedLog
            );
            $descriptorSpec = [];
            $process = proc_open($command, $descriptorSpec, $pipes, $rootPath, null, ['bypass_shell' => true]);
        } else {
            $command = sprintf(
                'nohup %s %s --job-id %d --root %s > %s 2>&1 &',
                $escapedPython,
                $escapedScript,
                $jobId,
                escapeshellarg($rootPath),
                $escapedLog
            );
            $process = proc_open($command, [], $pipes, $rootPath);
        }

        if (!is_resource($process)) {
            Logger::error('Failed to spawn Python worker process', ['job_id' => $jobId], $jobId, Logger::SOURCE_PHP);
            throw new RuntimeException('Could not start the processing worker.');
        }

        proc_close($process);

        Logger::info('Python worker spawned', ['job_id' => $jobId], $jobId, Logger::SOURCE_PHP);
    }

    private function runPodConfigured(): bool
    {
        return env('RUNPOD_API_KEY', '') !== '' && env('RUNPOD_ENDPOINT_ID', '') !== '';
    }

    /**
     * Remote-GPU counterpart to the local proc_open() path above: instead
     * of spawning python/worker.py as a subprocess on this machine, kicks
     * off handler.py running on RunPod's infrastructure via
     * their async job API (`/run` — not `/runsync`, since jobs here can
     * run for close to an hour, far past runsync's ~5-minute result
     * window). RunPod calls back to WorkerCallbackController as the job
     * progresses and once it's done — see docs/CONFIGURATION.md's RunPod
     * section for the full request/response shape.
     */
    private function dispatchToRunPod(int $jobId, string $youtubeUrl, bool $keepVocals, ?string $language = null): void
    {
        $config = $this->buildJobConfig($jobId, $youtubeUrl, $keepVocals, $language);

        // Neither of these means anything on a remote machine — job_dir is
        // a local path on RunPod's own (ephemeral) disk that
        // handler.py creates itself, and local backgrounds get
        // fetched by filename over HTTP instead of read from a shared
        // folder (see _download_local_backgrounds() in handler.py).
        unset($config['job_dir'], $config['local_backgrounds_path']);

        // ffmpeg_path/ffprobe_path from Settings are whatever this local
        // (Windows/WAMP) machine needs — e.g. "C:\ffmpeg\bin\ffmpeg.exe" —
        // and are meaningless on the Linux container, which has ffmpeg on
        // PATH via the Dockerfile's apt-get install. A real job hit this:
        // the config carried the Windows path straight through and
        // extract_audio failed with "No such file or directory:
        // 'C:\ffmpeg\bin\ffmpeg.exe'" on RunPod's side. handler.py's own
        // config.setdefault(...) never kicks in here since these keys
        // *are* present (just wrong), not absent — has to be overridden
        // explicitly, here, before dispatch.
        $config['ffmpeg_path'] = 'ffmpeg';
        $config['ffprobe_path'] = 'ffprobe';

        if ($config['background_source'] === 'local') {
            $config['local_background_filenames'] = $this->listLocalBackgroundFilenames();
        }

        $config['callback_base_url'] = base_url();
        $config['worker_secret'] = env('WORKER_API_SECRET', '');

        $url = 'https://api.runpod.ai/v2/' . env('RUNPOD_ENDPOINT_ID') . '/run';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . env('RUNPOD_API_KEY'),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['input' => $config], JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpStatus < 200 || $httpStatus >= 300) {
            Logger::error('Failed to dispatch job to RunPod', [
                'job_id' => $jobId,
                'http_status' => $httpStatus,
                'curl_error' => $curlError,
            ], $jobId, Logger::SOURCE_PHP);

            throw new RuntimeException('Could not start the processing worker (RunPod dispatch failed).');
        }

        Logger::info('Job dispatched to RunPod', ['job_id' => $jobId], $jobId, Logger::SOURCE_PHP);
    }

    /**
     * @return array<int, string>
     */
    private function listLocalBackgroundFilenames(): array
    {
        $folder = $this->resolvePath(Setting::get('local_backgrounds_path', 'storage/backgrounds'));

        if (!is_dir($folder)) {
            return [];
        }

        $filenames = [];

        foreach (scandir($folder) as $entry) {
            if (preg_match('/\.(png|jpe?g|webp)$/i', $entry) === 1) {
                $filenames[] = $entry;
            }
        }

        return $filenames;
    }
}
