<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Minimal "Sign in with Google" via the standard OAuth 2.0 authorization-code
 * flow — hand-rolled with cURL rather than pulling in Google's official PHP
 * client (google/apiclient), which drags in a large dependency tree for what
 * is, at the end of the day, two HTTP calls (code -> token, token ->
 * userinfo). Matches this app's existing "no framework, minimal deps" style.
 *
 * Needs GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI in
 * .env — see docs/CONFIGURATION.md for how to obtain them from Google Cloud
 * Console.
 */
final class GoogleOAuthService
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function isConfigured(): bool
    {
        return env('GOOGLE_CLIENT_ID', '') !== '' && env('GOOGLE_CLIENT_SECRET', '') !== '';
    }

    /**
     * $state is an opaque, unguessable value the caller generates and stores
     * in the session beforehand — returned unchanged in the callback so it
     * can be compared, which is what prevents a CSRF attack where a
     * third-party site tricks a victim's browser into completing an
     * attacker-initiated OAuth flow.
     */
    public function authorizeUrl(string $state): string
    {
        $params = [
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI', ''),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ];

        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * Exchanges the one-time authorization code for tokens, then fetches the
     * profile. Returns ['email' => ..., 'google_id' => ..., 'name' => ...]
     * or throws RuntimeException with a message safe to log (never includes
     * the code/token themselves).
     *
     * @return array{email: string, google_id: string, name: ?string}
     */
    public function handleCallback(string $code): array
    {
        $tokenResponse = $this->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI', ''),
            'grant_type' => 'authorization_code',
        ]);

        $accessToken = $tokenResponse['access_token'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google did not return an access token.');
        }

        $userInfo = $this->get(self::USERINFO_URL, $accessToken);

        $email = $userInfo['email'] ?? null;
        $sub = $userInfo['sub'] ?? null;

        if (!is_string($email) || $email === '' || !is_string($sub) || $sub === '') {
            throw new RuntimeException('Google did not return a usable profile (missing email or subject id).');
        }

        return [
            'email' => strtolower($email),
            'google_id' => $sub,
            'name' => is_string($userInfo['name'] ?? null) ? $userInfo['name'] : null,
        ];
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function post(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        return $this->execute($ch, 'token exchange');
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $url, string $bearerToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearerToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        return $this->execute($ch, 'userinfo fetch');
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(\CurlHandle $ch, string $step): array
    {
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException("Google {$step} failed: network error ({$error}).");
        }

        $decoded = json_decode((string) $body, true);

        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException("Google {$step} failed: HTTP {$status}.");
        }

        return $decoded;
    }
}
