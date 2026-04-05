<?php
/**
 * Public round view - Tactical Command Theme.
 *
 * Displays allocation table for a published round (FR-012).
 * Styled with "Tactical Command" dark theme for venue displays (T083).
 *
 * Reference: specs/001-table-allocation/tasks.md#T081, T083
 *
 * Expected variables:
 * - $tournament: Tournament model
 * - $round: Round model|null
 * - $allocations: Array of Allocation models
 * - $publishedRounds: Array of Round models (for round navigation)
 * - $rankedPlayers: Array of ranked players with leaderboard metadata (rank, player, roundScores)
 * - $isLeaderboardView: bool
 */

$modeTitle = $isLeaderboardView
    ? 'Leaderboard'
    : ($round !== null ? "Round {$round->roundNumber}" : 'No Published Rounds');
$pageTitle = htmlspecialchars($tournament->name) . " - {$modeTitle}";
$hasAllocations = !empty($allocations);
$tableCount = count($tournament->getTables());
$bodyClass = 'tc-page' . ($isLeaderboardView ? ' leaderboard-active' : '');

/**
 * Abbreviate player name for mobile display (UX Improvement #8).
 * "Tamas Horvath" -> "T. Horvath"
 * "John" -> "John" (single name, no change)
 */
function abbreviatePlayerName($fullName) {
    $parts = explode(' ', trim($fullName));
    if (count($parts) < 2) {
        return $fullName;
    }
    // First initial + last name(s)
    $firstInitial = mb_substr($parts[0], 0, 1) . '.';
    array_shift($parts);
    return $firstInitial . ' ' . implode(' ', $parts);
}

/**
 * Format table number for display (no leading zeros).
 */
function formatTableNumber($number) {
    return (int) $number;
}

/**
 * Parse tournament date text safely.
 */
function parseTournamentDate(?string $rawDate): ?DateTimeImmutable
{
    if ($rawDate === null || trim($rawDate) === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($rawDate);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Format event date range for display.
 */
function formatTournamentDateRange(?string $eventDate, ?string $eventEndDate): string
{
    $start = parseTournamentDate($eventDate);
    $end = parseTournamentDate($eventEndDate);

    if ($start === null && $end === null) {
        return 'Date TBD';
    }

    if ($start !== null && $end === null) {
        return $start->format('M j, Y');
    }

    if ($start === null && $end !== null) {
        return $end->format('M j, Y');
    }

    if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
        return $start->format('M j, Y');
    }

    if ($start->format('Y') === $end->format('Y')) {
        if ($start->format('n') === $end->format('n')) {
            return sprintf(
                '%s %d-%d, %s',
                $start->format('M'),
                (int) $start->format('j'),
                (int) $end->format('j'),
                $start->format('Y')
            );
        }

        return sprintf(
            '%s %d - %s %d, %s',
            $start->format('M'),
            (int) $start->format('j'),
            $end->format('M'),
            (int) $end->format('j'),
            $start->format('Y')
        );
    }

    return $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
}

/**
 * Normalize tournament date for client-side localization.
 */
function formatTournamentDateForClient(?string $rawDate): ?string
{
    $parsed = parseTournamentDate($rawDate);
    return $parsed === null ? null : $parsed->format('Y-m-d');
}

/**
 * Build public tournament URL with query parameters.
 */
function publicTournamentUrl(int $tournamentId, array $params = []): string
{
    $query = http_build_query($params);
    return '/' . $tournamentId . ($query === '' ? '' : ('?' . $query));
}

/**
 * Format tournament last-updated timestamp for public display.
 *
 * Rules:
 * - < 60 minutes: "X minutes ago"
 * - Today: "HH:MM" (24h)
 * - Otherwise: "YYYY.MM.DD. HH:MM"
 */
function formatLastUpdated(?string $lastUpdated): string
{
    if ($lastUpdated === null || trim($lastUpdated) === '') {
        return '—';
    }

    try {
        $updatedAt = new DateTimeImmutable($lastUpdated);
    } catch (Exception $e) {
        return '—';
    }

    $now = new DateTimeImmutable('now', $updatedAt->getTimezone());
    $diffSeconds = $now->getTimestamp() - $updatedAt->getTimestamp();
    if ($diffSeconds < 0) {
        $diffSeconds = 0;
    }

    $diffMinutes = (int) floor($diffSeconds / 60);
    if ($diffMinutes < 60) {
        return $diffMinutes === 1
            ? '1 minute ago'
            : $diffMinutes . ' minutes ago';
    }

    if ($updatedAt->format('Y-m-d') === $now->format('Y-m-d')) {
        return $updatedAt->format('H:i');
    }

    return $updatedAt->format('Y.m.d. H:i');
}

$locationName = trim((string) ($tournament->locationName ?? ''));
$venueName = $locationName !== '' ? $locationName : 'Venue TBD';
$startDateForClient = formatTournamentDateForClient(isset($tournament->eventDate) ? (string) $tournament->eventDate : null);
$endDateForClient = formatTournamentDateForClient(isset($tournament->eventEndDate) ? (string) $tournament->eventEndDate : null);
$dateRange = formatTournamentDateRange(
    isset($tournament->eventDate) ? (string) $tournament->eventDate : null,
    isset($tournament->eventEndDate) ? (string) $tournament->eventEndDate : null
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Tournament Tables</title>

    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tactical Theme CSS -->
    <link rel="stylesheet" href="/css/tactical-theme.css">
</head>
<body class="<?= $bodyClass ?>">
    <!-- Header -->
    <header class="tc-header">
        <a href="/" class="tc-header-brand" data-testid="back-to-list">
            <span class="tc-header-brand-icon">⚔️</span>
            <span class="tc-header-brand-text">Tournament Tables</span>
        </a>
        <a href="/" class="tc-header-back">
            <span>←</span>
            <span>Back to Tournaments</span>
        </a>
    </header>

    <!-- Layout Wrapper -->
    <div class="tc-layout">
        <!-- Sidebar (Desktop only) -->
        <aside class="tc-sidebar">
            <div class="tc-sidebar-header">
                <span class="tc-sidebar-tournament-name"><?= htmlspecialchars($tournament->name) ?></span>
                <span class="tc-sidebar-tournament-meta">
                    <span><?= htmlspecialchars($venueName) ?></span>
                    <span aria-hidden="true"> | </span>
                    <span
                        data-local-date-range
                        <?= $startDateForClient !== null ? 'data-local-date-start="' . htmlspecialchars($startDateForClient) . '"' : '' ?>
                        <?= $endDateForClient !== null ? 'data-local-date-end="' . htmlspecialchars($endDateForClient) . '"' : '' ?>
                    ><?= htmlspecialchars($dateRange) ?></span>
                </span>
                <span class="tc-sidebar-table-count"><?= $tableCount ?> Tables</span>
            </div>
            <nav class="tc-sidebar-nav" aria-label="Round navigation">
                <?php foreach ($publishedRounds as $r): ?>
                    <?php
                    $isActive = !$isLeaderboardView && $round !== null && $r->roundNumber === $round->roundNumber;
                    $linkClass = 'tc-sidebar-nav-link' . ($isActive ? ' active' : '');
                    ?>
                    <a href="<?= publicTournamentUrl((int) $tournament->id, ['round' => $r->roundNumber]) ?>"
                       class="<?= $linkClass ?>"
                       data-testid="sidebar-round-link-<?= $r->roundNumber ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        Round <?= $r->roundNumber ?>
                    </a>
                <?php endforeach; ?>
                <?php if (!empty($rankedPlayers)): ?>
                <a href="<?= publicTournamentUrl((int) $tournament->id, ['view' => 'leaderboard']) ?>"
                   class="tc-sidebar-nav-link<?= $isLeaderboardView ? ' active' : '' ?>"
                   id="sidebar-leaderboard-link">
                    Leaderboard
                </a>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="tc-main">
            <!-- Hero Section -->
            <section class="tc-hero">
                <div class="tc-hero-inner">
                    <div class="tc-hero-content">
                        <!-- Tournament name (mobile only, desktop shows in sidebar) -->
                        <h2 class="tc-tournament-name"><?= htmlspecialchars($tournament->name) ?></h2>
                        <p class="tc-tournament-meta">
                            <span><?= htmlspecialchars($venueName) ?></span>
                            <span aria-hidden="true"> | </span>
                            <span
                                data-local-date-range
                                <?= $startDateForClient !== null ? 'data-local-date-start="' . htmlspecialchars($startDateForClient) . '"' : '' ?>
                                <?= $endDateForClient !== null ? 'data-local-date-end="' . htmlspecialchars($endDateForClient) . '"' : '' ?>
                            ><?= htmlspecialchars($dateRange) ?></span>
                        </p>
                        <h1 class="tc-round-title" id="hero-round-title"><?= $round !== null ? ('Round ' . $round->roundNumber) : 'No Published Rounds' ?></h1>
                        <h1 class="tc-round-title" id="hero-leaderboard-title">Leaderboard</h1>
                    </div>

                    <!-- Round Pills (Mobile only) -->
                    <?php if (count($publishedRounds) > 1 || !empty($rankedPlayers)): ?>
                    <nav class="tc-round-pills" aria-label="Round navigation">
                        <?php foreach ($publishedRounds as $r): ?>
                            <?php
                            $isActive = !$isLeaderboardView && $round !== null && $r->roundNumber === $round->roundNumber;
                            $pillClass = 'tc-round-pill' . ($isActive ? ' active' : '');
                            ?>
                            <a href="<?= publicTournamentUrl((int) $tournament->id, ['round' => $r->roundNumber]) ?>"
                               class="<?= $pillClass ?>"
                               data-testid="pill-round-link-<?= $r->roundNumber ?>"
                               <?= $isActive ? 'aria-current="page"' : '' ?>>
                                Round <?= $r->roundNumber ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!empty($rankedPlayers)): ?>
                        <a href="<?= publicTournamentUrl((int) $tournament->id, ['view' => 'leaderboard']) ?>"
                           class="tc-round-pill<?= $isLeaderboardView ? ' active' : '' ?>"
                           id="pill-leaderboard-link">
                            Leaderboard
                        </a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($hasAllocations): ?>
            <!-- Match List -->
            <div class="tc-match-list">
                <!-- Column Headers (Desktop only) -->
                <div class="tc-match-header">
                    <span class="tc-match-header-cell">Table</span>
                    <span class="tc-match-header-cell">Terrain</span>
                    <span class="tc-match-header-cell">Player 1</span>
                    <span class="tc-match-header-cell center">Score</span>
                    <span class="tc-match-header-cell center"></span>
                    <span class="tc-match-header-cell center">Score</span>
                    <span class="tc-match-header-cell right">Player 2</span>
                </div>

                <!-- Column Headers (Mobile only) -->
                <div class="tc-match-header-mobile">
                    <span class="tc-mhm-table tc-match-header-cell">Table</span>
                    <span class="tc-mhm-player tc-match-header-cell">Player 1</span>
                    <span class="tc-mhm-score tc-match-header-cell">Score</span>
                    <span class="tc-mhm-player tc-mhm-player-right tc-match-header-cell">Player 2</span>
                </div>

                <!-- Match Rows -->
                <?php foreach ($allocations as $allocation):
                    $isBye = $allocation->isBye();
                    $table = $allocation->getTable();
                    $player1 = $allocation->getPlayer1();
                    $player2 = $isBye ? null : $allocation->getPlayer2();
                    $terrainType = $table ? $table->getTerrainType() : null;
                    $emoji = $terrainType ? $terrainType->emoji : '';
                    $terrainName = $terrainType ? htmlspecialchars($terrainType->name) : '—';

                    $p1NameFull = $player1 ? htmlspecialchars($player1->name) : 'Unknown';
                    $p1NameShort = $player1 ? htmlspecialchars(abbreviatePlayerName($player1->name)) : 'Unknown';
                    $p2NameFull = $isBye ? '' : ($player2 ? htmlspecialchars($player2->name) : 'Unknown');
                    $p2NameShort = $isBye ? '' : ($player2 ? htmlspecialchars(abbreviatePlayerName($player2->name)) : 'Unknown');
                    $p1Faction = $player1 && $player1->faction ? htmlspecialchars($player1->faction) : null;
                    $p2Faction = $player2 && $player2->faction ? htmlspecialchars($player2->faction) : null;

                    $tableNumber = $table ? formatTableNumber($table->tableNumber) : '—';
                    $rowClass = 'tc-match-row' . ($isBye ? ' bye' : '');

                    // Score coloring: win = green, loss = red, tie = grey
                    if (!$isBye) {
                        $s1 = (int)$allocation->player1Score;
                        $s2 = (int)$allocation->player2Score;
                        $p1ScoreClass = $s1 > $s2 ? 'score-win' : ($s1 === $s2 ? 'score-tie' : 'score-loss');
                        $p2ScoreClass = $s2 > $s1 ? 'score-win' : ($s1 === $s2 ? 'score-tie' : 'score-loss');
                    } else {
                        $p1ScoreClass = '';
                        $p2ScoreClass = '';
                    }
                ?>
                <div class="<?= $rowClass ?>">
                    <?php if ($isBye): ?>
                        <!-- Bye Row -->
                        <div class="tc-table-info">
                            <span class="tc-table-number">—</span>
                        </div>
                        <div class="tc-terrain-name">—</div>
                        <div class="tc-player tc-player-1">
                            <span class="tc-player-name">
                                <span class="tc-name-full"><?= $p1NameFull ?></span>
                                <span class="tc-name-short"><?= $p1NameShort ?></span>
                            </span>
                            <?php if ($p1Faction): ?>
                            <div class="tc-player-row">
                                <span class="tc-faction-pill"><?= $p1Faction ?></span>
                                <span class="tc-score-inline"><?= $allocation->player1Score ?></span>
                            </div>
                            <?php endif; ?>
                            <span class="tc-bye-indicator">Bye - no game this round</span>
                        </div>
                        <div class="tc-vs-zone">
                            <span class="tc-score"><?= $allocation->player1Score ?></span>
                            <span class="tc-vs"></span>
                            <span class="tc-score"></span>
                        </div>
                        <div class="tc-player tc-player-2">—</div>
                    <?php else: ?>
                        <!-- Regular Match Row -->
                        <div class="tc-table-info">
                            <span class="tc-table-number"><?= $tableNumber ?></span>
                            <?php if ($emoji): ?>
                            <span class="tc-terrain-emoji"><?= $emoji ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="tc-terrain-name">
                            <?php if ($emoji): ?>
                            <span><?= $emoji ?></span>
                            <?php endif; ?>
                            <span><?= $terrainName ?></span>
                        </div>
                        <div class="tc-player tc-player-1">
                            <span class="tc-player-name">
                                <span class="tc-name-full"><?= $p1NameFull ?></span>
                                <span class="tc-name-short"><?= $p1NameShort ?></span>
                            </span>
                            <div class="tc-player-row">
                                <?php if ($p1Faction): ?>
                                <span class="tc-faction-pill"><?= $p1Faction ?></span>
                                <?php endif; ?>
                                <span class="tc-score-inline <?= $p1ScoreClass ?>"><?= $allocation->player1Score ?></span>
                            </div>
                        </div>
                        <div class="tc-vs-zone">
                            <span class="tc-score <?= $p1ScoreClass ?>"><?= $allocation->player1Score ?></span>
                            <span class="tc-vs">VS</span>
                            <span class="tc-score <?= $p2ScoreClass ?>"><?= $allocation->player2Score ?></span>
                        </div>
                        <div class="tc-player tc-player-2">
                            <span class="tc-player-name">
                                <span class="tc-name-full"><?= $p2NameFull ?></span>
                                <span class="tc-name-short"><?= $p2NameShort ?></span>
                            </span>
                            <div class="tc-player-row">
                                <span class="tc-score-inline <?= $p2ScoreClass ?>"><?= $allocation->player2Score ?></span>
                                <?php if ($p2Faction): ?>
                                <span class="tc-faction-pill"><?= $p2Faction ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <!-- Empty State -->
            <div class="tc-empty-state">
                <?php if ($round === null): ?>
                <p>No published rounds are available yet.</p>
                <p>Please check back later.</p>
                <?php else: ?>
                <p>No table allocations available for this round.</p>
                <p>Please check back later.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($rankedPlayers)): ?>
            <!-- Leaderboard Section -->
            <section class="tc-leaderboard" id="leaderboard" data-testid="leaderboard-section">
                <div class="tc-leaderboard-list" data-testid="leaderboard-table">
                    <div class="tc-leaderboard-row">
                        <span class="tc-match-header-cell">Rank</span>
                        <span class="tc-match-header-cell">Player</span>
                        <span class="tc-match-header-cell right tc-lb-scores-col">Scores</span>
                        <span class="tc-match-header-cell right">
                            <span class="tc-lb-label-desktop">Total</span>
                            <span class="tc-lb-label-mobile">Score</span>
                        </span>
                    </div>
                    <?php foreach ($rankedPlayers as $entry):
                        $lbPlayer = $entry['player'];
                        $lbRank   = $entry['rank'];
                        $lbRankDisplay = $lbRank === null ? '—' : (string) $lbRank;
                        $lbRoundScores = $entry['roundScores'] ?? [];
                    ?>
                    <div class="tc-leaderboard-row" data-testid="leaderboard-row">
                        <span class="tc-lb-rank"><?= $lbRankDisplay ?></span>
                        <div class="tc-lb-player">
                            <span class="tc-player-name"><?= htmlspecialchars($lbPlayer->name) ?></span>
                            <?php if ($lbPlayer->faction): ?>
                            <span class="tc-faction-pill"><?= htmlspecialchars($lbPlayer->faction) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="tc-lb-scores tc-lb-scores-col">
                            <?php if (!empty($lbRoundScores)): ?>
                                <?php foreach ($lbRoundScores as $index => $scoreEntry): ?>
                                    <?php if ($index > 0): ?>
                                        <span class="tc-lb-score-sep">/</span>
                                    <?php endif; ?>
                                    <span class="<?= htmlspecialchars($scoreEntry['class']) ?>"><?= (int) $scoreEntry['score'] ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="tc-lb-score-empty">—</span>
                            <?php endif; ?>
                        </span>
                        <div class="tc-lb-total-cell">
                            <span class="tc-lb-score"><?= $lbPlayer->totalScore ?></span>
                            <span class="tc-lb-scores-mobile">
                                <?php if (!empty($lbRoundScores)): ?>
                                    <?php foreach ($lbRoundScores as $index => $scoreEntry): ?>
                                        <?php if ($index > 0): ?>
                                            <span class="tc-lb-score-sep">/</span>
                                        <?php endif; ?>
                                        <span class="<?= htmlspecialchars($scoreEntry['class']) ?>"><?= (int) $scoreEntry['score'] ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="tc-lb-score-empty">—</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Footer -->
            <footer class="tc-footer">
                <div class="tc-footer-meta">
                    <span class="tc-footer-update">
                        LAST UPDATED: <?= htmlspecialchars(formatLastUpdated($tournament->lastUpdated)) ?>
                        <?php if (!empty($tournament->bcpUrl)): ?>
                            | BCP:
                            <a
                                href="<?= htmlspecialchars((string) $tournament->bcpUrl) ?>"
                                class="tc-footer-link"
                                target="_blank"
                                rel="noopener noreferrer"
                            >Event Page</a>
                        <?php endif; ?>
                    </span>
                </div>
            </footer>
        </main>
    </div>
    <script src="/js/date-localization.js"></script>
    <script>
        (function () {
            var pillsNav = document.querySelector('.tc-round-pills');
            var leaderboardPill = document.getElementById('pill-leaderboard-link');
            var isLeaderboardActive = document.body.classList.contains('leaderboard-active');
            var isMobile = window.matchMedia('(max-width: 1023px)').matches;

            if (!pillsNav || !leaderboardPill || !isLeaderboardActive || !isMobile) {
                return;
            }

            requestAnimationFrame(function () {
                leaderboardPill.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'nearest'
                });
            });
        })();
    </script>
</body>
</html>
