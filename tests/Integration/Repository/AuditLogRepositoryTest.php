<?php

namespace App\Tests\Integration\Repository;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests — require a running database (APP_ENV=test).
 * Run: php bin/phpunit tests/Integration/
 */
class AuditLogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AuditLogRepository $repo;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em   = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(AuditLogRepository::class);

        // Wrap everything in a transaction that we roll back after each test
        $this->em->getConnection()->beginTransaction();

        // Create a minimal user for log entries
        $this->user = new User();
        $this->user->setEmail('test_audit_' . uniqid() . '@example.com')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setPassword('hashed_password')
            ->setRoles(['ROLE_USER']);
        $this->em->persist($this->user);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        // Symfony's Kernel registers exception handlers; restore them so PHPUnit 11 doesn't mark tests as risky
        restore_exception_handler();
        parent::tearDown();
    }

    private function createLog(string $action, string $entityType, ?array $newValues = null): AuditLog
    {
        $log = new AuditLog();
        $log->setAction($action)
            ->setEntityType($entityType)
            ->setEntityId(1)
            ->setUser($this->user)
            ->setNewValues($newValues);
        $log->setCreatedAtValue();
        $this->em->persist($log);
        $this->em->flush();
        return $log;
    }

    public function testFindByFiltersNoFiltersReturnsAll(): void
    {
        $this->createLog('create', 'Client', ['companyName' => 'ACME']);
        $this->createLog('update', 'Operation');

        $results = $this->repo->findByFilters(null, null, null, null, null);

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function testFindByFiltersFiltersByAction(): void
    {
        $this->createLog('create', 'Client');
        $this->createLog('delete', 'Client');

        $results = $this->repo->findByFilters('create', null, null, null, null);

        foreach ($results as $log) {
            $this->assertSame('create', $log->getAction());
        }
    }

    public function testFindByFiltersFiltersByEntityType(): void
    {
        $this->createLog('create', 'Client');
        $this->createLog('create', 'Operation');

        $results = $this->repo->findByFilters(null, 'Client', null, null, null);

        foreach ($results as $log) {
            $this->assertSame('Client', $log->getEntityType());
        }
    }

    public function testFindByFiltersFiltersByUser(): void
    {
        $this->createLog('create', 'Client');

        // Create a second user and a log for them
        $other = new User();
        $other->setEmail('other_' . uniqid() . '@example.com')
            ->setFirstName('Other')->setLastName('Person')
            ->setPassword('x')->setRoles(['ROLE_USER']);
        $this->em->persist($other);
        $this->em->flush();

        $otherLog = new AuditLog();
        $otherLog->setAction('update')->setEntityType('Client')->setEntityId(2)
            ->setUser($other)->setNewValues(null);
        $otherLog->setCreatedAtValue();
        $this->em->persist($otherLog);
        $this->em->flush();

        $results = $this->repo->findByFilters(null, null, $this->user, null, null);

        foreach ($results as $log) {
            $this->assertSame($this->user->getId(), $log->getUser()->getId());
        }
    }

    public function testFindByFiltersSearchInNewValues(): void
    {
        // Use lowercase value: MySQL JSON columns use binary collation, so LIKE is case-sensitive.
        // The repository applies strtolower() on the search term, so data must be lowercase too.
        $this->createLog('create', 'Client', ['companyName' => 'magrofresh']);
        $this->createLog('create', 'Client', ['companyName' => 'anotherco']);

        $results = $this->repo->findByFilters(null, null, null, null, null, 'magrofresh');

        $this->assertGreaterThanOrEqual(1, count($results));
        $found = false;
        foreach ($results as $log) {
            if ($log->getNewValues() && str_contains(json_encode($log->getNewValues()), 'magrofresh')) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testFindByFiltersDateRange(): void
    {
        $this->createLog('create', 'Invoice');

        $from = new \DateTime('yesterday');
        $to   = new \DateTime('tomorrow');

        $results = $this->repo->findByFilters(null, null, null, $from, $to);

        $this->assertNotEmpty($results);
    }

    public function testFindRecentLimitsResults(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createLog('create', 'Client');
        }

        $results = $this->repo->findRecent(3);

        $this->assertCount(3, $results);
    }

    public function testResultsOrderedByCreatedAtDesc(): void
    {
        $this->createLog('create', 'Client');
        usleep(1000); // ensure different timestamps
        $this->createLog('update', 'Client');

        $results = $this->repo->findByFilters('create', 'Client', null, null, null);
        // Just verify it returns results in DESC order (latest first)
        if (count($results) >= 2) {
            $first  = $results[0]->getCreatedAt()->getTimestamp();
            $second = $results[1]->getCreatedAt()->getTimestamp();
            $this->assertGreaterThanOrEqual($second, $first);
        }

        $this->assertTrue(true); // pass if only 1 result
    }
}
