<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const SESSION_KEY = '_auth_user_id';

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        return $id === null ? null : (int) $id;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, $userId);
    }

    public static function logout(): void
    {
        Session::remove(self::SESSION_KEY);
        Session::regenerate();
    }

    /**
     * Raw query rather than App\Models\User — Core deliberately doesn't
     * depend on Models (the dependency runs the other way everywhere else
     * in this app), and this is a one-column, one-off lookup that doesn't
     * warrant pulling that in just for this.
     */
    public static function isAdmin(): bool
    {
        $id = self::id();

        if ($id === null) {
            return false;
        }

        $row = Database::instance()->fetchOne('SELECT `role` FROM `users` WHERE `id` = ?', [$id]);

        return $row !== null && $row['role'] === 'admin';
    }

    /**
     * Runs once per request (see Application::run()). If the logged-in
     * user's row has since been blocked — or has vanished entirely — the
     * session is destroyed and the request is stopped, so a block takes
     * effect immediately rather than only at their next login.
     */
    public static function enforceNotBlocked(Request $request): void
    {
        $id = self::id();

        if ($id === null) {
            return;
        }

        try {
            $row = Database::instance()->fetchOne('SELECT `status` FROM `users` WHERE `id` = ?', [$id]);
        } catch (\Throwable $e) {
            // Fail open: if this query can't run (most likely the 006
            // migration hasn't been applied yet), don't take every
            // authenticated page down over it. Blocking simply won't take
            // effect until the `status` column exists.
            Logger::warning('Auth::enforceNotBlocked skipped', ['error' => $e->getMessage()]);

            return;
        }

        if ($row !== null && $row['status'] !== 'blocked') {
            return;
        }

        self::logout();

        if ($request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Your account has been suspended.'], 403);
        }

        Response::redirect(base_url('login') . '?suspended=1');
    }
}
