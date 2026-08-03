<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'AI Karaoke Maker'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost'), '/'),
    'key' => Env::get('APP_KEY', ''),
    'timezone' => 'UTC',
    'session_name' => Env::get('SESSION_NAME', 'aikaraoke_session'),
    'session_lifetime' => (int) Env::get('SESSION_LIFETIME', 7200),
];
