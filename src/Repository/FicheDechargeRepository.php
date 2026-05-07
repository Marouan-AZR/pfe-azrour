<?php

namespace App\Repository;

use App\Entity\FicheDecharge;
use App\Entity\User;
use App\Enum\FicheStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FicheDecharge>
 */
class FicheDechargeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheDecharge::class);
    }

    public function findByControleur(User $controleur, ?FicheStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.controleur = :controleur')
            ->setParameter('controleur', $controleur)
            ->orderBy('f.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('f.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findPendingValidation(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.status = :status')
            ->setParameter('status', FicheStatus::EN_ATTENTE_VALIDATION)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
