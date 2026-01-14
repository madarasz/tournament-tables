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

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>

    <!-- App utilities -->
    <script src="/js/utils.js"></script>

    <style>
        /* Custom styles */
        :root {
            --primary: #1095c1;
            --primary-hover: #0d7ea8;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        nav {
            background: var(--primary);
            padding: 1rem;
            margin-bottom: 2rem;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 1rem;
        }

        nav a {
            color: white;
            text-decoration: none;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .brand {
            font-weight: bold;
            font-size: 1.2rem;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }

        /* Conflict highlighting */
        .conflict {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .conflict-severe {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        /* Loading indicator */
        .htmx-indicator {
            display: none;
        }

        .htmx-request .htmx-indicator {
            display: inline;
        }

        .htmx-request.htmx-indicator {
            display: inline;
        }

        /* Table styling */
        table {
            width: 100%;
        }

        th {
            text-align: left;
        }

        /* Large text for venue displays */
        .venue-display {
            font-size: 1.5rem;
        }

        .venue-display th,
        .venue-display td {
            padding: 1rem;
        }

        /* Admin token display */
        .token-display {
            font-family: monospace;
            font-size: 1.2rem;
            background: #f5f5f5;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            user-select: all;
        }

        /* Footer */
        footer {
            margin-top: 3rem;
            padding: 1rem;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php if (!$isPublic): ?>
    <nav>
        <div class="container">
            <ul>
                <li><a href="/" class="brand">Tournament Tables</a></li>
                <li><a href="/tournament/create">New Tournament</a></li>
                <li><a href="/login">Login</a></li>
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
