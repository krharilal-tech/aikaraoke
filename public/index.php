<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Router;

$rootPath = dirname(__DIR__);

Application::boot($rootPath);

// Works whether the app is served from a root .htaccess forward (e.g. WAMP's
// default www/aikaraoke/ setup, where SCRIPT_NAME still carries the
// "/aikaraoke/public" prefix) or from a vhost whose DocumentRoot points
// straight at /public (production — no prefix at all).
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php'));
$basePath = preg_replace('#/public$#', '', $scriptDir);
$basePath = $basePath === '/' ? '' : $basePath;

$router = new Router($basePath);
require $rootPath . '/routes/web.php';

Application::run($router);
