<?php

namespace App\Tests\Unit\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuditServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private AuditLogRepository&MockObject $repo;
    private AuditService $service;

    protected function setUp(): void
    {
        $this->em   = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(AuditLogRepository::class);
        $this->service = new AuditService($this->em, $this->repo);
    }

    public function testLogPersistsAuditEntry(): void
    {
        $user = new User();
        $user->setFirstName('Nada')->setLastName('Azrour')->setEmail('n@test.com')->setPassword('x');

        $entity = new class {
            public function getId(): ?int { return 42; }
        };

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(AuditLog::class));
        $this->em->expects($this->once())->method('flush');

        $this->service->log('create', $entity, $user, null, ['name' => 'Test']);
    }

    public function testGetHistoryPassesFiltersToRepository(): void
    {
        $this->repo->expects($this->once())
            ->method('findByFilters')
            ->with('create', 'Client', null, null, null, null)
            ->willReturn([]);

        $result = $this->service->getHistory(['action' => 'create', 'entityType' => 'Client']);

        $this->assertIsArray($result);
    }

    public function testGetHistoryConvertsDateStrings(): void
    {
        $this->repo->expects($this->once())
            ->method('findByFilters')
            ->with(
                null, null, null,
                $this->callback(fn($d) => $d instanceof \DateTime && $d->format('Y-m-d') === '2025-01-01'),
                $this->callback(fn($d) => $d instanceof \DateTime && $d->format('Y-m-d') === '2025-12-31'),
                null
            )
            ->willReturn([]);

        $this->service->getHistory(['dateFrom' => '2025-01-01', 'dateTo' => '2025-12-31']);
    }

    public function testExportToCsvContainsHeader(): void
    {
        $user = new User();
        $user->setFirstName('Ali')->setLastName('Hassan')->setEmail('a@test.com')->setPassword('x');

        $log = new AuditLog();
        $log->setAction('create')
            ->setEntityType('Client')
            ->setEntityId(1)
            ->setUser($user);
        $log->setCreatedAtValue(); // normally called by ORM PrePersist lifecycle

        $csv = $this->service->exportToCsv([$log]);

        $this->assertStringContainsString('Date,Action,Type', $csv);
        $this->assertStringContainsString('create', $csv);
        $this->assertStringContainsString('Client', $csv);
    }
}
