<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\Setting;
use App\Services\JobService;

/**
 * Endpoints the RunPod worker (python/handler.py) calls back into —
 * never used by a logged-in user's browser, so these don't go through
 * Auth::check()/session cookies at all. Authenticated instead by a shared
 * secret (WORKER_API_SECRET in .env) every RunPod job carries as part of
 * its input and sends back on every request here. See "RunPod Serverless
 * integration" in docs/CONFIGURATION.md for the full request/response
 * contract this implements.
 */
final class WorkerCallbackController extends Controller
{
    private JobService $jobService;

    public function __construct()
    {
        $this->jobService = new JobService();
    }

    /**
     * The remote counterpart to a local worker writing status.json
     * directly — see JobService::applyRemoteStatus().
     */
    public function status(Request $request): void
    {
        $this->requireWorkerSecret($request);

        $jobId = Sanitizer::int($request->param('id'));
        $payload = $request->all();

        $this->jobService->applyRemoteStatus($jobId, $payload);

        $this->json(['success' => true]);
    }

    /**
     * Receives the finished video as a real file upload (not JSON) once
     * RunPod's render stage completes — see JobService::saveUploadedVideo().
     */
    public function upload(Request $request): void
    {
        $this->requireWorkerSecret($request);

        $jobId = Sanitizer::int($request->param('id'));

        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'No video file received.'], 422);
        }

        $this->jobService->saveUploadedVideo($jobId, $_FILES['video']['tmp_name']);

        $this->json(['success' => true]);
    }

    /**
     * Serves one image from the Local Backgrounds Folder so
     * handler.py can fetch the same candidates a local job would
     * have picked from disk directly — see
     * _download_local_backgrounds() in handler.py.
     */
    public function backgroundImage(Request $request): void
    {
        $this->requireWorkerSecret($request);

        $filename = Sanitizer::filePathSegment((string) $request->param('filename'));

        if ($filename === '' || !preg_match('/\.(png|jpe?g|webp)$/i', $filename)) {
            Response::notFound();
        }

        $folder = Setting::get('local_backgrounds_path', 'storage/backgrounds');
        $isAbsolute = preg_match('#^([A-Za-z]:[\\\\/]|/)#', $folder) === 1;
        $folder = $isAbsolute ? $folder : Config::get('paths.root') . '/' . $folder;

        $realFolder = realpath($folder);
        $realPath = realpath($folder . '/' . $filename);

        if ($realFolder === false || $realPath === false || !str_starts_with($realPath, $realFolder)) {
            Response::notFound();
        }

        $mime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($realPath));
        readfile($realPath);
        exit;
    }

    private function requireWorkerSecret(Request $request): void
    {
        $expected = env('WORKER_API_SECRET', '');
        $provided = $request->header('X-Worker-Secret');

        if ($expected === '' || $provided === null || !hash_equals($expected, $provided)) {
            Response::notFound();
        }
    }
}
