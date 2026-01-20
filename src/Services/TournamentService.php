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

    /**
     * Create a new tournament.
     *
     * @param string $name Tournament name
     * @param string $bcpUrl BCP event URL
     * @param int $tableCount Number of tables (0-100, where 0 means auto-import from Round 1)
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
        return Connection::executeInTransaction(function () use ($name, $bcpEventId, $bcpUrl, $tableCount, $adminToken) {
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

            // Create tables
            Table::createForTournament($tournament->id, $tableCount);

            return [
                'tournament' => $tournament,
                'adminToken' => $adminToken,
            ];
        });
    }

    /**
     * Validate all inputs or throw exception.
     */
    private function validateOrThrow(string $name, string $bcpUrl, int $tableCount): void
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
     * Delegates to BcpUrlValidator for centralized URL validation.
     *
     * @param string $url URL to validate
     * @return array{valid: bool, eventId?: string, error?: string}
     */
    public function validateBcpUrl(string $url): array
    {
        return BcpUrlValidator::validate($url);
    }

    /**
     * Validate table count.
     *
     * @param int $count Table count to validate (0-100, where 0 means auto-import from Round 1)
     * @return array{valid: bool, error?: string}
     */
    public function validateTableCount(int $count): array
    {
        if ($count < 0) {
            return ['valid' => false, 'error' => 'Table count cannot be negative'];
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
     *
     * Delegates to BcpUrlValidator for centralized URL normalization.
     */
    private function normalizeUrl(string $url): string
    {
        return BcpUrlValidator::normalize($url);
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

        return Connection::executeInTransaction(function () use ($tournament, $tournamentId) {
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
            return $tournament->delete();
        });
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

        return Connection::executeInTransaction(function () use ($tournamentId, $tableConfigs) {
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

            return Table::findByTournament($tournamentId);
        });
    }
}
