<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ColdRoom;
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
            ->andWhere('o.type = :entryType')
            ->andWhere('o.status = :validated')
            ->andWhere('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excludedStatuses)')
            ->setParameter('client', $client)
            ->setParameter('entryType', 'entry')
            ->setParameter('validated', 'validated')
            ->setParameter('excludedStatuses', [PaletteStatus::SORTIE_COMPLETE, PaletteStatus::REJETEE])
            ->orderBy('o.dateReception', 'DESC')
            ->addOrderBy('p.espece', 'ASC');

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
        if (!empty($filters['cdLotClient'])) {
            $qb->andWhere('o.cdLotClient LIKE :cdLot')->setParameter('cdLot', '%' . $filters['cdLotClient'] . '%');
        }
        if (!empty($filters['dateReception'])) {
            $qb->andWhere('o.dateReception >= :drFrom AND o.dateReception < :drTo')
               ->setParameter('drFrom', $filters['dateReception'] . ' 00:00:00')
               ->setParameter('drTo', $filters['dateReception'] . ' 23:59:59');
        }

        return $qb->getQuery()->getResult();
    }

    public function getFilterValuesForClient(Client $client): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.espece, p.qualite, p.moule, p.famille, p.rayon, o.cdLotClient, cr.name as coldRoomName')
            ->join('p.operation', 'o')
            ->leftJoin('p.coldRoom', 'cr')
            ->where('o.client = :client')
            ->andWhere('o.type = :entryType')
            ->andWhere('o.status = :validated')
            ->andWhere('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excluded)')
            ->setParameter('client', $client)
            ->setParameter('entryType', 'entry')
            ->setParameter('validated', 'validated')
            ->setParameter('excluded', [PaletteStatus::SORTIE_COMPLETE, PaletteStatus::REJETEE])
            ->getQuery()->getResult();

        $especes = $qualites = $moules = $familles = $rayons = $cdLots = $chambres = [];
        foreach ($rows as $r) {
            if ($r['espece'])       $especes[]  = $r['espece'];
            if ($r['qualite'])      $qualites[] = $r['qualite'];
            if ($r['moule'])        $moules[]   = $r['moule'];
            if ($r['famille'])      $familles[] = $r['famille'];
            if ($r['rayon'])        $rayons[]   = $r['rayon'];
            if ($r['cdLotClient'])  $cdLots[]   = $r['cdLotClient'];
            if ($r['coldRoomName']) $chambres[] = $r['coldRoomName'];
        }
        sort($chambres);
        return [
            'especes'  => array_values(array_unique($especes)),
            'qualites' => array_values(array_unique($qualites)),
            'moules'   => array_values(array_unique($moules)),
            'familles' => array_values(array_unique($familles)),
            'rayons'   => array_values(array_unique($rayons)),
            'cdLots'   => array_values(array_unique($cdLots)),
            'chambres' => array_values(array_unique($chambres)),
        ];
    }

    public function findStockByClient(?Client $client = null, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.operation', 'o')
            ->where('o.status = :validated')
            ->andWhere('o.type = :entryType')
            ->setParameter('validated', 'validated')
            ->setParameter('entryType', 'entry');

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
            ->andWhere('o.type = :entryType')
            ->setParameter('client', $client)
            ->setParameter('validated', 'validated')
            ->setParameter('entryType', 'entry')
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
        $excluded = [PaletteStatus::SORTIE_COMPLETE->value, PaletteStatus::REJETEE->value];

        $rows = $this->createQueryBuilder('p')
            ->select('p.rayon,
                COUNT(p.id) as total,
                SUM(CASE WHEN p.cartonsRestants > 0 THEN 1 ELSE 0 END) as occupied,
                SUM(p.poidsRestant) as totalWeight,
                SUM(p.cartonsRestants) as totalCartons')
            ->join('p.operation', 'o')
            ->where('p.rayon IS NOT NULL')
            ->andWhere('p.status NOT IN (:excluded)')
            ->andWhere('o.type = :entryType')
            ->andWhere('o.status = :validated')
            ->setParameter('excluded', $excluded)
            ->setParameter('entryType', 'entry')
            ->setParameter('validated', 'validated')
            ->groupBy('p.rayon')
            ->getQuery()->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['rayon']] = [
                'total'        => (int)   $row['total'],
                'occupied'     => (int)   $row['occupied'],
                'totalWeight'  => (float) ($row['totalWeight']  ?? 0),
                'totalCartons' => (int)   ($row['totalCartons'] ?? 0),
            ];
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
            ->andWhere('p.status NOT IN (:excluded)')
            ->andWhere('o.type = :entryType')
            ->andWhere('o.status = :validated')
            ->andWhere('p.cartonsRestants > 0')
            ->setParameter('excluded', [PaletteStatus::SORTIE_COMPLETE->value, PaletteStatus::REJETEE->value])
            ->setParameter('entryType', 'entry')
            ->setParameter('validated', 'validated')
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
        if (!empty($filters['qualite'])) {
            $qb->andWhere('p.qualite = :qualite')->setParameter('qualite', $filters['qualite']);
        }
        if (!empty($filters['coldRoom'])) {
            $qb->andWhere('p.coldRoom = :coldRoom')->setParameter('coldRoom', $filters['coldRoom']);
        }

        return $qb->getQuery()->getResult();
    }

    public function findActivePalettesForColdRoom(ColdRoom $coldRoom): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.operation', 'o')
            ->where('p.coldRoom = :coldRoom')
            ->setParameter('coldRoom', $coldRoom)
            ->andWhere('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excluded)')
            ->andWhere('o.type = :entryType')
            ->andWhere('o.status = :validated')
            ->setParameter('excluded', [PaletteStatus::SORTIE_COMPLETE->value, PaletteStatus::REJETEE->value])
            ->setParameter('entryType', 'entry')
            ->setParameter('validated', 'validated')
            ->getQuery()->getResult();
    }

    public function countActivePalettes(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.operation', 'o')
            ->where('o.status = :validated')
            ->andWhere('o.type = :type')
            ->andWhere('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excluded)')
            ->setParameter('validated', 'validated')
            ->setParameter('type', 'entry')
            ->setParameter('excluded', [PaletteStatus::SORTIE_COMPLETE, PaletteStatus::REJETEE])
            ->getQuery()->getSingleScalarResult();
    }

    public function findClientsWithCriticalStock(float $threshold = 0.20): array
    {
        $conn = $this->getEntityManager()->getConnection();
        // Include SORTIE_COMPLETE palettes so their cartons_restants=0 is counted
        // in the total, giving the true remaining ratio. Only exclude REJETEE.
        $sql = '
            SELECT c.id AS client_id, c.company_name, c.code AS client_code,
                   SUM(p.nombre_cartons)   AS total,
                   SUM(p.cartons_restants) AS restants
            FROM palettes p
            INNER JOIN operations o ON p.operation_id = o.id
            INNER JOIN clients   c ON o.client_id = c.id
            WHERE o.status = :validated
              AND o.type   = :type
              AND p.status != :excl_rejected
            GROUP BY c.id, c.company_name, c.code
            HAVING SUM(p.nombre_cartons) > 0
               AND (SUM(p.cartons_restants) / SUM(p.nombre_cartons)) < :threshold
            ORDER BY (SUM(p.cartons_restants) / SUM(p.nombre_cartons)) ASC
        ';

        $rows = $conn->fetchAllAssociative($sql, [
            'validated'     => 'validated',
            'type'          => 'entry',
            'excl_rejected' => PaletteStatus::REJETEE->value,
            'threshold'     => $threshold,
        ]);

        return array_map(fn($row) => [
            'clientId'   => (int) $row['client_id'],
            'name'       => $row['company_name'],
            'code'       => $row['client_code'],
            'restants'   => (int) $row['restants'],
            'total'      => (int) $row['total'],
            'percentage' => round(((int)$row['restants'] / (int)$row['total']) * 100, 1),
        ], $rows);
    }

    /**
     * Returns active clients whose remaining stock weight is below
     * (threshold * totalCapacityKg) — the same metric shown in the client list.
     * Clients with zero stock are included (they are at 0% of capacity).
     */
    public function findClientsWithLowCapacityPct(float $totalCapacityKg, float $threshold = 0.20): array
    {
        if ($totalCapacityKg <= 0) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $sql  = '
            SELECT c.id AS client_id, c.company_name, c.code AS client_code,
                   COALESCE(SUM(p.poids_restant),   0) AS weight_kg,
                   COALESCE(SUM(p.cartons_restants), 0) AS restants,
                   COALESCE(SUM(p.nombre_cartons),   0) AS total
            FROM clients c
            LEFT JOIN operations o
                   ON o.client_id = c.id
                  AND o.status    = :validated
                  AND o.type      = :type
            LEFT JOIN palettes p
                   ON p.operation_id = o.id
                  AND p.status NOT IN (:excl1, :excl2)
            WHERE c.is_active = 1
            GROUP BY c.id, c.company_name, c.code
            HAVING (COALESCE(SUM(p.poids_restant), 0) / :capacityKg) * 100 < :threshold
            ORDER BY weight_kg ASC
        ';

        $rows = $conn->fetchAllAssociative($sql, [
            'validated'  => 'validated',
            'type'       => 'entry',
            'excl1'      => PaletteStatus::SORTIE_COMPLETE->value,
            'excl2'      => PaletteStatus::REJETEE->value,
            'capacityKg' => $totalCapacityKg,
            'threshold'  => $threshold * 100,
        ]);

        return array_map(fn($row) => [
            'clientId'   => (int)   $row['client_id'],
            'name'       => $row['company_name'],
            'code'       => $row['client_code'],
            'restants'   => (int)   $row['restants'],
            'total'      => (int)   $row['total'],
            'percentage' => $totalCapacityKg > 0
                ? round(((float)$row['weight_kg'] / $totalCapacityKg) * 100, 1)
                : 0.0,
        ], $rows);
    }

    public function getStockGroupedByClient(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('c.companyName as clientName, SUM(p.poidsRestant) as totalWeight, SUM(p.cartonsRestants) as totalCartons')
            ->join('p.operation', 'o')
            ->join('o.client', 'c')
            ->where('o.status = :validated')
            ->andWhere('o.type = :entryType')
            ->andWhere('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excluded)')
            ->setParameter('validated', 'validated')
            ->setParameter('entryType', 'entry')
            ->setParameter('excluded', [PaletteStatus::SORTIE_COMPLETE, PaletteStatus::REJETEE])
            ->groupBy('c.id, c.companyName')
            ->orderBy('totalWeight', 'DESC')
            ->getQuery()->getResult();

        // poidsRestant is stored in kg — convert to tonnes for display
        return array_map(fn($row) => [
            'name'     => $row['clientName'],
            'quantity' => round((float) ($row['totalWeight'] ?? 0) / 1000.0, 2),
            'cartons'  => (int)         ($row['totalCartons'] ?? 0),
        ], $rows);
    }

    public function getTotalStockByClient(Client $client): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.poidsRestant) as total')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('o.status = :validated')
            ->andWhere('o.type = :entryType')
            ->andWhere('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excluded)')
            ->setParameter('client', $client)
            ->setParameter('validated', 'validated')
            ->setParameter('entryType', 'entry')
            ->setParameter('excluded', [PaletteStatus::SORTIE_COMPLETE->value, PaletteStatus::REJETEE->value])
            ->getQuery()->getSingleScalarResult();

        // poidsRestant stored in kg — return tonnes
        return round((float) ($result ?? 0) / 1000.0, 2);
    }
}
