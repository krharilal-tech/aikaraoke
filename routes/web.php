<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\JobController;
use App\Controllers\PageController;
use App\Controllers\SettingsController;
use App\Controllers\WorkerCallbackController;
use App\Core\Router;

/** @var Router $router */

$router->get('/login', [AuthController::class, 'showLogin'], auth: false);
$router->post('/login', [AuthController::class, 'login'], auth: false);
$router->get('/register', [AuthController::class, 'showRegister'], auth: false);
$router->post('/register', [AuthController::class, 'register'], auth: false);
$router->get('/auth/google', [AuthController::class, 'redirectToGoogle'], auth: false);
$router->get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'], auth: false);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/', [HomeController::class, 'index'], auth: false);
$router->get('/pricing', [HomeController::class, 'pricing'], auth: false);

$router->get('/about', [PageController::class, 'about'], auth: false);
$router->get('/privacy', [PageController::class, 'privacy'], auth: false);
$router->get('/terms', [PageController::class, 'terms'], auth: false);
$router->get('/refund-policy', [PageController::class, 'refundPolicy'], auth: false);
$router->get('/contact', [PageController::class, 'contact'], auth: false);

$router->get('/admin/users', [AdminController::class, 'users']);
$router->post('/admin/users/{id}/credits', [AdminController::class, 'adjustCredits']);
$router->get('/admin/packages', [AdminController::class, 'packages']);
$router->post('/admin/packages', [AdminController::class, 'createPackage']);
$router->post('/admin/packages/{id}', [AdminController::class, 'updatePackage']);
$router->post('/admin/packages/{id}/toggle', [AdminController::class, 'togglePackage']);

$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings', [SettingsController::class, 'update']);

$router->get('/jobs', [JobController::class, 'index']);
$router->post('/jobs', [JobController::class, 'create']);
$router->get('/jobs/{id}', [JobController::class, 'show']);
$router->get('/jobs/{id}/image/{filename}', [JobController::class, 'image']);
$router->get('/jobs/{id}/video', [JobController::class, 'video']);
$router->get('/jobs/{id}/download', [JobController::class, 'download']);
$router->get('/api/jobs/{id}/status', [JobController::class, 'status']);

// Called by the RunPod worker, not by a user's browser — authenticated via
// a shared secret header instead of a session, see
// WorkerCallbackController::requireWorkerSecret().
$router->post('/api/worker/jobs/{id}/status', [WorkerCallbackController::class, 'status'], auth: false);
$router->post('/api/worker/jobs/{id}/upload', [WorkerCallbackController::class, 'upload'], auth: false);
$router->get('/api/worker/backgrounds/{filename}', [WorkerCallbackController::class, 'backgroundImage'], auth: false);
