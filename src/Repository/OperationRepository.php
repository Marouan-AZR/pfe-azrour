<?php

namespace App\Repository;

use App\Entity\Operation;
use App\Entity\User;
use App\Enum\OperationType;
use App\Enum\StockStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Operation>
 */
class OperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Operation::class);
    }

    public function findByType(OperationType $type, ?int $clientId = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.type = :type')
            ->setParameter('type', $type)
            ->orderBy('o.createdAt', 'DESC');

        if ($clientId) {
            $qb->andWhere('o.client = :clientId')->setParameter('clientId', $clientId);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByControleur(User $controleur, ?OperationType $type = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.controleur = :controleur')
            ->setParameter('controleur', $controleur)
            ->orderBy('o.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('o.type = :type')->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    public function findPendingValidation(OperationType $type): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.type = :type')
            ->andWhere('o.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', StockStatus::PENDING)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findByClientAndPeriod(\App\Entity\Client $client, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.client = :client')
            ->andWhere('o.dateReception BETWEEN :start AND :end')
            ->andWhere('o.status = :status')
            ->setParameter('client', $client)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', StockStatus::VALIDATED)
            ->getQuery()->getResult();
    }

    public function countByMonthAndControleur(User $controleur, int $month, int $year): array
    {
        $start = new \DateTime("$year-$month-01");
        $end = (clone $start)->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('o')
            ->select('o.type, COUNT(o.id) as total')
            ->where('o.controleur = :controleur')
            ->andWhere('o.createdAt BETWEEN :start AND :end')
            ->setParameter('controleur', $controleur)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('o.type')
            ->getQuery()->getResult();
    }

    public function getControleurStatsForMonth(int $month, int $year): array
    {
        $start = new \DateTime("$year-$month-01");
        $end = (clone $start)->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('o')
            ->select('IDENTITY(o.controleur) as controleur_id, o.type, COUNT(o.id) as total, SUM(SIZE(o.palettes)) as palettes')
            ->where('o.controleur IS NOT NULL')
            ->andWhere('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('o.controleur, o.type')
            ->getQuery()->getResult();
    }

    public function findByControleurAndDates(User $controleur, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.controleur = :controleur')
            ->setParameter('controleur', $controleur)
            ->orderBy('o.createdAt', 'DESC');

        if ($from) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }
}
