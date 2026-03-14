<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findPendingValidation(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.status = :status')
            ->setParameter('status', InvoiceStatus::PENDING_VALIDATION)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.client = :client')
            ->setParameter('client', $client)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByFilters(?Client $client, ?InvoiceStatus $status, ?\DateTimeInterface $from, ?\DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('i');

        if ($client) {
            $qb->andWhere('i.client = :client')->setParameter('client', $client);
        }
        if ($status) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if ($from) {
            $qb->andWhere('i.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('i.createdAt <= :to')->setParameter('to', $to);
        }

        return $qb->orderBy('i.createdAt', 'DESC')->getQuery()->getResult();
    }

    public function getTotalRevenue(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $qb = $this->createQueryBuilder('i')
            ->select('SUM(i.totalTtc)')
            ->where('i.status = :status')
            ->setParameter('status', InvoiceStatus::VALIDATED);

        if ($from) {
            $qb->andWhere('i.validatedAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('i.validatedAt <= :to')->setParameter('to', $to);
        }

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('i')
            ->join('i.client', 'c');

        if (!empty($filters['client'])) {
            $qb->andWhere('i.client = :client')->setParameter('client', $filters['client']);
        }
        if (!empty($filters['status'])) {
            $qb->andWhere('i.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('i.createdAt >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $qb->andWhere('i.createdAt <= :dateTo')->setParameter('dateTo', $filters['dateTo']);
        }

        return $qb->orderBy('i.createdAt', 'DESC')->getQuery()->getResult();
    }
}
