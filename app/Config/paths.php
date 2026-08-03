<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

return [
    'root' => $root,
    'app' => $root . '/app',
    'storage' => $root . '/storage',
    'jobs' => $root . '/storage/jobs',
    'logs' => $root . '/storage/logs',
    'uploads' => $root . '/storage/uploads',
    'tmp' => $root . '/storage/tmp',
    'python' => $root . '/python',
    'database' => $root . '/database',
    'public' => $root . '/public',
];
