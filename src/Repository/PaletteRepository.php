<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Palette;
use App\Enum\PaletteStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Palette>
 */
class PaletteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Palette::class);
    }

    public function findAvailableByClient(Client $client, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excludedStatuses)')
            ->setParameter('client', $client)
            ->setParameter('excludedStatuses', [PaletteStatus::SORTIE_COMPLETE, PaletteStatus::REJETEE])
            ->orderBy('p.createdAt', 'DESC');

        if (!empty($filters['espece'])) {
            $qb->andWhere('p.espece = :espece')->setParameter('espece', $filters['espece']);
        }
        if (!empty($filters['qualite'])) {
            $qb->andWhere('p.qualite = :qualite')->setParameter('qualite', $filters['qualite']);
        }
        if (!empty($filters['moule'])) {
            $qb->andWhere('p.moule = :moule')->setParameter('moule', $filters['moule']);
        }
        if (!empty($filters['famille'])) {
            $qb->andWhere('p.famille = :famille')->setParameter('famille', $filters['famille']);
        }
        if (!empty($filters['coldRoom'])) {
            $qb->andWhere('p.coldRoom = :coldRoom')->setParameter('coldRoom', $filters['coldRoom']);
        }
        if (!empty($filters['rayon'])) {
            $qb->andWhere('p.rayon = :rayon')->setParameter('rayon', $filters['rayon']);
        }

        return $qb->getQuery()->getResult();
    }

    public function findStockByClient(?Client $client = null, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.operation', 'o')
            ->where('o.status = :validated')
            ->setParameter('validated', 'validated');

        if ($client) {
            $qb->andWhere('o.client = :client')->setParameter('client', $client);
        }
        if (!empty($filters['espece'])) {
            $qb->andWhere('p.espece = :espece')->setParameter('espece', $filters['espece']);
        }
        if (!empty($filters['qualite'])) {
            $qb->andWhere('p.qualite = :qualite')->setParameter('qualite', $filters['qualite']);
        }
        if (!empty($filters['moule'])) {
            $qb->andWhere('p.moule = :moule')->setParameter('moule', $filters['moule']);
        }
        if (!empty($filters['famille'])) {
            $qb->andWhere('p.famille = :famille')->setParameter('famille', $filters['famille']);
        }
        if (!empty($filters['coldRoom'])) {
            $qb->andWhere('p.coldRoom = :coldRoom')->setParameter('coldRoom', $filters['coldRoom']);
        }
        if (!empty($filters['rayon'])) {
            $qb->andWhere('p.rayon = :rayon')->setParameter('rayon', $filters['rayon']);
        }

        return $qb->orderBy('o.client', 'ASC')->addOrderBy('p.espece', 'ASC')->getQuery()->getResult();
    }

    public function getStockSummaryByClient(Client $client): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.espece, p.qualite, p.moule, p.famille,
                SUM(p.nombreCartons) as totalCartonsEntres,
                SUM(p.nombreCartons - p.cartonsRestants) as totalCartonsSortis,
                SUM(p.cartonsRestants) as totalCartonsRestants,
                SUM(p.poidsTotal) as totalPoidsEntree,
                SUM(CAST(p.poidsTotal, \'decimal\') - CAST(p.poidsRestant, \'decimal\')) as totalPoidsSortie,
                SUM(p.poidsRestant) as totalPoidsRestant')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('o.status = :validated')
            ->setParameter('client', $client)
            ->setParameter('validated', 'validated')
            ->groupBy('p.espece, p.qualite, p.moule, p.famille')
            ->getQuery()->getResult();
    }

    public function getTotalStockByClient(Client $client): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.poidsRestant) as total')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('o.status = :validated')
            ->andWhere('p.cartonsRestants > 0')
            ->setParameter('client', $client)
            ->setParameter('validated', 'validated')
            ->getQuery()->getSingleScalarResult();

        return (float)($result ?? 0);
    }
}
