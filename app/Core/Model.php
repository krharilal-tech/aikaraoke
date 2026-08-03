<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal active-record-ish base model. Subclasses set $table (and
 * optionally $primaryKey) and get prepared-statement CRUD helpers for free.
 */
abstract class Model
{
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    protected static function db(): Database
    {
        return Database::instance();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int|string $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `%s` = ? LIMIT 1', static::$table, static::$primaryKey);

        return static::db()->fetchOne($sql, [$id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(string $orderBy = ''): array
    {
        $sql = sprintf('SELECT * FROM `%s`', static::$table);

        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        return static::db()->fetchAll($sql);
    }

    /**
     * @param array<string, mixed> $conditions column => value, combined with AND
     * @return array<int, array<string, mixed>>
     */
    public static function where(array $conditions, string $orderBy = '', int $limit = 0): array
    {
        [$clause, $params] = static::buildWhere($conditions);

        $sql = sprintf('SELECT * FROM `%s`%s', static::$table, $clause);

        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        return static::db()->fetchAll($sql, $params);
    }

    /**
     * @param array<string, mixed> $conditions
     * @return array<string, mixed>|null
     */
    public static function firstWhere(array $conditions): ?array
    {
        $rows = static::where($conditions, '', 1);

        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            static::$table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        static::db()->execute($sql, array_values($data));

        return static::db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int|string $id, array $data): int
    {
        $assignments = array_map(static fn (string $column): string => "`{$column}` = ?", array_keys($data));

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = ?',
            static::$table,
            implode(', ', $assignments),
            static::$primaryKey
        );

        $params = [...array_values($data), $id];

        return static::db()->execute($sql, $params);
    }

    public static function delete(int|string $id): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `%s` = ?', static::$table, static::$primaryKey);

        return static::db()->execute($sql, [$id]);
    }

    public static function count(array $conditions = []): int
    {
        [$clause, $params] = static::buildWhere($conditions);

        $sql = sprintf('SELECT COUNT(*) AS aggregate FROM `%s`%s', static::$table, $clause);

        $row = static::db()->fetchOne($sql, $params);

        return (int) ($row['aggregate'] ?? 0);
    }

    /**
     * @param array<string, mixed> $conditions
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function buildWhere(array $conditions): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        $parts = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $parts[] = "`{$column}` = ?";
            $params[] = $value;
        }

        return [' WHERE ' . implode(' AND ', $parts), $params];
    }
}
