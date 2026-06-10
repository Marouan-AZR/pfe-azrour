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
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('p.createdAt >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom'] . ' 00:00:00');
        }
        if (!empty($filters['dateTo'])) {
            $qb->andWhere('p.createdAt <= :dateTo')->setParameter('dateTo', $filters['dateTo'] . ' 23:59:59');
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

    public function getDistinctFilterValues(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        return [
            'especes' => array_column($conn->fetchAllAssociative('SELECT DISTINCT espece FROM palettes WHERE espece IS NOT NULL ORDER BY espece'), 'espece'),
            'qualites' => array_column($conn->fetchAllAssociative('SELECT DISTINCT qualite FROM palettes WHERE qualite IS NOT NULL ORDER BY qualite'), 'qualite'),
            'moules' => array_column($conn->fetchAllAssociative('SELECT DISTINCT moule FROM palettes WHERE moule IS NOT NULL ORDER BY moule'), 'moule'),
            'familles' => array_column($conn->fetchAllAssociative('SELECT DISTINCT famille FROM palettes WHERE famille IS NOT NULL ORDER BY famille'), 'famille'),
            'rayons' => array_column($conn->fetchAllAssociative('SELECT DISTINCT rayon FROM palettes WHERE rayon IS NOT NULL ORDER BY rayon'), 'rayon'),
        ];
    }

    public function countPalettesGroupedByRayon(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.rayon, COUNT(p.id) as total, SUM(CASE WHEN p.cartonsRestants > 0 THEN 1 ELSE 0 END) as occupied')
            ->where('p.rayon IS NOT NULL')
            ->groupBy('p.rayon')
            ->getQuery()->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['rayon']] = ['total' => (int)$row['total'], 'occupied' => (int)$row['occupied']];
        }
        return $result;
    }

    public function findByRayon(string $rayon, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.operation', 'o')
            ->leftJoin('p.coldRoom', 'c')
            ->where('p.rayon = :rayon')
            ->setParameter('rayon', $rayon)
            ->orderBy('p.createdAt', 'DESC');

        if (!empty($filters['search'])) {
            $qb->andWhere('p.codePalette LIKE :search OR p.espece LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['client'])) {
            $qb->andWhere('o.client = :client')->setParameter('client', $filters['client']);
        }
        if (!empty($filters['espece'])) {
            $qb->andWhere('p.espece = :espece')->setParameter('espece', $filters['espece']);
        }

        return $qb->getQuery()->getResult();
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
