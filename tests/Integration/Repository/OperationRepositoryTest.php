<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Client;
use App\Entity\Operation;
use App\Entity\User;
use App\Enum\OperationType;
use App\Enum\StockStatus;
use App\Repository\OperationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for OperationRepository.
 * Each test runs in a transaction that is rolled back at tearDown.
 */
class OperationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private OperationRepository $repo;
    private User $user;
    private Client $client;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em   = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(OperationRepository::class);

        $this->em->getConnection()->beginTransaction();

        // Utilisateur de test
        $this->user = new User();
        $this->user->setEmail('op_test_' . uniqid() . '@example.com')
            ->setFirstName('Op')->setLastName('Test')
            ->setPassword('x')->setRoles(['ROLE_USER']);
        $this->em->persist($this->user);

        // Client de test (tous les champs NOT NULL requis)
        $this->client = new Client();
        $this->client->setCompanyName('TestCo')
            ->setCode('TC' . uniqid())
            ->setAddress('1 rue Test')
            ->setPhone('0600000000')
            ->setEmail('testco_' . uniqid() . '@example.com');
        $this->em->persist($this->client);

        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        restore_exception_handler();
        parent::tearDown();
    }

    private function createOperation(
        OperationType $type,
        StockStatus   $status,
        ?string       $code = null
    ): Operation {
        $op = new Operation();
        $op->setCode($code ?? strtoupper(uniqid('OP')))
           ->setType($type)
           ->setStatus($status)
           ->setClient($this->client)
           ->setCreatedBy($this->user)
           ->setDateReception(new \DateTime());
        $op->setCreatedAtValue();
        $op->setUpdatedAtValue();
        $this->em->persist($op);
        $this->em->flush();
        return $op;
    }

    // ── findByType ────────────────────────────────────────────────

    public function testFindByTypeReturnsOnlyEntries(): void
    {
        $this->createOperation(OperationType::ENTRY, StockStatus::PENDING);
        $this->createOperation(OperationType::EXIT,  StockStatus::PENDING);

        $results = $this->repo->findByType(OperationType::ENTRY);

        foreach ($results as $op) {
            $this->assertSame(OperationType::ENTRY, $op->getType());
        }
    }

    public function testFindByTypeReturnsOnlyExits(): void
    {
        $this->createOperation(OperationType::ENTRY, StockStatus::PENDING);
        $this->createOperation(OperationType::EXIT,  StockStatus::PENDING);

        $results = $this->repo->findByType(OperationType::EXIT);

        foreach ($results as $op) {
            $this->assertSame(OperationType::EXIT, $op->getType());
        }
    }

    // ── findPendingValidation ──────────────────────────────────────

    public function testFindPendingValidationReturnsOnlyPendingOps(): void
    {
        // findPendingValidation cherche status = PENDING uniquement
        $this->createOperation(OperationType::ENTRY, StockStatus::PENDING);
        $this->createOperation(OperationType::ENTRY, StockStatus::VALIDATED);

        $results = $this->repo->findPendingValidation(OperationType::ENTRY);

        $this->assertNotEmpty($results);
        foreach ($results as $op) {
            $this->assertSame(StockStatus::PENDING, $op->getStatus());
        }
    }

    // ── countByTypeAndStatuses ─────────────────────────────────────

    public function testCountByTypeAndStatusesCountsCorrectly(): void
    {
        $this->createOperation(OperationType::ENTRY, StockStatus::PENDING);
        $this->createOperation(OperationType::ENTRY, StockStatus::PENDING);
        $this->createOperation(OperationType::ENTRY, StockStatus::VALIDATED);
        $this->createOperation(OperationType::EXIT,  StockStatus::PENDING);

        $count = $this->repo->countByTypeAndStatuses(
            OperationType::ENTRY,
            [StockStatus::PENDING->value, StockStatus::EN_ATTENTE_CONTROLE->value]
        );

        $this->assertGreaterThanOrEqual(2, $count);
    }

    // ── countTodayByType ──────────────────────────────────────────

    public function testCountTodayByTypeCountsOpValidatedToday(): void
    {
        // countTodayByType cherche les opérations VALIDATED avec validatedAt = aujourd'hui
        $op = $this->createOperation(OperationType::ENTRY, StockStatus::VALIDATED);
        $op->setValidatedAt(new \DateTime());
        $this->em->flush();

        $count = $this->repo->countTodayByType(OperationType::ENTRY);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    // ── findByClientAndPeriod ─────────────────────────────────────

    public function testFindByClientAndPeriodReturnsMatchingOps(): void
    {
        $this->createOperation(OperationType::ENTRY, StockStatus::VALIDATED);

        $from    = new \DateTime('yesterday');
        $to      = new \DateTime('tomorrow');
        $results = $this->repo->findByClientAndPeriod($this->client, $from, $to);

        $this->assertNotEmpty($results);
        foreach ($results as $op) {
            $this->assertSame($this->client->getId(), $op->getClient()->getId());
        }
    }

    public function testFindByClientAndPeriodReturnsEmptyForOtherClient(): void
    {
        $this->createOperation(OperationType::ENTRY, StockStatus::VALIDATED);

        // Autre client sans opérations
        $other = new Client();
        $other->setCompanyName('OtherCo')
            ->setCode('OC' . uniqid())
            ->setAddress('2 rue Autre')
            ->setPhone('0611111111')
            ->setEmail('other_' . uniqid() . '@example.com');
        $this->em->persist($other);
        $this->em->flush();

        $from    = new \DateTime('yesterday');
        $to      = new \DateTime('tomorrow');
        $results = $this->repo->findByClientAndPeriod($other, $from, $to);

        $this->assertEmpty($results);
    }
}
