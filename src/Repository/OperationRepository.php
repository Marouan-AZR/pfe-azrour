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

    public function findByTypeWithFilters(OperationType $type, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.type = :type')
            ->setParameter('type', $type)
            ->orderBy('o.createdAt', 'DESC');

        $this->applyFilters($qb, $filters);
        return $qb->getQuery()->getResult();
    }

    public function findByControleurWithFilters(User $controleur, OperationType $type, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.controleur = :controleur')
            ->andWhere('o.type = :type')
            ->setParameter('controleur', $controleur)
            ->setParameter('type', $type)
            ->orderBy('o.createdAt', 'DESC');

        $this->applyFilters($qb, $filters);
        return $qb->getQuery()->getResult();
    }

    private function applyFilters($qb, array $filters): void
    {
        if (!empty($filters['client'])) {
            $qb->andWhere('o.client = :client')->setParameter('client', $filters['client']);
        }
        if (!empty($filters['date'])) {
            $date = new \DateTime($filters['date']);
            $qb->andWhere('o.createdAt >= :dateStart')
               ->andWhere('o.createdAt <= :dateEnd')
               ->setParameter('dateStart', $date->format('Y-m-d 00:00:00'))
               ->setParameter('dateEnd', $date->format('Y-m-d 23:59:59'));
        }
        if (!empty($filters['code'])) {
            $qb->andWhere('o.code LIKE :code')->setParameter('code', '%' . $filters['code'] . '%');
        }
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

    public function findAwaitingValidation(OperationType $type, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.type = :type')
            ->andWhere('o.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', StockStatus::EN_ATTENTE_VALIDATION)
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countAwaitingValidation(OperationType $type): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.type = :type')
            ->andWhere('o.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', StockStatus::EN_ATTENTE_VALIDATION)
            ->getQuery()->getSingleScalarResult();
    }

    public function findAssignedAwaitingControl(User $controleur, OperationType $type, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.type = :type')
            ->andWhere('o.controleur = :controleur')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('type', $type)
            ->setParameter('controleur', $controleur)
            ->setParameter('statuses', [StockStatus::PENDING, StockStatus::EN_ATTENTE_CONTROLE])
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countAssignedAwaitingControl(User $controleur, OperationType $type): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.type = :type')
            ->andWhere('o.controleur = :controleur')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('type', $type)
            ->setParameter('controleur', $controleur)
            ->setParameter('statuses', [StockStatus::PENDING, StockStatus::EN_ATTENTE_CONTROLE])
            ->getQuery()->getSingleScalarResult();
    }

    public function findForNotifications(OperationType $type, array $statuses, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')
            ->where('o.type = :type')
            ->setParameter('type', $type)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (count($statuses) === 1) {
            $qb->andWhere('o.status = :status0')->setParameter('status0', $statuses[0]);
        } else {
            $orX = $qb->expr()->orX();
            foreach ($statuses as $i => $status) {
                $orX->add("o.status = :status$i");
                $qb->setParameter("status$i", $status);
            }
            $qb->andWhere($orX);
        }

        return $qb->getQuery()->getResult();
    }

    public function getTotalWeightValidatedInRange(
        OperationType $type,
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): float {
        $result = $this->createQueryBuilder('o')
            ->select('SUM(p.poidsTotal)')
            ->join('o.palettes', 'p')
            ->where('o.type = :type')
            ->andWhere('o.status = :status')
            ->andWhere('o.validatedAt >= :from')
            ->andWhere('o.validatedAt <= :to')
            ->setParameter('type', $type)
            ->setParameter('status', StockStatus::VALIDATED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()->getSingleScalarResult();

        return round((float)($result ?? 0) / 1000, 2);
    }

    public function findValidatedInRange(
        OperationType $type,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        int $limit = 10
    ): array {
        return $this->createQueryBuilder('o')
            ->where('o.type = :type')
            ->andWhere('o.status = :status')
            ->andWhere('o.validatedAt >= :from')
            ->andWhere('o.validatedAt <= :to')
            ->setParameter('type', $type)
            ->setParameter('status', StockStatus::VALIDATED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('o.validatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findRejectedOperations(int $limit = 15): array
    {
        $since = (new \DateTime())->modify('-7 days');

        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.updatedAt >= :since')
            ->setParameter('status', StockStatus::REJECTED)
            ->setParameter('since', $since)
            ->orderBy('o.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countByTypeAndStatuses(OperationType $type, array $statuses): int
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.type = :type')
            ->setParameter('type', $type);

        if (count($statuses) === 1) {
            $qb->andWhere('o.status = :s0')->setParameter('s0', $statuses[0]);
        } else {
            $orX = $qb->expr()->orX();
            foreach ($statuses as $i => $s) {
                $orX->add("o.status = :s$i");
                $qb->setParameter("s$i", $s);
            }
            $qb->andWhere($orX);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countTodayByType(OperationType $type): int
    {
        $today    = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.type = :type')
            ->andWhere('o.status = :validated')
            ->andWhere('o.validatedAt >= :today')
            ->andWhere('o.validatedAt < :tomorrow')
            ->setParameter('type', $type)
            ->setParameter('validated', StockStatus::VALIDATED)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()->getSingleScalarResult();
    }
}
