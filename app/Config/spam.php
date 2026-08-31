<?php

declare(strict_types=1);

/**
 * Anti-spam tuning for the public registration form.
 * Consumed by App\Services\SignupGuard (wired into AuthController::register()).
 *
 * - min_submit_seconds: reject a signup that arrives fewer than this many
 *   seconds after the form was rendered. A human can't read the form, choose
 *   a password and submit that fast; a bot posts near-instantly.
 * - check_mx: require the email's domain to publish an MX (or A) record, so
 *   invented / typo'd / throwaway domains that can't receive mail are turned
 *   away. Set to false only if the server can't do outbound DNS.
 * - blocked_email_domains: lowercase, exact match on the host after "@".
 *   Two groups — known disposable/throwaway providers, and a handful of free
 *   providers that essentially never appear for this app's (India-focused,
 *   karaoke) audience but dominate its spam. Add or trim freely.
 */

return [
    'min_submit_seconds' => 3,
    'check_mx' => true,

    'blocked_email_domains' => [
        // --- Disposable / throwaway mail services ---
        'mailinator.com',
        'guerrillamail.com',
        'guerrillamail.info',
        'guerrillamail.net',
        'sharklasers.com',
        'grr.la',
        'trashmail.com',
        'trashmail.de',
        'yopmail.com',
        'yopmail.fr',
        'yopmail.net',
        'getnada.com',
        'nada.email',
        'temp-mail.org',
        'tempmail.com',
        'tempmailo.com',
        'tempr.email',
        'dispostable.com',
        '10minutemail.com',
        '10minutemail.net',
        '20minutemail.com',
        'maildrop.cc',
        'mailnesia.com',
        'fakeinbox.com',
        'throwawaymail.com',
        'mohmal.com',
        'moakt.com',
        'emailondeck.com',
        'mailtemp.net',
        'inboxbear.com',
        'spambog.com',
        'mytemp.email',
        'cs.email',
        'tmpmail.org',
        'tmpmail.net',
        'burnermail.io',
        'spam4.me',
        'byom.de',
        'mailcatch.com',
        'mailexpire.com',
        'jetable.org',
        'trbvm.com',
        'harakirimail.com',

        // --- Free providers rarely legitimate for this audience ---
        'yandex.ru',
        'yandex.com',
        'ya.ru',
        'mail.ru',
        'bk.ru',
        'list.ru',
        'inbox.ru',
        'internet.ru',
        'rambler.ru',
    ],
];
