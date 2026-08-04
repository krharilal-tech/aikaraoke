<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\Credit;
use App\Models\Job;
use App\Models\Setting;
use App\Models\Video;
use App\Services\JobService;
use Throwable;

final class JobController extends Controller
{
    /** Kept in sync with SettingsController::TRANSCRIPTION_LANGUAGES and the <select> in Views/home/index.php. */
    private const LANGUAGES = ['auto', 'ta', 'ml', 'hi', 'en'];

    private JobService $jobService;

    public function __construct()
    {
        $this->jobService = new JobService();
    }

    public function index(Request $request): void
    {
        $jobs = Job::all('created_at DESC');

        $this->view('jobs/index', [
            'pageTitle' => 'My Videos',
            'jobs' => $jobs,
        ]);
    }

    public function create(Request $request): void
    {
        $this->requireCsrf($request);
        $this->rateLimit($request, 'job_create', 10, 600);

        $userId = Auth::id();

        // 402 (not 422/403) so the frontend can tell "you're out of
        // credits, go buy more" apart from a validation or auth failure and
        // redirect to /pricing instead of showing an inline form error.
        if ($userId !== null && Credit::balance($userId) <= 0) {
            $this->json(['success' => false, 'message' => 'You are out of credits. Please purchase more to continue.'], 402);
        }

        $youtubeUrl = trim((string) $request->input('youtube_url', ''));
        $keepVocals = (bool) $request->input('keep_vocals', false);
        $language = (string) $request->input('language', 'auto');

        if (!in_array($language, self::LANGUAGES, true)) {
            $language = 'auto';
        }

        if (!Sanitizer::isYoutubeUrl($youtubeUrl)) {
            $this->json(['success' => false, 'message' => 'Please provide a valid YouTube video URL.'], 422);
        }

        $maxSeconds = (int) Setting::get('max_video_length_seconds', '600');

        try {
            // 'auto' means "use whatever Settings has configured" — null here
            // rather than the literal string keeps buildJobConfig() from
            // needing to special-case it twice.
            $job = $this->jobService->create($youtubeUrl, $keepVocals, $userId, $language === 'auto' ? null : $language);
        } catch (Throwable $e) {
            Logger::error('Failed to create job', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'message' => 'Could not start the job. Please try again.'], 500);
        }

        if ($userId !== null) {
            Credit::consumeForJob($userId, (int) $job['id']);
        }

        $this->json([
            'success' => true,
            'job_id' => (int) $job['id'],
            'max_duration_seconds' => $maxSeconds,
        ]);
    }

    public function show(Request $request): void
    {
        $id = Sanitizer::int($request->param('id'));
        $job = Job::find($id);

        if ($job === null) {
            http_response_code(404);
            $this->view('jobs/not-found', ['pageTitle' => 'Job Not Found']);

            return;
        }

        $this->view('jobs/show', [
            'pageTitle' => $job['title'] ?? 'Processing',
            'pageScript' => 'js/job-progress.js',
            'job' => $job,
            'stages' => Job::pipelineStages(),
        ]);
    }

    public function status(Request $request): void
    {
        $id = Sanitizer::int($request->param('id'));
        $status = $this->jobService->getStatus($id);

        if ($status === null) {
            $this->json(['success' => false, 'message' => 'Job not found.'], 404);
        }

        $this->json(['success' => true, ...$status]);
    }

    /**
     * Streams a generated background image from storage/jobs/{id}/ — that
     * directory is outside the webroot on purpose, so this is the only way
     * the browser can reach these files.
     */
    public function image(Request $request): void
    {
        $jobId = Sanitizer::int($request->param('id'));
        $filename = Sanitizer::filePathSegment((string) $request->param('filename'));

        if ($filename === '' || !preg_match('/\.(png|jpe?g|webp)$/i', $filename)) {
            Response::notFound();
        }

        $path = Config::get('paths.jobs') . '/' . $jobId . '/' . $filename;
        $realJobDir = realpath(Config::get('paths.jobs') . '/' . $jobId);
        $realPath = realpath($path);

        if ($realPath === false || $realJobDir === false || !str_starts_with($realPath, $realJobDir)) {
            Response::notFound();
        }

        $mime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . filesize($realPath));
        readfile($realPath);
        exit;
    }

    /**
     * Inline video preview — supports HTTP Range requests so the HTML5
     * <video> element can seek without downloading the whole file first.
     */
    public function video(Request $request): void
    {
        $jobId = Sanitizer::int($request->param('id'));
        $this->streamVideo($jobId, inline: true);
    }

    public function download(Request $request): void
    {
        $jobId = Sanitizer::int($request->param('id'));
        $this->streamVideo($jobId, inline: false);
    }

    private function streamVideo(int $jobId, bool $inline): void
    {
        $video = Video::forJob($jobId);

        if ($video === null || !is_file($video['file_path'])) {
            Response::notFound('Video not found.');
        }

        $path = $video['file_path'];
        $size = filesize($path);
        $start = 0;
        $end = $size - 1;

        header('Accept-Ranges: bytes');
        header('Content-Type: video/mp4');

        $disposition = $inline ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="karaoke-' . $jobId . '.mp4"');

        $range = $_SERVER['HTTP_RANGE'] ?? null;

        if ($range !== null && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches) === 1) {
            $start = $matches[1] === '' ? 0 : (int) $matches[1];
            $end = $matches[2] === '' ? $size - 1 : (int) $matches[2];
            $end = min($end, $size - 1);

            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }

        header('Content-Length: ' . ($end - $start + 1));

        $handle = fopen($path, 'rb');
        fseek($handle, $start);
        $remaining = $end - $start + 1;
        $chunkSize = 1024 * 1024;

        while ($remaining > 0 && !feof($handle)) {
            $read = (int) min($chunkSize, $remaining);
            echo fread($handle, $read);
            flush();
            $remaining -= $read;
        }

        fclose($handle);
        exit;
    }
}
