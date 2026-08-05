<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;

/**
 * Static, no-DB pages — legal/compliance content required for payment
 * gateway (Cashfree) onboarding review, plus the Contact page it expects to
 * find linked from them.
 */
final class PageController extends Controller
{
    public function about(Request $request): void
    {
        $this->view('pages/about', ['pageTitle' => 'About Us']);
    }

    public function privacy(Request $request): void
    {
        $this->view('pages/privacy', ['pageTitle' => 'Privacy Policy']);
    }

    public function terms(Request $request): void
    {
        $this->view('pages/terms', ['pageTitle' => 'Terms of Service']);
    }

    public function refundPolicy(Request $request): void
    {
        $this->view('pages/refund-policy', ['pageTitle' => 'Refund & Cancellation Policy']);
    }

    public function contact(Request $request): void
    {
        $this->view('pages/contact', ['pageTitle' => 'Contact Us']);
    }
}
