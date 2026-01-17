<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use TournamentTables\Models\Tournament;
use TournamentTables\Models\Table;
use TournamentTables\Models\Round;
use TournamentTables\Database\Connection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Tournament management service.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#CreateTournamentRequest
 */
class TournamentService
{
    private const BCP_URL_PATTERN = '#^https://www\.bestcoastpairings\.com/event/([A-Za-z0-9]+)/?(\?.*)?$#';

    /**
     * Create a new tournament.
     *
     * Tables will be created later when Round 1 is imported.
     *
     * @param string $name Tournament name
     * @param string $bcpUrl BCP event URL
     * @param int $tableCount Number of tables (must be between 1 and 100)
     * @return array{tournament: Tournament, adminToken: string}
     * @throws InvalidArgumentException If validation fails
     * @throws RuntimeException If tournament already exists
     */
    public function createTournament(string $name, string $bcpUrl, int $tableCount): array
    {
        // Validate inputs
        $this->validateOrThrow($name, $bcpUrl, $tableCount);

        // Extract BCP event ID
        $bcpUrlValidation = $this->validateBcpUrl($bcpUrl);
        $bcpEventId = $bcpUrlValidation['eventId'];

        // Check for existing tournament with same BCP event
        if (Tournament::findByBcpEventId($bcpEventId) !== null) {
            throw new RuntimeException('A tournament already exists for this BCP event');
        }

        // Generate admin token
        $adminToken = TokenGenerator::generate();

        // Create tournament and tables in transaction
        Connection::beginTransaction();

        try {
            // Create tournament
            $tournament = new Tournament(
                null,
                $name,
                $bcpEventId,
                $this->normalizeUrl($bcpUrl),
                $tableCount,
                $adminToken
            );
            $tournament->save();

            // Create tables if tableCount is provided and > 0
            // Otherwise, tables will be created when Round 1 is imported
            if ($tableCount > 0) {
                Table::createForTournament($tournament->id, $tableCount);
            }

            Connection::commit();

            return [
                'tournament' => $tournament,
                'adminToken' => $adminToken,
            ];
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }

    /**
     * Validate all inputs or throw exception.
     */
    private function validateOrThrow(string $name, string $bcpUrl, int $tableCount = 0): void
    {
        $errors = [];

        $nameValidation = $this->validateName($name);
        if (!$nameValidation['valid']) {
            $errors['name'] = [$nameValidation['error']];
        }

        $urlValidation = $this->validateBcpUrl($bcpUrl);
        if (!$urlValidation['valid']) {
            $errors['bcpUrl'] = [$urlValidation['error']];
        }

        $tableCountValidation = $this->validateTableCount($tableCount);
        if (!$tableCountValidation['valid']) {
            $errors['tableCount'] = [$tableCountValidation['error']];
        }

        if (!empty($errors)) {
            $messages = [];
            foreach ($errors as $field => $fieldErrors) {
                $messages[] = "{$field}: " . implode(', ', $fieldErrors);
            }
            throw new InvalidArgumentException(implode('; ', $messages));
        }
    }

    /**
     * Validate BCP URL format.
     *
     * @param string $url URL to validate
     * @return array{valid: bool, eventId?: string, error?: string}
     */
    public function validateBcpUrl(string $url): array
    {
        $url = trim($url);

        if (empty($url)) {
            return ['valid' => false, 'error' => 'BCP URL is required'];
        }

        // Check it starts with https://
        if (strpos($url, 'https://') !== 0) {
            return ['valid' => false, 'error' => 'URL must use HTTPS'];
        }

        // Check domain
        if (strpos($url, 'bestcoastpairings.com') === false) {
            return ['valid' => false, 'error' => 'URL must be from bestcoastpairings.com'];
        }

        // Extract event ID
        if (!preg_match(self::BCP_URL_PATTERN, $url, $matches)) {
            return ['valid' => false, 'error' => 'Invalid BCP URL format. Must be https://www.bestcoastpairings.com/event/{event ID}'];
        }

        $eventId = $matches[1];
        if (empty($eventId)) {
            return ['valid' => false, 'error' => 'Missing event ID in URL'];
        }

        return ['valid' => true, 'eventId' => $eventId];
    }

    /**
     * Validate table count.
     *
     * @param int $count Table count to validate
     * @return array{valid: bool, error?: string}
     */
    public function validateTableCount(int $count): array
    {
        if ($count < 1) {
            return ['valid' => false, 'error' => 'Table count must be at least 1'];
        }

        if ($count > 100) {
            return ['valid' => false, 'error' => 'Table count must not exceed 100'];
        }

        return ['valid' => true];
    }

    /**
     * Validate tournament name.
     *
     * @param string $name Name to validate
     * @return array{valid: bool, error?: string}
     */
    public function validateName(string $name): array
    {
        $name = trim($name);

        if (empty($name)) {
            return ['valid' => false, 'error' => 'Tournament name is required'];
        }

        if (strlen($name) > 255) {
            return ['valid' => false, 'error' => 'Tournament name must not exceed 255 characters'];
        }

        return ['valid' => true];
    }

    /**
     * Normalize BCP URL (strip query params).
     */
    private function normalizeUrl(string $url): string
    {
        if (preg_match(self::BCP_URL_PATTERN, $url, $matches)) {
            return 'https://www.bestcoastpairings.com/event/' . $matches[1];
        }
        return $url;
    }

    /**
     * Delete a tournament and all related data.
     *
     * Deletion order to avoid FK violations:
     * 1. Delete allocations (references rounds and tables)
     * 2. Delete tournament (CASCADE removes rounds, tables, players)
     *
     * @param int $tournamentId Tournament ID to delete
     * @return bool True if deleted successfully
     * @throws InvalidArgumentException If tournament not found
     */
    public function deleteTournament(int $tournamentId): bool
    {
        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            throw new InvalidArgumentException('Tournament not found');
        }

        Connection::beginTransaction();

        try {
            // Fetch all rounds for this tournament
            $rounds = Round::findByTournament($tournamentId);

            // Delete allocations for each round
            // This must happen first because allocations.table_id FK lacks CASCADE
            if (!empty($rounds)) {
                $roundIds = array_map(function ($round) {
                    return $round->id;
                }, $rounds);

                // Delete all allocations for these rounds
                $placeholders = implode(',', array_fill(0, count($roundIds), '?'));
                Connection::execute(
                    "DELETE FROM allocations WHERE round_id IN ({$placeholders})",
                    $roundIds
                );
            }

            // Now delete the tournament
            // CASCADE will handle rounds, tables, and players
            $result = $tournament->delete();

            Connection::commit();

            return $result;
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }

    /**
     * Update table terrain types.
     *
     * @param int $tournamentId Tournament ID
     * @param array $tableConfigs Array of {tableNumber: int, terrainTypeId: int|null}
     */
    public function updateTables(int $tournamentId, array $tableConfigs): array
    {
        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            throw new InvalidArgumentException('Tournament not found');
        }

        Connection::beginTransaction();

        try {
            foreach ($tableConfigs as $config) {
                $tableNumber = $config['tableNumber'] ?? null;
                $terrainTypeId = $config['terrainTypeId'] ?? null;

                if ($tableNumber === null) {
                    continue;
                }

                $table = Table::findByTournamentAndNumber($tournamentId, (int) $tableNumber);
                if ($table !== null) {
                    $table->terrainTypeId = $terrainTypeId;
                    $table->save();
                }
            }

            Connection::commit();

            return Table::findByTournament($tournamentId);
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }
}
