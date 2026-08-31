<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Cheap, dependency-free bot/abuse filtering for the public registration
 * form. None of these checks is bulletproof alone; together they turn away
 * the bulk of drive-by spam signups whose only purpose is to burn the free
 * signup credit on GPU / OpenAI time.
 *
 * Wired into AuthController::register(). The matching hidden form fields
 * live in Views/auth/register.php. Tuning lives in app/Config/spam.php.
 */
final class SignupGuard
{
    /** Hidden honeypot field — a real browser leaves it empty, bots fill it. */
    public const HONEYPOT_FIELD = 'company_website';

    /** Hidden field carrying the unix time the form was rendered. */
    public const TIMESTAMP_FIELD = 'form_loaded_at';

    /**
     * Returns a user-facing rejection message, or null if the submission
     * looks human. Messages are deliberately vague to a bot; the real
     * reason is written to the log.
     *
     * @param array<string, mixed> $input the raw request input (body + query)
     */
    public static function rejectionReason(array $input, string $email, string $ip): ?string
    {
        // 1. Honeypot: an off-screen field only an indiscriminate form-filler touches.
        if (trim((string) ($input[self::HONEYPOT_FIELD] ?? '')) !== '') {
            self::log('honeypot filled', $email, $ip);

            return 'Your registration could not be completed. Please try again.';
        }

        // 2. Time trap: submitted implausibly fast after the form loaded.
        $loadedAt = (int) ($input[self::TIMESTAMP_FIELD] ?? 0);
        $minSeconds = (int) config('spam.min_submit_seconds', 3);

        if ($loadedAt > 0 && (time() - $loadedAt) < $minSeconds) {
            self::log('submitted ' . (time() - $loadedAt) . 's after load', $email, $ip);

            return 'Your registration could not be completed. Please try again.';
        }

        $domain = strtolower(substr(strrchr($email, '@') ?: '@', 1));

        if ($domain === '') {
            return 'Please enter a valid email address.';
        }

        // 3. Domain blocklist: disposable providers + a few free providers
        //    that are near-universally spam for this audience.
        $blocked = config('spam.blocked_email_domains', []);

        if (is_array($blocked) && in_array($domain, $blocked, true)) {
            self::log('blocked email domain: ' . $domain, $email, $ip);

            return 'Please sign up with a different email provider.';
        }

        // 4. The domain must be able to receive mail at all — catches
        //    invented / mistyped domains and many disposable ones that
        //    never publish an MX (or A) record.
        if (config('spam.check_mx', true) && !self::domainAcceptsMail($domain)) {
            self::log('domain has no MX/A record: ' . $domain, $email, $ip);

            return 'That email domain does not appear to accept mail. Please check the address.';
        }

        return null;
    }

    private static function domainAcceptsMail(string $domain): bool
    {
        // getmxrr() is the direct check; RFC 5321 also lets a bare A record
        // act as an implicit mail exchanger, so fall back to that.
        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }

    private static function log(string $reason, string $email, string $ip): void
    {
        Logger::warning('SignupGuard blocked a registration', [
            'reason' => $reason,
            'email' => $email,
            'ip' => $ip,
        ]);
    }
}
