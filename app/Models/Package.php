<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A purchasable credit pack shown on /pricing — admin-manageable via
 * /admin/packages instead of being hardcoded into the pricing view.
 */
final class Package extends Model
{
    protected static string $table = 'packages';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activeOrdered(): array
    {
        return static::db()->fetchAll(
            'SELECT * FROM `packages` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `id` ASC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(): array
    {
        return static::db()->fetchAll('SELECT * FROM `packages` ORDER BY `sort_order` ASC, `id` ASC');
    }

    public static function setActive(int $id, bool $active): void
    {
        static::update($id, ['is_active' => $active ? 1 : 0]);
    }
}
