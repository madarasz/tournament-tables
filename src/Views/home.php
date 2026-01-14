<?php
/**
 * Home page view.
 *
 * Landing page for Tournament Tables application.
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Tables - Tournament Table Allocation</title>

    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.1.1/css/pico.min.css">

    <style>
        :root {
            --primary: #1095c1;
            --primary-hover: #0d7ea8;
        }

        .hero {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, #0d7ea8 100%);
            color: white;
            margin-bottom: 3rem;
        }

        .hero h1 {
            color: white;
            font-size: 3rem;
            margin: 0 0 1rem 0;
        }

        .hero p {
            font-size: 1.25rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin: 2rem 0;
        }

        .actions a {
            display: inline-block;
            padding: 1rem 2rem;
            font-size: 1.25rem;
            text-decoration: none;
            border-radius: 8px;
            transition: transform 0.1s;
        }

        .actions a:hover {
            transform: scale(1.02);
        }

        .btn-primary {
            background: white;
            color: var(--primary);
            font-weight: 600;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }

        .feature {
            background: #f5f5f5;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .feature h3 {
            margin: 0 0 0.5rem 0;
            color: var(--primary);
        }

        .feature p {
            margin: 0;
            color: #666;
        }

        footer {
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
            background: #f5f5f5;
            color: #666;
        }
    </style>
</head>
<body>
    <header class="hero">
        <h1>Tournament Tables</h1>
        <p>Smart table allocation for BCP tournaments. Ensure every player gets a unique gaming experience.</p>

        <div class="actions">
            <a href="/tournament/create" class="btn-primary">Create Tournament</a>
            <a href="/login" class="btn-secondary">Login to Manage</a>
        </div>
    </header>

    <main class="container">
        <div class="feature-grid">
            <article class="feature">
                <h3>BCP Integration</h3>
                <p>Import pairings directly from Best Coast Pairings. No manual data entry required.</p>
            </article>

            <article class="feature">
                <h3>Smart Allocation</h3>
                <p>Automatically assigns tables to avoid repeats, ensuring players experience new terrain each round.</p>
            </article>

            <article class="feature">
                <h3>Conflict Detection</h3>
                <p>Identifies unavoidable conflicts and highlights them for manual review before publishing.</p>
            </article>

            <article class="feature">
                <h3>Public Display</h3>
                <p>Share table allocations with players via a clean, venue-friendly display optimized for big screens.</p>
            </article>

            <article class="feature">
                <h3>Manual Override</h3>
                <p>Easily swap tables or reassign pairings when special circumstances require manual adjustments.</p>
            </article>

            <article class="feature">
                <h3>Simple Admin</h3>
                <p>Secure tournament management with a simple admin token. No complex user accounts needed.</p>
            </article>
        </div>
    </main>

    <footer>
        Tournament Tables - Tournament Table Allocation System
    </footer>
</body>
</html>
