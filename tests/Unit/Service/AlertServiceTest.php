<?php

namespace App\Tests\Unit\Service;

use App\Repository\ColdRoomRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;
use App\Service\AlertService;
use App\Service\ColdRoomOccupancyService;
use PHPUnit\Framework\TestCase;

class AlertServiceTest extends TestCase
{
    private AlertService $service;

    protected function setUp(): void
    {
        // getStats() est une méthode pure (calcul sur tableau) — pas besoin de vrais repos
        $this->service = new AlertService(
            $this->createMock(ColdRoomRepository::class),
            $this->createMock(OperationRepository::class),
            $this->createMock(PaletteRepository::class),
            $this->createMock(ColdRoomOccupancyService::class),
        );
    }

    private function alert(string $type, string $category, string $title = 'T', string $message = 'M'): array
    {
        return ['type' => $type, 'priority' => 1, 'icon' => 'x', 'category' => $category,
                'title' => $title, 'message' => $message];
    }

    // ── getStats ──────────────────────────────────────────────────

    public function testStatsCountsTotal(): void
    {
        $alerts = [
            $this->alert('danger',  'chambre'),
            $this->alert('warning', 'stock'),
            $this->alert('info',    'operation'),
        ];

        $stats = $this->service->getStats($alerts);

        $this->assertSame(3, $stats['total']);
    }

    public function testStatsCountsByType(): void
    {
        $alerts = [
            $this->alert('danger',  'chambre'),
            $this->alert('danger',  'stock'),
            $this->alert('warning', 'operation'),
            $this->alert('success', 'operation'),
            $this->alert('info',    'performance'),
        ];

        $stats = $this->service->getStats($alerts);

        $this->assertSame(2, $stats['danger']);
        $this->assertSame(1, $stats['warning']);
        $this->assertSame(1, $stats['info']);
        $this->assertSame(1, $stats['success']);
    }

    public function testStatsCountsByCategory(): void
    {
        $alerts = [
            $this->alert('danger',  'chambre'),
            $this->alert('warning', 'chambre'),
            $this->alert('danger',  'stock'),
            $this->alert('warning', 'operation'),
            $this->alert('info',    'performance'),
        ];

        $stats = $this->service->getStats($alerts);

        $this->assertSame(2, $stats['chambre']);
        $this->assertSame(1, $stats['stock']);
        $this->assertSame(1, $stats['operation']);
        $this->assertSame(1, $stats['performance']);
    }

    public function testStatsReturnsZeroForMissingCategories(): void
    {
        $alerts = [$this->alert('info', 'chambre')];

        $stats = $this->service->getStats($alerts);

        $this->assertSame(0, $stats['danger']);
        $this->assertSame(0, $stats['warning']);
        $this->assertSame(0, $stats['stock']);
        $this->assertSame(0, $stats['operation']);
        $this->assertSame(0, $stats['performance']);
    }

    public function testStatsWithEmptyAlerts(): void
    {
        $stats = $this->service->getStats([]);

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['danger']);
        $this->assertSame(0, $stats['success']);
    }

    // ── getAlertCount ─────────────────────────────────────────────

    public function testAlertCountExcludesSuccessAlerts(): void
    {
        // On simule getAlerts() pour ne pas toucher la base de données
        $service = $this->getMockBuilder(AlertService::class)
            ->setConstructorArgs([
                $this->createMock(ColdRoomRepository::class),
                $this->createMock(OperationRepository::class),
                $this->createMock(PaletteRepository::class),
                $this->createMock(ColdRoomOccupancyService::class),
            ])
            ->onlyMethods(['getAlerts'])
            ->getMock();

        $service->method('getAlerts')->willReturn([
            $this->alert('danger',  'chambre'),
            $this->alert('warning', 'stock'),
            $this->alert('success', 'operation'), // ne doit pas être compté
        ]);

        // 2 alertes non-success sur 3
        $this->assertSame(2, $service->getAlertCount());
    }

    public function testAlertCountIsZeroWhenOnlySuccess(): void
    {
        $service = $this->getMockBuilder(AlertService::class)
            ->setConstructorArgs([
                $this->createMock(ColdRoomRepository::class),
                $this->createMock(OperationRepository::class),
                $this->createMock(PaletteRepository::class),
                $this->createMock(ColdRoomOccupancyService::class),
            ])
            ->onlyMethods(['getAlerts'])
            ->getMock();

        $service->method('getAlerts')->willReturn([
            $this->alert('success', 'operation'),
            $this->alert('success', 'operation'),
        ]);

        $this->assertSame(0, $service->getAlertCount());
    }
}
