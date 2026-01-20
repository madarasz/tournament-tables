<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Models\Tournament;
use TournamentTables\Database\Connection;

/**
 * Home page controller.
 *
 * Displays list of tournaments the user has access to.
 */
class HomeController extends BaseController
{
    /**
     * GET / - Display home page with tournament list.
     */
    public function index(array $params, ?array $body): void
    {
        $tournaments = $this->getMultiTokenCookie();

        if (empty($tournaments)) {
            $this->renderView('home', [
                'tournaments' => [],
                'isEmpty' => true
            ]);
            return;
        }

        // Fetch full tournament details
        $tournamentData = [];
        foreach ($tournaments as $id => $data) {
            try {
                $tournament = Tournament::find((int)$id);
                if ($tournament === null) {
                    continue; // Skip invalid tournaments
                }

                // Get round count
                $roundCount = Connection::fetchColumn(
                    'SELECT COUNT(*) FROM rounds WHERE tournament_id = ?',
                    [$id]
                );

                $tournamentData[] = [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'tableCount' => $tournament->tableCount,
                    'roundCount' => $roundCount,
                    'lastAccessed' => $data['lastAccessed']
                ];
            } catch (\Exception $e) {
                error_log("Failed to load tournament {$id}: " . $e->getMessage());
                continue;
            }
        }

        // Sort by last accessed (descending)
        usort($tournamentData, function ($a, $b) {
            return $b['lastAccessed'] - $a['lastAccessed'];
        });

        $this->renderView('home', [
            'tournaments' => $tournamentData,
            'isEmpty' => empty($tournamentData)
        ]);
    }
}
