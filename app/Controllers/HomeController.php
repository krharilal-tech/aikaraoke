<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('home/index', [
            'pageTitle' => 'Home',
            'pageScript' => 'js/home.js',
            'maxDurationSeconds' => (int) Setting::get('max_video_length_seconds', '600'),
        ]);
    }

    public function pricing(Request $request): void
    {
        $userId = Auth::id();
        $user = $userId !== null ? User::find($userId) : null;

        $this->view('pricing/index', [
            'pageTitle' => 'Pricing',
            'pageScript' => 'js/pricing.js',
            'packages' => Package::activeOrdered(),
            'userPhone' => $user['phone'] ?? null,
        ]);
    }
}
