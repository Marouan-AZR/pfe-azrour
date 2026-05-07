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
        $qb = $this->createQueryBuilder('t')
            ->where('t.transferredBy = :user')
            ->setParameter('user', $controleur)
            ->orderBy('t.transferredAt', 'DESC');

        if (!empty($filters['client'])) {
            $qb->join('t.palette', 'p')
               ->join('p.operation', 'o')
               ->andWhere('o.client = :client')
               ->setParameter('client', $filters['client']);
        }

        return $qb->getQuery()->getResult();
    }
}
