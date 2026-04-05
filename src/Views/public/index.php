<?php
declare(strict_types=1);

/**
 * Public tournament list view - Tactical Command Theme.
 *
 * Expected variables:
 * - $tournaments: Array<int, array<string, mixed>>
 */

/**
 * Parse a date string into DateTimeImmutable.
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
 * Determine tournament status from date fields only.
 */
function getTournamentStatus(?string $eventDate, ?string $eventEndDate, DateTimeImmutable $now): string
{
    $start = parseTournamentDate($eventDate);
    $end = parseTournamentDate($eventEndDate);

    if ($start !== null && $end !== null) {
        if ($now < $start) {
            return 'UPCOMING';
        }
        if ($now > $end) {
            return 'FINISHED';
        }
        return 'LIVE';
    }

    if ($start !== null && $end === null) {
        return $now < $start ? 'UPCOMING' : 'LIVE';
    }

    if ($start === null && $end !== null) {
        return $now > $end ? 'FINISHED' : 'UPCOMING';
    }

    return 'UPCOMING';
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
 * Normalize terrain emoji string into list.
 *
 * @return string[]
 */
function getTerrainEmojiList(string $terrainEmojiSummary): array
{
    $summary = trim($terrainEmojiSummary);
    if ($summary === '') {
        return [];
    }

    $parts = preg_split('/\s+/', $summary) ?: [];
    $parts = array_values(array_filter($parts, fn($part) => trim($part) !== ''));

    return array_values(array_unique($parts));
}

$hasTournaments = !empty($tournaments);
$now = new DateTimeImmutable('now');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments - Tournament Tables</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/css/tactical-theme.css">
</head>
<body class="tc-page tc-list-page">
    <header class="tc-header">
        <a href="/" class="tc-header-brand">
            <span class="tc-header-brand-icon">⚔️</span>
            <span class="tc-header-brand-text">Tournament Tables</span>
        </a>
    </header>

    <main class="tc-list-main">
        <section class="tc-list-hero">
            <h1 data-testid="tournaments-heading">Tournaments</h1>
        </section>

        <?php if ($hasTournaments): ?>
        <section class="tc-list-grid" data-testid="tournament-list">
            <?php foreach ($tournaments as $tournament): ?>
                <?php
                $name = (string) ($tournament['name'] ?? 'Unnamed Tournament');
                $safeName = htmlspecialchars($name);
                $status = getTournamentStatus(
                    (string) ($tournament['event_date'] ?? ''),
                    (string) ($tournament['event_end_date'] ?? ''),
                    $now
                );
                $statusClass = strtolower($status);
                $startDateForClient = formatTournamentDateForClient((string) ($tournament['event_date'] ?? ''));
                $endDateForClient = formatTournamentDateForClient((string) ($tournament['event_end_date'] ?? ''));
                $dateRange = formatTournamentDateRange(
                    (string) ($tournament['event_date'] ?? ''),
                    (string) ($tournament['event_end_date'] ?? '')
                );
                $playerCount = (int) ($tournament['player_count'] ?? 0);
                $roundCount = (int) ($tournament['round_count'] ?? 0);
                $terrainEmojis = getTerrainEmojiList((string) ($tournament['terrain_emojis'] ?? ''));
                $bcpUrl = trim((string) ($tournament['bcp_url'] ?? ''));
                $isUpcoming = $status === 'UPCOMING';
                $useBcpLink = $isUpcoming && $bcpUrl !== '';
                $cardHref = $useBcpLink ? $bcpUrl : '/' . (int) $tournament['id'];
                $actionLabel = $status === 'FINISHED' ? 'Archives' : ($useBcpLink ? 'BCP' : 'View');
                $photoUrl = trim((string) ($tournament['photo_url'] ?? ''));
                $showLiveDot = $status === 'LIVE';
                $locationName = trim((string) ($tournament['location_name'] ?? ''));
                $venueName = $locationName !== '' ? $locationName : 'Venue TBD';
                ?>
                <a
                    href="<?= htmlspecialchars($cardHref) ?>"
                    class="tc-list-card status-<?= $statusClass ?>"
                    data-testid="tournament-link-<?= $safeName ?>"
                    <?= $useBcpLink ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                >
                    <div class="tc-list-card-media" aria-hidden="true">
                        <?php if ($photoUrl !== ''): ?>
                            <img
                                src="<?= htmlspecialchars($photoUrl) ?>"
                                alt="<?= $safeName ?> banner"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="tc-list-card-media-fallback"></div>
                        <?php endif; ?>
                    </div>

                    <span class="tc-list-status status-<?= $statusClass ?>" data-testid="status-<?= $safeName ?>">
                        <?php if ($showLiveDot): ?>
                        <span class="tc-list-status-dot" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?= $status ?>
                    </span>

                    <div class="tc-list-card-body">
                        <h2><?= $safeName ?></h2>
                        <p class="tc-list-venue"><?= htmlspecialchars($venueName) ?></p>
                        <p
                            class="tc-list-date"
                            data-testid="event-date-<?= $safeName ?>"
                            data-local-date-range
                            <?= $startDateForClient !== null ? 'data-local-date-start="' . htmlspecialchars($startDateForClient) . '"' : '' ?>
                            <?= $endDateForClient !== null ? 'data-local-date-end="' . htmlspecialchars($endDateForClient) . '"' : '' ?>
                        ><?= htmlspecialchars($dateRange) ?></p>

                        <div class="tc-list-stats">
                            <span data-testid="player-count-<?= $safeName ?>"><?= $playerCount ?> players</span>
                            <span><?= $roundCount ?> rounds</span>
                        </div>

                        <footer class="tc-list-meta">
                            <?php if (!empty($terrainEmojis)): ?>
                                <span class="tc-list-terrain" aria-label="Terrain types">
                                    <?php foreach ($terrainEmojis as $emoji): ?>
                                        <span><?= htmlspecialchars($emoji) ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php else: ?>
                                <span class="tc-list-terrain tc-list-terrain-empty">No terrain data</span>
                            <?php endif; ?>
                            <span class="tc-list-action"><?= $actionLabel ?></span>
                            <span class="tc-list-chevron" aria-hidden="true">›</span>
                        </footer>
                    </div>
                </a>
            <?php endforeach; ?>
        </section>
        <?php else: ?>
        <section class="tc-list-empty">
            <p>No tournaments available.</p>
            <p>Please check back later.</p>
        </section>
        <?php endif; ?>
    </main>
    <script src="/js/date-localization.js"></script>
</body>
</html>
