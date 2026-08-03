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
}
