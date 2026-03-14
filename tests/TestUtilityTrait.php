<?php

declare(strict_types=1);

namespace TournamentTables\Tests;

trait TestUtilityTrait
{
    /**
     * Generate a collision-resistant identifier for test data names/keys.
     */
    protected function createUniqueId(): string
    {
        return sprintf('%d%s', (int) (microtime(true) * 1000000), bin2hex(random_bytes(4)));
    }
}
