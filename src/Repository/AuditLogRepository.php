<?php

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findByFilters(?string $action, ?string $entityType, ?User $user, ?\DateTimeInterface $from, ?\DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }
        if ($entityType) {
            $qb->andWhere('a.entityType = :entityType')->setParameter('entityType', $entityType);
        }
        if ($user) {
            $qb->andWhere('a.user = :user')->setParameter('user', $user);
        }
        if ($from) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', $to);
        }

        return $qb->orderBy('a.createdAt', 'DESC')->getQuery()->getResult();
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getDistinctEntityTypes(): array
    {
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.entityType')
            ->orderBy('a.entityType', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
