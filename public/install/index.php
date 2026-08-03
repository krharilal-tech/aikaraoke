<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Database;
use App\Core\EnvWriter;
use App\Core\Sanitizer;
use App\Core\Session;

$rootPath = dirname(__DIR__, 2);

Application::boot($rootPath);

$envPath = $rootPath . '/.env';
$envExists = is_file($envPath);

if (!$envExists && is_file($rootPath . '/.env.example')) {
    copy($rootPath . '/.env.example', $envPath);
}

// Once an admin account exists, this wizard must never run again — leaving
// it live would let anyone who finds the URL repoint the database or plant
// a rogue admin user. Only step 3 (a static "you're done" message) stays
// reachable so a stray bookmark doesn't just 404.
function ak_already_installed(): bool
{
    try {
        $row = Database::instance()->fetchOne('SELECT COUNT(*) AS c FROM users');

        return $row !== null && (int) $row['c'] > 0;
    } catch (Throwable) {
        return false;
    }
}

if (ak_already_installed() && ($_GET['step'] ?? '1') !== '3') {
    http_response_code(403);
    ?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<title>Already Installed</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<main class="app-main"><section class="container py-5 text-center">
  <div class="glass-card p-5 mx-auto" style="max-width:32rem;">
    <i class="bi bi-shield-lock text-warning" style="font-size:2.5rem;"></i>
    <h1 class="h4 fw-bold mt-3">Already Installed</h1>
    <p class="text-secondary">AI Karaoke Maker is already set up. The install wizard is disabled to protect your data.</p>
    <a href="../login" class="gradient-btn btn btn-lg mt-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</a>
  </div>
</section></main>
</body>
</html>
    <?php
    exit;
}

/**
 * @return array<int, array{label: string, ok: bool, detail: string, critical: bool}>
 */
function ak_run_requirement_checks(string $rootPath): array
{
    $checks = [];

    $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
    $checks[] = ['label' => 'PHP version >= 8.1', 'ok' => $phpOk, 'detail' => PHP_VERSION, 'critical' => true];

    foreach (['pdo_mysql', 'mbstring', 'json', 'fileinfo'] as $ext) {
        $checks[] = [
            'label' => "PHP extension: {$ext}",
            'ok' => extension_loaded($ext),
            'detail' => extension_loaded($ext) ? 'loaded' : 'missing',
            'critical' => true,
        ];
    }

    $storageWritable = is_writable($rootPath . '/storage');
    $checks[] = [
        'label' => 'storage/ is writable',
        'ok' => $storageWritable,
        'detail' => $storageWritable ? 'writable' : 'NOT writable — check folder permissions',
        'critical' => true,
    ];

    $envWritable = is_writable($rootPath . '/.env') || is_writable($rootPath);
    $checks[] = [
        'label' => '.env is writable',
        'ok' => $envWritable,
        'detail' => $envWritable ? 'writable' : 'NOT writable',
        'critical' => true,
    ];

    $pythonVersion = ak_shell_version('python --version 2>&1');
    $checks[] = [
        'label' => 'Python 3 available',
        'ok' => $pythonVersion !== null,
        'detail' => $pythonVersion ?? 'not found on PATH — required before running jobs',
        'critical' => false,
    ];

    $ffmpegVersion = ak_shell_version('ffmpeg -version 2>&1');
    $checks[] = [
        'label' => 'FFmpeg available',
        'ok' => $ffmpegVersion !== null,
        'detail' => $ffmpegVersion ?? 'not found on PATH — required before rendering video',
        'critical' => false,
    ];

    $ytDlpVersion = ak_shell_version('yt-dlp --version 2>&1');
    $checks[] = [
        'label' => 'yt-dlp available',
        'ok' => $ytDlpVersion !== null,
        'detail' => $ytDlpVersion ?? 'not found — install via python/requirements.txt',
        'critical' => false,
    ];

    $demucsInstalled = ak_shell_ok('python -m demucs --help 2>&1');
    $checks[] = [
        'label' => 'Demucs installed',
        'ok' => $demucsInstalled,
        'detail' => $demucsInstalled ? 'available' : 'not found — install via python/requirements.txt',
        'critical' => false,
    ];

    $whisperxInstalled = ak_shell_ok('python -c "import whisperx" 2>&1');
    $checks[] = [
        'label' => 'WhisperX installed',
        'ok' => $whisperxInstalled,
        'detail' => $whisperxInstalled ? 'available' : 'not found — install via python/requirements.txt',
        'critical' => false,
    ];

    return $checks;
}

function ak_shell_available(): bool
{
    return function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
}

function ak_shell_version(string $command): ?string
{
    if (!ak_shell_available()) {
        return null;
    }

    // Windows cmd.exe only runs the part after "&&" if $command exited 0, so
    // the AK_OK marker doubles as a reliable success signal (shell_exec()
    // itself has no way to report the child's exit code).
    $output = @shell_exec($command . ' && echo AK_OK');

    if ($output === null || !str_contains($output, 'AK_OK')) {
        return null;
    }

    $withoutMarker = trim(str_replace('AK_OK', '', $output));
    $firstLine = trim(explode("\n", $withoutMarker)[0] ?? '');

    return $firstLine !== '' ? $firstLine : 'available';
}

function ak_shell_ok(string $command): bool
{
    if (!ak_shell_available()) {
        return false;
    }

    $output = @shell_exec($command . ' && echo AK_OK');

    return $output !== null && str_contains($output, 'AK_OK');
}

$step = $_GET['step'] ?? '1';
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '2') {
    $dbHost = Sanitizer::string($_POST['db_host'] ?? '127.0.0.1', 255);
    $dbPort = Sanitizer::int($_POST['db_port'] ?? 3306, 3306);
    $dbName = Sanitizer::string($_POST['db_database'] ?? 'aikaraoke', 64);
    $dbUser = Sanitizer::string($_POST['db_username'] ?? 'root', 255);
    $dbPass = (string) ($_POST['db_password'] ?? '');
    $appName = Sanitizer::string($_POST['app_name'] ?? 'AI Karaoke Maker', 255);
    $appUrl = Sanitizer::string($_POST['app_url'] ?? '', 255);
    $adminEmail = filter_var(trim((string) ($_POST['admin_email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $adminPasswordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');

    if ($dbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        $errors[] = 'Database name may only contain letters, numbers, and underscores.';
    }

    if ($adminEmail === false) {
        $errors[] = 'Please provide a valid admin email address.';
    }

    if (strlen($adminPassword) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    } elseif ($adminPassword !== $adminPasswordConfirm) {
        $errors[] = 'Admin password and confirmation do not match.';
    }

    if ($errors === []) {
        try {
            EnvWriter::set($envPath, [
                'APP_NAME' => $appName,
                'APP_URL' => rtrim($appUrl, '/'),
                'APP_KEY' => bin2hex(random_bytes(16)),
                'DB_HOST' => $dbHost,
                'DB_PORT' => (string) $dbPort,
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $dbUser,
                'DB_PASSWORD' => $dbPass,
            ]);

            // Connect using the values just submitted (not App\Core\Config, which
            // already cached the pre-install .env values earlier in this request).
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            $migrationSql = file_get_contents($rootPath . '/database/migrations/001_create_tables.sql');

            // Strip full-line "--" comments before splitting on ";" — a comment
            // containing a literal semicolon would otherwise be torn in half.
            $sqlLines = array_filter(
                explode("\n", $migrationSql),
                static fn (string $line): bool => !str_starts_with(trim($line), '--')
            );
            $migrationSql = implode("\n", $sqlLines);

            foreach (array_filter(array_map('trim', explode(';', $migrationSql))) as $statement) {
                if ($statement === '') {
                    continue;
                }

                $pdo->exec($statement);
            }

            $insertAdmin = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
            $insertAdmin->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);

            Session::set('install_step2_done', true);
            header('Location: ?step=3');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

$checks = ak_run_requirement_checks($rootPath);
$criticalFailed = count(array_filter($checks, static fn (array $c): bool => $c['critical'] && !$c['ok']));

?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install &mdash; AI Karaoke Maker</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<main class="app-main">
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="text-center mb-4">
        <span class="brand-icon d-inline-flex mb-2"><i class="bi bi-mic-fill"></i></span>
        <h1 class="h3 fw-bold">AI Karaoke Maker &mdash; Install Wizard</h1>
        <p class="text-secondary">Step <?= e($step) ?> of 3</p>
      </div>

      <?php if ($step === '1'): ?>
        <div class="glass-card p-4 p-md-5">
          <h5 class="fw-bold mb-3">1. Environment Checks</h5>
          <ul class="list-group list-group-flush mb-4">
            <?php foreach ($checks as $check): ?>
              <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center border-secondary-subtle">
                <span>
                  <i class="bi <?= $check['ok'] ? 'bi-check-circle-fill text-success' : ($check['critical'] ? 'bi-x-circle-fill text-danger' : 'bi-exclamation-circle-fill text-warning') ?> me-2"></i>
                  <?= e($check['label']) ?>
                </span>
                <span class="text-secondary small"><?= e($check['detail']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($criticalFailed > 0): ?>
            <div class="alert alert-danger">Please resolve the critical issues above before continuing.</div>
          <?php else: ?>
            <div class="alert alert-info small">
              Python/FFmpeg/Demucs/WhisperX are only required once you start generating videos.
              They can be installed later via <code>python/setup.ps1</code> — see the Installation Guide.
            </div>
          <?php endif; ?>
          <a class="gradient-btn btn btn-lg <?= $criticalFailed > 0 ? 'disabled' : '' ?>" href="?step=2">Continue <i class="bi bi-arrow-right ms-1"></i></a>
        </div>

      <?php elseif ($step === '2'): ?>
        <div class="glass-card p-4 p-md-5">
          <h5 class="fw-bold mb-3">2. Application &amp; Database</h5>
          <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
          <?php endforeach; ?>
          <form method="post" action="?step=2">
            <div class="mb-3">
              <label class="form-label">Application Name</label>
              <input type="text" name="app_name" class="form-control form-control-ak" value="AI Karaoke Maker" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Application URL</label>
              <input type="url" name="app_url" class="form-control form-control-ak" value="http://localhost/aikaraoke" required>
            </div>
            <hr class="border-secondary-subtle">
            <div class="row">
              <div class="col-md-8 mb-3">
                <label class="form-label">Database Host</label>
                <input type="text" name="db_host" class="form-control form-control-ak" value="127.0.0.1" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Port</label>
                <input type="number" name="db_port" class="form-control form-control-ak" value="3306" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Database Name</label>
                <input type="text" name="db_database" class="form-control form-control-ak" value="aikaraoke" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Database Username</label>
                <input type="text" name="db_username" class="form-control form-control-ak" value="root" required>
              </div>
              <div class="col-12 mb-3">
                <label class="form-label">Database Password</label>
                <input type="password" name="db_password" class="form-control form-control-ak" value="">
              </div>
            </div>
            <hr class="border-secondary-subtle">
            <p class="text-secondary small mb-3">Create the admin account you'll use to sign in.</p>
            <div class="row">
              <div class="col-12 mb-3">
                <label class="form-label">Admin Email</label>
                <input type="email" name="admin_email" class="form-control form-control-ak" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Admin Password</label>
                <input type="password" name="admin_password" class="form-control form-control-ak" minlength="8" required>
              </div>
              <div class="col-md-6 mb-4">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="admin_password_confirm" class="form-control form-control-ak" minlength="8" required>
              </div>
            </div>
            <button type="submit" class="gradient-btn btn btn-lg"><i class="bi bi-database-check me-2"></i>Create Database &amp; Install</button>
          </form>
        </div>

      <?php elseif ($step === '3'): ?>
        <div class="glass-card p-4 p-md-5 text-center">
          <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
          <h5 class="fw-bold mt-3 mb-2">Installation Complete</h5>
          <p class="text-secondary mb-4">
            The database and your admin account have been created. Sign in, then add your OpenAI API key on the
            Settings page and install the Python AI tooling (see <code>python/README.md</code>) before generating videos.
          </p>
          <div class="d-flex gap-2 justify-content-center">
            <a href="../login" class="gradient-btn btn btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
</main>
</body>
</html>
