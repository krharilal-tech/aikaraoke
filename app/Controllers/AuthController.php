<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Models\Credit;
use App\Models\User;
use App\Services\GoogleOAuthService;
use Throwable;

final class AuthController extends Controller
{
    private const SIGNUP_BONUS_CREDITS = 1;

    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect(base_url('/'));
        }

        $this->view('auth/login', [
            'pageTitle' => 'Sign In',
            'next' => (string) $request->input('next', ''),
            'error' => null,
            'googleEnabled' => (new GoogleOAuthService())->isConfigured(),
        ], layout: 'layouts/blank');
    }

    public function showRegister(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect(base_url('/'));
        }

        $this->view('auth/register', [
            'pageTitle' => 'Create Account',
            'next' => (string) $request->input('next', ''),
            'error' => null,
            'googleEnabled' => (new GoogleOAuthService())->isConfigured(),
        ], layout: 'layouts/blank');
    }

    public function register(Request $request): void
    {
        $token = $request->input('_csrf');

        if (!Csrf::verify(is_string($token) ? $token : null)) {
            $this->renderRegisterError('Your session expired. Please try again.');
        }

        if (RateLimiter::tooManyAttempts('register:' . $request->ip(), 5, 300)) {
            $this->renderRegisterError('Too many attempts. Please wait a few minutes and try again.');
        }

        $name = trim((string) $request->input('name', ''));
        $email = Sanitizer::email($request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($name === '') {
            $this->renderRegisterError('Please enter your name.');
        }

        if ($email === null) {
            $this->renderRegisterError('Please enter a valid email address.');
        }

        if (strlen($password) < 8) {
            $this->renderRegisterError('Password must be at least 8 characters.');
        }

        if (User::findByEmail($email) !== null) {
            $this->renderRegisterError('An account with that email already exists. Try signing in instead.');
        }

        $userId = (int) User::createUser($email, $password, 'user', $name);
        Credit::grant($userId, self::SIGNUP_BONUS_CREDITS, Credit::REASON_SIGNUP_BONUS);

        Auth::login($userId);

        $this->redirect($this->resolveNext((string) $request->input('next', '')));
    }

    /**
     * Kicks off the OAuth flow: stash a random state value in the session
     * (compared back in the callback to block CSRF) and send the browser to
     * Google's consent screen.
     */
    public function redirectToGoogle(Request $request): void
    {
        $google = new GoogleOAuthService();

        if (!$google->isConfigured()) {
            $this->renderLoginError('Google sign-in is not configured on this server.');
        }

        $state = bin2hex(random_bytes(16));
        Session::set('_google_oauth_state', $state);
        Session::set('_google_oauth_next', (string) $request->input('next', ''));

        $this->redirect($google->authorizeUrl($state));
    }

    public function handleGoogleCallback(Request $request): void
    {
        $google = new GoogleOAuthService();

        $expectedState = Session::get('_google_oauth_state');
        Session::remove('_google_oauth_state');

        $state = (string) $request->input('state', '');

        if ($expectedState === null || !hash_equals((string) $expectedState, $state)) {
            $this->renderLoginError('Google sign-in failed (invalid state). Please try again.');
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            $this->renderLoginError('Google sign-in was cancelled.');
        }

        try {
            $profile = $google->handleCallback($code);
        } catch (Throwable $e) {
            Logger::error('Google OAuth callback failed', ['error' => $e->getMessage()]);
            $this->renderLoginError('Google sign-in failed. Please try again.');

            return;
        }

        $user = User::findByGoogleId($profile['google_id']);
        $isNewUser = false;

        if ($user === null) {
            // Same email already registered the traditional way — link this
            // Google identity to that existing account instead of creating
            // a duplicate, so "sign in with Google" and "sign in with
            // password" both land on the same account for that email.
            $existingByEmail = User::findByEmail($profile['email']);

            if ($existingByEmail !== null) {
                User::linkGoogleId((int) $existingByEmail['id'], $profile['google_id']);
                $user = User::find((int) $existingByEmail['id']);
            } else {
                $userId = (int) User::createGoogleUser($profile['email'], $profile['google_id'], $profile['name']);
                $user = User::find($userId);
                $isNewUser = true;
            }
        }

        if ($user === null) {
            $this->renderLoginError('Google sign-in failed. Please try again.');

            return;
        }

        if ($isNewUser) {
            Credit::grant((int) $user['id'], self::SIGNUP_BONUS_CREDITS, Credit::REASON_SIGNUP_BONUS);
        }

        Auth::login((int) $user['id']);

        $next = (string) Session::get('_google_oauth_next', '');
        Session::remove('_google_oauth_next');

        $this->redirect($this->resolveNext($next));
    }

    public function login(Request $request): void
    {
        $token = $request->input('_csrf');

        if (!Csrf::verify(is_string($token) ? $token : null)) {
            $this->renderLoginError('Your session expired. Please try again.');
        }

        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5, 300)) {
            $this->renderLoginError('Too many attempts. Please wait a few minutes and try again.');
        }

        $email = Sanitizer::email($request->input('email', ''));
        $password = (string) $request->input('password', '');

        $user = $email !== null ? User::findByEmail($email) : null;

        if ($user === null || !User::verifyPassword($user, $password)) {
            $this->renderLoginError('Invalid email or password.');
        }

        Auth::login((int) $user['id']);

        $this->redirect($this->resolveNext((string) $request->input('next', '')));
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $this->redirect(base_url('login'));
    }

    /**
     * A "next" value only ever comes from our own redirect (the router's
     * denyUnauthenticated(), or a form's hidden field we rendered
     * ourselves) — but it's still attacker-reachable via a crafted login
     * URL, so only a same-site relative path is honored; anything else
     * (an absolute URL, protocol-relative "//evil.com") falls back to home
     * rather than turning login into an open redirect.
     */
    private function resolveNext(string $next): string
    {
        return ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//'))
            ? base_url(ltrim($next, '/'))
            : base_url('/');
    }

    private function renderLoginError(string $message): never
    {
        $this->view('auth/login', [
            'pageTitle' => 'Sign In',
            'next' => '',
            'error' => $message,
            'googleEnabled' => (new GoogleOAuthService())->isConfigured(),
        ], layout: 'layouts/blank');

        exit;
    }

    private function renderRegisterError(string $message): never
    {
        $this->view('auth/register', [
            'pageTitle' => 'Create Account',
            'next' => '',
            'error' => $message,
            'googleEnabled' => (new GoogleOAuthService())->isConfigured(),
        ], layout: 'layouts/blank');

        exit;
    }
}
