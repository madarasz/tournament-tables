<?php
/**
 * Home page view.
 *
 * Displays list of tournaments the user has access to.
 *
 * @var array $tournaments List of tournaments
 * @var bool $isEmpty Whether the tournament list is empty
 */

/**
 * Format a Unix timestamp as relative time.
 *
 * @param int $timestamp Unix timestamp
 * @return string Formatted relative time
 */
function formatRelativeTime(int $timestamp): string
{
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

$title = 'My Tournaments';
ob_start();
?>

<h1>My Tournaments</h1>

<?php if ($isEmpty): ?>
    <article>
        <header>
            <h2>No tournaments yet</h2>
        </header>
        <p>You haven't created or accessed any tournaments yet. Get started by creating a new tournament!</p>
        <footer>
            <a href="/admin/tournament/create" role="button">Create New Tournament</a>
        </footer>
    </article>
<?php else: ?>
    <p><a href="/admin/tournament/create" role="button">Create New Tournament</a></p>

    <table>
        <thead>
            <tr>
                <th>Tournament Name</th>
                <th>Tables</th>
                <th>Rounds</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tournaments as $tournament): ?>
                <tr>
                    <td>
                        <strong>
                            <a href="/admin/tournament/<?= $tournament['id'] ?>" role="button" class="secondary">
                                <?= htmlspecialchars($tournament['name']) ?>
                            </a>
                        </strong>
                    </td>
                    <td><?= $tournament['tableCount'] ?></td>
                    <td><?= $tournament['roundCount'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
