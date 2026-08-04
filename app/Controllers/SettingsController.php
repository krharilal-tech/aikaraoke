<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\EnvWriter;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Models\Setting;

final class SettingsController extends Controller
{
    /** Keys that are secrets and belong in .env, never in the settings table. */
    private const ENV_KEYS = ['OPENAI_API_KEY', 'MUSIXMATCH_API_KEY', 'GENIUS_ACCESS_TOKEN'];

    /** Keys stored in the `settings` table, mapped to their sanitizer. */
    private const DB_KEYS = [
        'image_model',
        'prompt_model',
        'background_source',
        'local_backgrounds_path',
        'ffmpeg_path',
        'ffprobe_path',
        'python_path',
        'yt_dlp_path',
        'demucs_model',
        'whisperx_model',
        'transcription_language',
        'max_video_length_seconds',
        'temp_storage_path',
        'youtube_cookies',
    ];

    /** cookies.txt content routinely runs several KB — everything else here is a short path/model name. */
    private const YOUTUBE_COOKIES_MAX_LENGTH = 65000;

    /** Valid values for transcription_language — kept in sync with the <select> in settings/index.php. */
    private const TRANSCRIPTION_LANGUAGES = ['auto', 'ta', 'ml', 'hi', 'en'];

    public function index(Request $request): void
    {
        $this->view('settings/index', [
            'pageTitle' => 'Settings',
            'settings' => Setting::allAsMap(),
            'openaiKeySet' => env('OPENAI_API_KEY', '') !== '',
            'musixmatchKeySet' => env('MUSIXMATCH_API_KEY', '') !== '',
            'geniusKeySet' => env('GENIUS_ACCESS_TOKEN', '') !== '',
            'flash' => flash_message('settings_status'),
        ]);
    }

    public function update(Request $request): void
    {
        $this->requireCsrf($request);

        $dbValues = [];

        foreach (self::DB_KEYS as $key) {
            $raw = $request->input($key);

            if ($raw === null) {
                continue;
            }

            $dbValues[$key] = Sanitizer::string($raw, $key === 'youtube_cookies' ? self::YOUTUBE_COOKIES_MAX_LENGTH : 500);
        }

        if (isset($dbValues['max_video_length_seconds'])) {
            $dbValues['max_video_length_seconds'] = (string) max(30, Sanitizer::int($dbValues['max_video_length_seconds'], 600));
        }

        if (isset($dbValues['background_source']) && !in_array($dbValues['background_source'], ['openai', 'local'], true)) {
            $dbValues['background_source'] = 'openai';
        }

        if (isset($dbValues['transcription_language']) && !in_array($dbValues['transcription_language'], self::TRANSCRIPTION_LANGUAGES, true)) {
            $dbValues['transcription_language'] = 'auto';
        }

        Setting::setMany($dbValues);

        $envValues = [];

        foreach (self::ENV_KEYS as $key) {
            $raw = $request->input($key);

            if ($raw === null || $raw === '') {
                continue;
            }

            $envValues[$key] = Sanitizer::string($raw, 500);
        }

        if ($envValues !== []) {
            EnvWriter::set(Config::get('paths.root') . '/.env', $envValues);
        }

        Logger::info('Settings updated', ['keys' => [...array_keys($dbValues), ...array_keys($envValues)]]);

        Session::flash('settings_status', ['type' => 'success', 'message' => 'Settings saved successfully.']);

        $this->redirect(base_url('settings'));
    }
}
