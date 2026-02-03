<?php
/**
 * Admin home page view.
 *
 * Displays list of tournaments the user has access to.
 *
 * @var array $tournaments List of tournaments
 * @var bool $isEmpty Whether the tournament list is empty
 */

$title = 'My Tournaments';
$pageName = 'My Tournaments';
ob_start();
?>

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
    <article>
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
                        <td class="text-center"><?= $tournament['tableCount'] ?></td>
                        <td class="text-center"><?= $tournament['roundCount'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </article>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
