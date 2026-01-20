<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use PDO;

/**
 * Trait providing common database query helper methods.
 *
 * This trait reduces boilerplate for services that need to fetch
 * entities directly using PDO rather than through Model classes.
 *
 * Requires the using class to have a $db property of type PDO.
 */
trait DatabaseQueryHelper
{
    /**
     * Fetch a single row by ID from a table.
     *
     * @param string $table Table name
     * @param int $id Record ID
     * @return array|null Row data or null if not found
     */
    protected function fetchById(string $table, int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Fetch a single row matching given conditions.
     *
     * @param string $table Table name
     * @param array $conditions Associative array of column => value pairs
     * @return array|null Row data or null if not found
     */
    protected function fetchOneWhere(string $table, array $conditions): ?array
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereClauses[] = "{$column} = ?";
            $params[] = $value;
        }

        $whereString = implode(' AND ', $whereClauses);
        $sql = "SELECT * FROM {$table} WHERE {$whereString}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Fetch all rows matching given conditions.
     *
     * @param string $table Table name
     * @param array $conditions Associative array of column => value pairs
     * @param string|null $orderBy Optional ORDER BY clause (e.g., "id ASC")
     * @return array List of rows
     */
    protected function fetchAllWhere(string $table, array $conditions, ?string $orderBy = null): array
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereClauses[] = "{$column} = ?";
            $params[] = $value;
        }

        $whereString = implode(' AND ', $whereClauses);
        $sql = "SELECT * FROM {$table} WHERE {$whereString}";

        if ($orderBy !== null) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if any rows exist matching given conditions.
     *
     * @param string $sql Full SQL query with COUNT(*) AS count
     * @param array $params Query parameters
     * @return bool True if count > 0
     */
    protected function existsWithQuery(string $sql, array $params): bool
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($result['count']) && $result['count'] > 0;
    }
}
