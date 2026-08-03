<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        return static::firstWhere(['email' => strtolower(trim($email))]);
    }

    public static function findByGoogleId(string $googleId): ?array
    {
        return static::firstWhere(['google_id' => $googleId]);
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        // Google-only accounts have no password_hash — nothing can verify
        // against it, so treat every attempt as a mismatch rather than
        // passing null into password_verify() (which itself returns false,
        // but relying on that implicitly instead of stating it is a trap
        // for the next person who touches this).
        if ($user['password_hash'] === null) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }

    public static function createUser(string $email, string $password, string $role = 'admin', ?string $name = null): string
    {
        return static::create([
            'email' => strtolower(trim($email)),
            'name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);
    }

    /**
     * Google-authenticated accounts have no password — sign-in happens
     * entirely through Google, so there's nothing for a local password to
     * protect (and nothing more secure than blocking that path than simply
     * not having a hash to check at all).
     */
    public static function createGoogleUser(string $email, string $googleId, ?string $name): string
    {
        return static::create([
            'email' => strtolower(trim($email)),
            'name' => $name,
            'google_id' => $googleId,
            'role' => 'user',
        ]);
    }

    public static function linkGoogleId(int $userId, string $googleId): void
    {
        static::update($userId, ['google_id' => $googleId]);
    }

    /**
     * Every user plus their current credit balance in one query, for the
     * admin user list — an N+1 of Credit::balance() per row would work but
     * doesn't need to when this is one join.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allWithBalance(): array
    {
        return static::db()->fetchAll(
            'SELECT u.*, COALESCE(SUM(c.delta), 0) AS balance
             FROM `users` u
             LEFT JOIN `credits` c ON c.user_id = u.id
             GROUP BY u.id
             ORDER BY u.created_at DESC'
        );
    }
}
