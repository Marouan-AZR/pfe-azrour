<?php

namespace App\Tests\Unit\Service;

use App\Entity\ColdRoom;
use App\Repository\PaletteRepository;
use App\Service\ColdRoomOccupancyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ColdRoomOccupancyServiceTest extends TestCase
{
    private PaletteRepository&MockObject $paletteRepo;

    protected function setUp(): void
    {
        $this->paletteRepo = $this->createMock(PaletteRepository::class);
    }

    private function makeService(float $usedCapacityTons = 0.0): ColdRoomOccupancyService
    {
        // Crée un service où getUsedCapacity() est simulé pour retourner une valeur fixe
        $service = $this->getMockBuilder(ColdRoomOccupancyService::class)
            ->setConstructorArgs([$this->paletteRepo])
            ->onlyMethods(['getUsedCapacity'])
            ->getMock();

        $service->method('getUsedCapacity')->willReturn($usedCapacityTons);

        return $service;
    }

    private function makeRoom(string $maxCapacityTons): ColdRoom
    {
        $room = new ColdRoom();
        $room->setName('Test Room')
             ->setMaxCapacityTons($maxCapacityTons)
             ->setTargetTemperature('-18');
        return $room;
    }

    // ── getOccupancyRate ──────────────────────────────────────────

    public function testOccupancyRateIsZeroWhenMaxCapacityIsZero(): void
    {
        // Chambre avec capacité 0 → pas de division par zéro
        $service = $this->makeService(0.0);
        $room    = $this->makeRoom('0');

        $this->assertSame(0.0, $service->getOccupancyRate($room));
    }

    public function testOccupancyRateFullRoom(): void
    {
        // Chambre de 2400 T, utilisée à 2400 T → 100%
        $service = $this->makeService(2400.0);
        $room    = $this->makeRoom('2400');

        $this->assertSame(100.0, $service->getOccupancyRate($room));
    }

    public function testOccupancyRateHalfFull(): void
    {
        // Chambre de 2400 T, utilisée à 1200 T → 50%
        $service = $this->makeService(1200.0);
        $room    = $this->makeRoom('2400');

        $this->assertSame(50.0, $service->getOccupancyRate($room));
    }

    public function testOccupancyRateAlmostFull(): void
    {
        // Chambre de 2400 T, utilisée à 2160 T → 90%
        $service = $this->makeService(2160.0);
        $room    = $this->makeRoom('2400');

        $this->assertSame(90.0, $service->getOccupancyRate($room));
    }

    // ── getAvailableCapacity ──────────────────────────────────────

    public function testAvailableCapacityFullWarehouse(): void
    {
        // Entrepôt 12 000 T total (5 chambres × 2400 T), utilisé 3000 T → 9000 T disponibles
        $service = $this->makeService(3000.0);
        $room    = $this->makeRoom('12000');

        $this->assertSame(9000.0, $service->getAvailableCapacity($room));
    }

    public function testAvailableCapacityEmptyRoom(): void
    {
        // Chambre vide → disponible = max
        $service = $this->makeService(0.0);
        $room    = $this->makeRoom('2400');

        $this->assertSame(2400.0, $service->getAvailableCapacity($room));
    }

    // ── getOccupancyStatsForRooms ─────────────────────────────────

    public function testOccupancyStatsReturnsCorrectKeys(): void
    {
        $service = $this->makeService(1200.0);
        $room    = $this->makeRoom('2400');

        // On a besoin d'un ID — simulons avec reflexion
        $ref = new \ReflectionProperty(ColdRoom::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($room, 1);

        $stats = $service->getOccupancyStatsForRooms([$room]);

        $this->assertArrayHasKey(1, $stats);
        $this->assertArrayHasKey('usedCapacity', $stats[1]);
        $this->assertArrayHasKey('availableCapacity', $stats[1]);
        $this->assertArrayHasKey('occupancyRate', $stats[1]);
    }

    public function testOccupancyStatsIgnoresNonColdRoomObjects(): void
    {
        $service = $this->makeService(0.0);

        // Passer un objet qui n'est pas une ColdRoom → doit être ignoré
        $stats = $service->getOccupancyStatsForRooms([new \stdClass()]);

        $this->assertEmpty($stats);
    }

    public function testOccupancyStatsMultipleRooms(): void
    {
        $service = $this->makeService(600.0); // toutes les chambres retournent 600 T

        $room1 = $this->makeRoom('2400');
        $room2 = $this->makeRoom('2400');

        $ref = new \ReflectionProperty(ColdRoom::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($room1, 1);
        $ref->setValue($room2, 2);

        $stats = $service->getOccupancyStatsForRooms([$room1, $room2]);

        $this->assertCount(2, $stats);
        $this->assertArrayHasKey(1, $stats);
        $this->assertArrayHasKey(2, $stats);
    }
}
