<?php
/**
 * Base layout template with Pico CSS + HTMX.
 *
 * Reference: specs/001-table-allocation/research.md#implementation-notes
 *
 * @var string $title Page title
 * @var string $content Page content HTML
 * @var bool $isPublic Whether this is a public page (no admin nav)
 */

use TournamentTables\Services\CsrfService;

$title = $title ?? 'Tournament Tables';
$isPublic = $isPublic ?? false;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> - Tournament Tables</title>
    <?= CsrfService::getMetaTag() ?>

    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">

    <!-- App CSS -->
    <link rel="stylesheet" href="/css/app.css">

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>

    <!-- App utilities -->
    <script src="/js/utils.js"></script>
    <script src="/js/form-utils.js"></script>
</head>
<body>
    <?php if (!$isPublic): ?>
    <nav>
        <div class="container">
            <ul>
                <li><a href="/admin" class="brand">Tournament Tables</a></li>
                <li><a href="/admin/tournament/create">New Tournament</a></li>
                <li><a href="/admin/login">Login</a></li>
            </ul>
        </div>
    </nav>
    <?php else: ?>
    <nav>
        <div class="container">
            <ul>
                <li><a href="/" class="brand">Tournament Tables</a></li>
            </ul>
        </div>
    </nav>
    <?php endif; ?>

    <main class="container">
        <?= $content ?? '' ?>
    </main>

    <footer>
        <div class="container">
            Tournament Tables - Tournament Table Allocation System
        </div>
    </footer>

    <script>
        // Configure HTMX to include CSRF token in requests
        document.body.addEventListener('htmx:configRequest', function(event) {
            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                event.detail.headers['X-CSRF-Token'] = csrfToken.getAttribute('content');
            }
        });
    </script>
</body>
</html>
