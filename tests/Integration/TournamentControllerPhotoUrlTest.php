<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use TournamentTables\Controllers\TournamentController;
use TournamentTables\Models\Tournament;
use TournamentTables\Services\BCPApiService;
use TournamentTables\Services\TournamentImportService;
use TournamentTables\Services\TournamentService;
use TournamentTables\Tests\DatabaseTestCase;

/**
 * Integration tests for tournament creation photo URL persistence via controller.
 */
class TournamentControllerPhotoUrlTest extends DatabaseTestCase
{
    /** @var string|null */
    private $originalContentType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalContentType = $_SERVER['CONTENT_TYPE'] ?? null;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
    }

    protected function tearDown(): void
    {
        if ($this->originalContentType === null) {
            unset($_SERVER['CONTENT_TYPE']);
        } else {
            $_SERVER['CONTENT_TYPE'] = $this->originalContentType;
        }

        parent::tearDown();
    }

    public function testCreatePersistsPhotoUrlFromBcpMetadata(): void
    {
        $bcpUrl = 'https://www.bestcoastpairings.com/event/controllerphoto123';
        $photoUrl = 'https://example.com/events/controllerphoto123.png';
        $eventDate = '2026-10-15T08:00:00.000Z';
        $eventEndDate = '2026-10-15T20:00:00.000Z';
        $locationName = 'Metagame Klub';

        $bcpService = $this->createMock(BCPApiService::class);
        $bcpService
            ->expects($this->once())
            ->method('fetchTournamentMetadata')
            ->with($bcpUrl)
            ->willReturn([
                'name' => 'Controller Photo Tournament',
                'photoUrl' => $photoUrl,
                'eventDate' => $eventDate,
                'eventEndDate' => $eventEndDate,
                'locationName' => $locationName,
            ]);

        $importService = $this->createMock(TournamentImportService::class);
        $importService
            ->expects($this->once())
            ->method('autoImportRound1')
            ->with($this->isInstanceOf(Tournament::class))
            ->willReturn([
                'success' => false,
                'error' => 'Round 1 not yet published on BCP',
            ]);

        $controller = new TestableTournamentController(
            new TournamentService(),
            $importService,
            $bcpService
        );

        $controller->create([], [
            'bcpUrl' => $bcpUrl,
            'tableCount' => 0,
        ]);

        $this->assertNull($controller->validationErrorFields);
        $this->assertNull($controller->errorResponse);
        $this->assertSame(201, $controller->successStatusCode);
        $this->assertNotNull($controller->successResponse);
        $this->assertSame($photoUrl, $controller->successResponse['tournament']['photoUrl']);
        $this->assertSame($eventDate, $controller->successResponse['tournament']['eventDate']);
        $this->assertSame($eventEndDate, $controller->successResponse['tournament']['eventEndDate']);
        $this->assertSame($locationName, $controller->successResponse['tournament']['locationName']);

        $tournamentId = (int) $controller->successResponse['tournament']['id'];
        $found = Tournament::find($tournamentId);
        $this->assertNotNull($found);
        $this->assertSame($photoUrl, $found->photoUrl);
        $this->assertSame($eventDate, $found->eventDate);
        $this->assertSame($eventEndDate, $found->eventEndDate);
        $this->assertSame($locationName, $found->locationName);
    }

    public function testCreateStoresNullPhotoUrlWhenBcpMetadataPhotoMissingOrBlank(): void
    {
        $bcpUrl = 'https://www.bestcoastpairings.com/event/controllernophoto123';

        $bcpService = $this->createMock(BCPApiService::class);
        $bcpService
            ->expects($this->once())
            ->method('fetchTournamentMetadata')
            ->with($bcpUrl)
            ->willReturn([
                'name' => 'Controller No Photo Tournament',
                'photoUrl' => '   ',
                'eventDate' => '   ',
                'eventEndDate' => null,
                'locationName' => '   ',
            ]);

        $importService = $this->createMock(TournamentImportService::class);
        $importService
            ->expects($this->once())
            ->method('autoImportRound1')
            ->with($this->isInstanceOf(Tournament::class))
            ->willReturn([
                'success' => false,
                'error' => 'Round 1 not yet published on BCP',
            ]);

        $controller = new TestableTournamentController(
            new TournamentService(),
            $importService,
            $bcpService
        );

        $controller->create([], [
            'bcpUrl' => $bcpUrl,
            'tableCount' => 0,
        ]);

        $this->assertNull($controller->validationErrorFields);
        $this->assertNull($controller->errorResponse);
        $this->assertSame(201, $controller->successStatusCode);
        $this->assertNotNull($controller->successResponse);
        $this->assertNull($controller->successResponse['tournament']['photoUrl']);
        $this->assertNull($controller->successResponse['tournament']['eventDate']);
        $this->assertNull($controller->successResponse['tournament']['eventEndDate']);
        $this->assertNull($controller->successResponse['tournament']['locationName']);

        $tournamentId = (int) $controller->successResponse['tournament']['id'];
        $found = Tournament::find($tournamentId);
        $this->assertNotNull($found);
        $this->assertNull($found->photoUrl);
        $this->assertNull($found->eventDate);
        $this->assertNull($found->eventEndDate);
        $this->assertNull($found->locationName);
    }
}

/**
 * Controller test double that captures responses instead of emitting headers/body.
 */
class TestableTournamentController extends TournamentController
{
    /** @var array<string, mixed>|null */
    public $successResponse = null;

    /** @var int|null */
    public $successStatusCode = null;

    /** @var array<string, mixed>|null */
    public $errorResponse = null;

    /** @var array<string, array<int, string>>|null */
    public $validationErrorFields = null;

    protected function success($data, int $statusCode = 200): void
    {
        $this->successResponse = is_array($data) ? $data : ['data' => $data];
        $this->successStatusCode = $statusCode;
    }

    protected function validationError(array $fields): void
    {
        $this->validationErrorFields = $fields;
    }

    protected function error(string $error, string $message, int $statusCode = 400, array $fields = []): void
    {
        $this->errorResponse = [
            'error' => $error,
            'message' => $message,
            'statusCode' => $statusCode,
            'fields' => $fields,
        ];
    }
}
