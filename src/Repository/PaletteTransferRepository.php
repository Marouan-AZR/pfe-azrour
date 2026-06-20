<?php

namespace App\Repository;

use App\Entity\PaletteTransfer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaletteTransfer>
 */
class PaletteTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaletteTransfer::class);
    }

    public function findByControleur(User $controleur, array $filters = []): array
    {
        return $this->findByControleurWithFilters($controleur, $filters);
    }

    public function findByControleurWithFilters(User $controleur, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.palette', 'p')
            ->join('p.operation', 'o')
            ->where('t.transferredBy = :user')
            ->setParameter('user', $controleur)
            ->orderBy('t.transferredAt', 'DESC');

        if (!empty($filters['client'])) {
            $qb->andWhere('o.client = :client')->setParameter('client', $filters['client']);
        }
        if (!empty($filters['codePalette'])) {
            $qb->andWhere('p.codePalette LIKE :code')->setParameter('code', '%' . $filters['codePalette'] . '%');
        }
        if (!empty($filters['coldRoom'])) {
            $qb->andWhere('t.toColdRoom = :coldRoom OR t.fromColdRoom = :coldRoom')->setParameter('coldRoom', $filters['coldRoom']);
        }

        return $qb->getQuery()->getResult();
    }

    public function findAllWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.palette', 'p')
            ->join('p.operation', 'o')
            ->orderBy('t.transferredAt', 'DESC');

        if (!empty($filters['client'])) {
            $qb->andWhere('o.client = :client')->setParameter('client', $filters['client']);
        }
        if (!empty($filters['codePalette'])) {
            $qb->andWhere('p.codePalette LIKE :code')->setParameter('code', '%' . $filters['codePalette'] . '%');
        }
        if (!empty($filters['coldRoom'])) {
            $qb->andWhere('t.toColdRoom = :coldRoom OR t.fromColdRoom = :coldRoom')->setParameter('coldRoom', $filters['coldRoom']);
        }
        if (!empty($filters['controleur'])) {
            $qb->andWhere('t.transferredBy = :ctrl')->setParameter('ctrl', $filters['controleur']);
        }
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('t.transferredAt >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom'] . ' 00:00:00');
        }
        if (!empty($filters['dateTo'])) {
            $qb->andWhere('t.transferredAt <= :dateTo')->setParameter('dateTo', $filters['dateTo'] . ' 23:59:59');
        }

        return $qb->getQuery()->getResult();
    }

    public function countByControleur(User $controleur): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.transferredBy = :user')
            ->setParameter('user', $controleur)
            ->getQuery()->getSingleScalarResult();
    }
}
