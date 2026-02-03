<?php
declare(strict_types=1);
/**
 * Admin layout template with Pico CSS + HTMX.
 *
 * Provides consistent admin page structure with nav bar, page name bar,
 * and message container.
 *
 * @var string $title Browser tab title
 * @var string $pageName H1 in page name bar (optional)
 * @var string $pageSubtitle Optional subtitle under page name
 * @var string $backLink Optional back URL
 * @var string $content Page content HTML
 */

use TournamentTables\Services\CsrfService;

$title = $title ?? 'Tournament Tables';
$pageName = $pageName ?? null;
$pageSubtitle = $pageSubtitle ?? null;
$backLink = $backLink ?? null;
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
    <nav>
        <div class="container">
            <ul>
                <li><a href="/admin" class="brand"><span class="hide-mobile">Tournament Tables</span><span class="hide-desktop">T-Tables</span></a></li>
                <li class="nav-right">
                    <a href="/admin/tournament/create"><span class="hide-mobile">Create New Tournament</span><span class="hide-desktop">Create</span></a>
                    <a href="/admin/login">Login</a>
                </li>
            </ul>
        </div>
    </nav>

    <?php if ($pageName): ?>
    <div class="nav-page-name full-bleed">
        <?php if ($backLink): ?>
        <a href="<?= htmlspecialchars($backLink) ?>" class="back-link">&laquo; Back</a>
        <?php endif; ?>
        <h1><?= htmlspecialchars($pageName) ?></h1>
        <?php if ($pageSubtitle): ?>
        <p class="page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <main class="container">
        <div id="admin-message-container"></div>
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
