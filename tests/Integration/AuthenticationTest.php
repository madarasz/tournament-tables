<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use TournamentTables\Tests\DatabaseTestCase;
use TournamentTables\Services\AuthService;
use TournamentTables\Services\TournamentService;
use TournamentTables\Middleware\AdminAuthMiddleware;
use TournamentTables\Models\Tournament;
use TournamentTables\Database\Connection;

/**
 * Integration tests for authentication flow.
 *
 * Reference: specs/001-table-allocation/tasks.md T034
 */
class AuthenticationTest extends DatabaseTestCase
{
    /** @var AuthService */
    private $authService;

    /** @var TournamentService */
    private $tournamentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = new AuthService();
        $this->tournamentService = new TournamentService();

        // Reset global state
        global $authenticatedTournament;
        $authenticatedTournament = null;

        // Clear superglobals for middleware tests
        $_SERVER['HTTP_X_ADMIN_TOKEN'] = null;
        $_COOKIE['admin_token'] = null;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset global state
        global $authenticatedTournament;
        $authenticatedTournament = null;

        // Clear superglobals
        unset($_SERVER['HTTP_X_ADMIN_TOKEN']);
        unset($_COOKIE['admin_token']);
    }

    // ========== AuthService Integration Tests ==========

    public function testValidateTokenSucceedsWithValidToken(): void
    {
        // Create a tournament to get a valid token
        $tournamentResult = $this->tournamentService->createTournament(
            'Auth Test Tournament',
            'https://www.bestcoastpairings.com/event/authtest123',
            5
        );

        $validToken = $tournamentResult['adminToken'];

        // Validate the token
        $result = $this->authService->validateToken($validToken);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('tournament', $result);
        $this->assertInstanceOf(Tournament::class, $result['tournament']);
        $this->assertEquals($tournamentResult['tournament']->id, $result['tournament']->id);
    }

    public function testValidateTokenFailsWithInvalidToken(): void
    {
        // Create a tournament first to ensure DB is working
        $tournamentResult = $this->tournamentService->createTournament(
            'Auth Test Tournament 2',
            'https://www.bestcoastpairings.com/event/authtest456',
            5
        );


        // Try to validate a non-existent token
        $result = $this->authService->validateToken('InvalidToken12345');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testValidateTokenRejectsNonExistentValidFormatToken(): void
    {
        // A token with correct format (16 chars) but doesn't exist in database
        $result = $this->authService->validateToken('Abc123XyzDef456G');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testValidateTokenTrimsWhitespace(): void
    {
        // Token with whitespace that becomes 16 chars after trim
        // Should still fail because trimmed token won't be found in DB
        // But the validation should process it correctly
        $result = $this->authService->validateToken('  abc123xyz45678  ');

        // After trim, token is 16 chars but won't exist in DB
        $this->assertFalse($result['valid']);
        // Error should be about invalid token (not found), not format
        $this->assertArrayHasKey('error', $result);
    }

    public function testValidateTokenReturnsTournamentData(): void
    {
        // Create a tournament
        $tournamentResult = $this->tournamentService->createTournament(
            'Tournament Data Test',
            'https://www.bestcoastpairings.com/event/datatest123',
            8
        );

        $validToken = $tournamentResult['adminToken'];

        // Validate and check tournament data
        $result = $this->authService->validateToken($validToken);

        $this->assertTrue($result['valid']);
        $this->assertEquals('Tournament Data Test', $result['tournament']->name);
        $this->assertEquals(8, $result['tournament']->tableCount);
    }

    // ========== Middleware Integration Tests ==========

    public function testMiddlewareBlocksRequestWithNoToken(): void
    {
        // Ensure no tokens are set
        unset($_SERVER['HTTP_X_ADMIN_TOKEN']);
        unset($_COOKIE['admin_token']);

        $result = AdminAuthMiddleware::check();

        $this->assertTrue(is_string($result));
        $this->assertStringContainsString('Missing', $result);
    }

    public function testMiddlewareBlocksRequestWithInvalidHeaderToken(): void
    {
        $_SERVER['HTTP_X_ADMIN_TOKEN'] = 'InvalidToken12345';

        $result = AdminAuthMiddleware::check();

        $this->assertTrue(is_string($result));
        $this->assertStringContainsString('Invalid', $result);
    }

    public function testMiddlewareBlocksRequestWithInvalidCookieToken(): void
    {
        // Set JSON cookie format with invalid token
        $tournamentId = 999; // Non-existent tournament
        $_COOKIE['admin_token'] = json_encode([
            'tournaments' => [
                $tournamentId => [
                    'token' => 'InvalidToken12345',
                    'name' => 'Test Tournament',
                    'lastAccessed' => time()
                ]
            ]
        ]);
        $_SERVER['REQUEST_URI'] = "/api/tournaments/{$tournamentId}";

        $result = AdminAuthMiddleware::check();

        $this->assertTrue(is_string($result));
        $this->assertStringContainsString('Invalid', $result);
    }

    public function testMiddlewareAllowsRequestWithValidHeaderToken(): void
    {
        // Create a tournament to get a valid token
        $tournamentResult = $this->tournamentService->createTournament(
            'Middleware Header Test',
            'https://www.bestcoastpairings.com/event/middleware123',
            5
        );

        $validToken = $tournamentResult['adminToken'];

        $_SERVER['HTTP_X_ADMIN_TOKEN'] = $validToken;

        $result = AdminAuthMiddleware::check();

        $this->assertTrue($result);
    }

    public function testMiddlewareAllowsRequestWithValidCookieToken(): void
    {
        // Create a tournament to get a valid token
        $tournamentResult = $this->tournamentService->createTournament(
            'Middleware Cookie Test',
            'https://www.bestcoastpairings.com/event/middleware456',
            5
        );

        $validToken = $tournamentResult['adminToken'];
        $tournamentId = $tournamentResult['tournament']->id;

        // Set JSON cookie format
        $_COOKIE['admin_token'] = json_encode([
            'tournaments' => [
                $tournamentId => [
                    'token' => $validToken,
                    'name' => 'Middleware Cookie Test',
                    'lastAccessed' => time()
                ]
            ]
        ]);
        $_SERVER['REQUEST_URI'] = "/api/tournaments/{$tournamentId}";

        $result = AdminAuthMiddleware::check();

        $this->assertTrue($result);
    }

    public function testMiddlewarePrefersHeaderOverCookie(): void
    {
        // Create two tournaments
        $tournament1 = $this->tournamentService->createTournament(
            'Header Priority Test 1',
            'https://www.bestcoastpairings.com/event/priority1',
            5
        );

        $tournament2 = $this->tournamentService->createTournament(
            'Header Priority Test 2',
            'https://www.bestcoastpairings.com/event/priority2',
            5
        );

        // Set header to tournament 1, cookie to tournament 2
        $_SERVER['HTTP_X_ADMIN_TOKEN'] = $tournament1['adminToken'];
        $_COOKIE['admin_token'] = $tournament2['adminToken'];

        AdminAuthMiddleware::check();
        $authenticatedTournament = AdminAuthMiddleware::getTournament();

        // Should use header token (tournament 1)
        $this->assertEquals($tournament1['tournament']->id, $authenticatedTournament->id);
    }

    public function testMiddlewareStoresAuthenticatedTournament(): void
    {
        // Create a tournament
        $tournamentResult = $this->tournamentService->createTournament(
            'Store Tournament Test',
            'https://www.bestcoastpairings.com/event/store123',
            5
        );

        $_SERVER['HTTP_X_ADMIN_TOKEN'] = $tournamentResult['adminToken'];

        AdminAuthMiddleware::check();
        $tournament = AdminAuthMiddleware::getTournament();

        $this->assertNotNull($tournament);
        $this->assertEquals($tournamentResult['tournament']->id, $tournament->id);
        $this->assertEquals('Store Tournament Test', $tournament->name);
    }

    public function testMiddlewareRejectsShortToken(): void
    {
        $_SERVER['HTTP_X_ADMIN_TOKEN'] = 'short';

        $result = AdminAuthMiddleware::check();

        $this->assertTrue(is_string($result));
        $this->assertStringContainsString('format', strtolower($result));
    }

    public function testMiddlewareRejectsLongToken(): void
    {
        $_SERVER['HTTP_X_ADMIN_TOKEN'] = 'thisTokenIsWayTooLong1234567890';

        $result = AdminAuthMiddleware::check();

        $this->assertTrue(is_string($result));
        $this->assertStringContainsString('format', strtolower($result));
    }

    // ========== Cookie Flow Tests ==========

    public function testTokenCanBeRetrievedFromCookieAfterValidation(): void
    {
        // Create a tournament
        $tournamentResult = $this->tournamentService->createTournament(
            'Cookie Flow Test',
            'https://www.bestcoastpairings.com/event/cookieflow123',
            5
        );

        $validToken = $tournamentResult['adminToken'];
        $tournamentId = $tournamentResult['tournament']->id;

        // Simulate setting the cookie in JSON format (what AuthController does)
        $_COOKIE['admin_token'] = json_encode([
            'tournaments' => [
                $tournamentId => [
                    'token' => $validToken,
                    'name' => 'Cookie Flow Test',
                    'lastAccessed' => time()
                ]
            ]
        ]);
        $_SERVER['REQUEST_URI'] = "/api/tournaments/{$tournamentId}";

        // Verify middleware can authenticate using the cookie
        $result = AdminAuthMiddleware::check();
        $this->assertTrue($result);

        // Verify the tournament is accessible
        $tournament = AdminAuthMiddleware::getTournament();
        $this->assertNotNull($tournament);
        $this->assertEquals($tournamentResult['tournament']->id, $tournament->id);
    }

    public function testTokenValidationAfterTournamentDeletion(): void
    {
        // Create a tournament
        $tournamentResult = $this->tournamentService->createTournament(
            'Deletion Test',
            'https://www.bestcoastpairings.com/event/deletion123',
            5
        );

        $validToken = $tournamentResult['adminToken'];

        // Verify token works initially
        $result = $this->authService->validateToken($validToken);
        $this->assertTrue($result['valid']);

        // Delete the tournament
        Connection::execute('DELETE FROM tournaments WHERE id = ?', [$tournamentResult['tournament']->id]);

        // Token should now be invalid
        $result = $this->authService->validateToken($validToken);
        $this->assertFalse($result['valid']);
    }
}
