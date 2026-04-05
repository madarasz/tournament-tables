<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TournamentTables\Services\BCPApiService;
use TournamentTables\Services\TournamentMetadataBackfillService;

/**
 * Unit tests for TournamentMetadataBackfillService helper behavior.
 */
class TournamentMetadataBackfillServiceTest extends TestCase
{
    /** @var TournamentMetadataBackfillService */
    private $service;

    protected function setUp(): void
    {
        $this->service = new TournamentMetadataBackfillService(
            $this->createStub(BCPApiService::class)
        );
    }

    public function testIsMissingValueTreatsNullAndBlankAsMissing(): void
    {
        $this->assertTrue($this->invokePrivateMethod('isMissingValue', [null]));
        $this->assertTrue($this->invokePrivateMethod('isMissingValue', ['']));
        $this->assertTrue($this->invokePrivateMethod('isMissingValue', ['   ']));

        $this->assertFalse($this->invokePrivateMethod('isMissingValue', ['value']));
        $this->assertFalse($this->invokePrivateMethod('isMissingValue', ['  value  ']));
    }

    public function testBuildFillableUpdateFieldsIncludesOnlyMissingFieldsWithMetadata(): void
    {
        $tournament = [
            'photo_url' => null,
            'event_date' => '2026-10-10T09:00:00.000Z',
            'event_end_date' => '   ',
            'location_name' => '',
        ];

        $metadata = [
            'photoUrl' => ' https://example.com/new.png ',
            'eventDate' => '2027-02-01T09:00:00.000Z',
            'eventEndDate' => '2027-02-01T18:00:00.000Z',
            'locationName' => ' Main Hall ',
        ];

        $updates = $this->invokePrivateMethod('buildFillableUpdateFields', [$tournament, $metadata]);

        $this->assertSame([
            'photo_url' => 'https://example.com/new.png',
            'event_end_date' => '2027-02-01T18:00:00.000Z',
            'location_name' => 'Main Hall',
        ], $updates);
    }

    public function testBuildFillableUpdateFieldsReturnsEmptyWhenIncomingMetadataIsBlank(): void
    {
        $tournament = [
            'photo_url' => null,
            'event_date' => null,
            'event_end_date' => null,
            'location_name' => null,
        ];

        $metadata = [
            'photoUrl' => '   ',
            'eventDate' => null,
            'eventEndDate' => '',
            'locationName' => ' ',
        ];

        $updates = $this->invokePrivateMethod('buildFillableUpdateFields', [$tournament, $metadata]);

        $this->assertSame([], $updates);
    }

    /**
     * @param array<int, mixed> $args
     * @return mixed
     */
    private function invokePrivateMethod(string $methodName, array $args = [])
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($this->service, $args);
    }
}
