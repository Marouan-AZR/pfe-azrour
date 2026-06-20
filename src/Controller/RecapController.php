<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Operation;
use App\Enum\OperationType;
use App\Enum\StockStatus;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecapController extends AbstractController
{
    #[Route('/recap', name: 'app_recap')]
    public function recap(
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepository
    ): Response {
        if (
            !$this->isGranted('ROLE_CHEF_STOCK') &&
            !$this->isGranted('ROLE_DIRECTEUR') &&
            !$this->isGranted('ROLE_PATRON')
        ) {
            throw $this->createAccessDeniedException();
        }

        $year        = (int) ($request->query->get('year') ?? date('Y'));
        $clientId    = $request->query->get('client') ?: null;
        $monthFilter = $request->query->get('month') ? (int) $request->query->get('month') : null;

        $allClients = $clientRepository->findBy(['isActive' => true], ['companyName' => 'ASC']);

        if ($monthFilter && $monthFilter >= 1 && $monthFilter <= 12) {
            $startOfYear = new \DateTime(sprintf('%d-%02d-01 00:00:00', $year, $monthFilter));
            $endOfYear   = (clone $startOfYear)->modify('last day of this month')->setTime(23, 59, 59);
        } else {
            $monthFilter = null;
            $startOfYear = new \DateTime("$year-01-01 00:00:00");
            $endOfYear   = new \DateTime("$year-12-31 23:59:59");
        }
        $beforeYear = new \DateTime("$year-01-01 00:00:00");

        // ── Helper: build base query for operations with eager palette load ──
        $buildOpQuery = function (OperationType $type, ?\DateTime $from, ?\DateTime $to) use ($em, $clientId): array {
            $qb = $em->createQueryBuilder()
                ->select('o', 'c', 'p')
                ->from(Operation::class, 'o')
                ->join('o.client', 'c')
                ->leftJoin('o.palettes', 'p')
                ->where('o.type = :type')
                ->andWhere('o.status = :status')
                ->setParameter('type', $type)
                ->setParameter('status', StockStatus::VALIDATED)
                ->orderBy('c.companyName', 'ASC');

            if ($from) {
                $qb->andWhere('o.dateReception >= :from')->setParameter('from', $from);
            }
            if ($to) {
                $qb->andWhere('o.dateReception <= :to')->setParameter('to', $to);
            }
            if ($clientId) {
                $qb->andWhere('c.id = :cid')->setParameter('cid', $clientId);
            }

            return $qb->getQuery()->getResult();
        };

        // ── Operations of the selected year ──
        $entryOps = $buildOpQuery(OperationType::ENTRY, $startOfYear, $endOfYear);
        $exitOps  = $buildOpQuery(OperationType::EXIT,  $startOfYear, $endOfYear);

        // ── Historical operations before the year (for initial stock) ──
        $histEntries = $buildOpQuery(OperationType::ENTRY, null, (clone $beforeYear)->modify('-1 second'));
        $histExits   = $buildOpQuery(OperationType::EXIT,  null, (clone $beforeYear)->modify('-1 second'));

        // ── Invoices for the year, grouped by [clientId][month] ──
        $invoiceQb = $em->createQueryBuilder()
            ->select('i', 'c')
            ->from(Invoice::class, 'i')
            ->join('i.client', 'c')
            ->where('i.periodEnd >= :start')
            ->andWhere('i.periodEnd <= :end')
            ->setParameter('start', $startOfYear)
            ->setParameter('end', $endOfYear);

        if ($clientId) {
            $invoiceQb->andWhere('c.id = :cid')->setParameter('cid', $clientId);
        }

        $invoicesByMonth = []; // [clientId][month] => total MAD
        foreach ($invoiceQb->getQuery()->getResult() as $inv) {
            $cid   = $inv->getClient()->getId();
            $month = (int) $inv->getPeriodEnd()->format('n');
            $invoicesByMonth[$cid][$month] = ($invoicesByMonth[$cid][$month] ?? 0.0) + (float) $inv->getTotalTtc();
        }

        // ── Compute initial stock per client (history before the year) ──
        $clientNames   = [];
        $initialStocks = []; // [clientId] => kg

        foreach ($histEntries as $op) {
            $cid = $op->getClient()->getId();
            $clientNames[$cid] = $op->getClient()->getCompanyName();
            foreach ($op->getPalettes() as $p) {
                $initialStocks[$cid] = ($initialStocks[$cid] ?? 0.0) + (float) $p->getPoidsTotal();
            }
        }
        foreach ($histExits as $op) {
            $cid = $op->getClient()->getId();
            $clientNames[$cid] = $op->getClient()->getCompanyName();
            foreach ($op->getPalettes() as $p) {
                $cartons = $p->getCartonsReellementSortis() ?? $p->getNombreCartons();
                $initialStocks[$cid] = ($initialStocks[$cid] ?? 0.0) - $cartons * (float) $p->getPoidsCarton();
            }
        }

        // ── Monthly data per client (current year) ──
        $monthly = []; // [clientId][month] => [entry, exit]

        foreach ($entryOps as $op) {
            $cid   = $op->getClient()->getId();
            $month = (int) $op->getDateReception()->format('n');
            $clientNames[$cid] = $op->getClient()->getCompanyName();
            foreach ($op->getPalettes() as $p) {
                $monthly[$cid][$month]['entry'] = ($monthly[$cid][$month]['entry'] ?? 0.0)
                    + (float) $p->getPoidsTotal();
            }
        }
        foreach ($exitOps as $op) {
            $cid   = $op->getClient()->getId();
            $month = (int) $op->getDateReception()->format('n');
            $clientNames[$cid] = $op->getClient()->getCompanyName();
            foreach ($op->getPalettes() as $p) {
                $cartons = $p->getCartonsReellementSortis() ?? $p->getNombreCartons();
                $monthly[$cid][$month]['exit'] = ($monthly[$cid][$month]['exit'] ?? 0.0)
                    + $cartons * (float) $p->getPoidsCarton();
            }
        }

        // ── Build final structure: [month][clientId] with running stock ──
        $allCids  = array_unique(array_merge(array_keys($monthly), array_keys($initialStocks)));
        $byMonth  = []; // [month][clientId] => row
        $runningStocks = []; // [clientId] => float (updated as we iterate months)

        foreach ($allCids as $cid) {
            $runningStocks[$cid] = max(0.0, $initialStocks[$cid] ?? 0.0);
        }

        for ($m = 1; $m <= 12; $m++) {
            foreach ($allCids as $cid) {
                $entry   = $monthly[$cid][$m]['entry'] ?? 0.0;
                $exit    = $monthly[$cid][$m]['exit']  ?? 0.0;
                $facture = $invoicesByMonth[$cid][$m]  ?? 0.0;

                $runningStocks[$cid] = max(0.0, $runningStocks[$cid] + $entry - $exit);
                $stock = $runningStocks[$cid];

                // Show row only if there is activity or non-zero stock this month
                if ($entry > 0 || $exit > 0 || $facture > 0) {
                    $byMonth[$m][$cid] = [
                        'name'    => $clientNames[$cid] ?? '—',
                        'entry'   => $entry,
                        'exit'    => $exit,
                        'stock'   => $stock,
                        'facture' => $facture,
                    ];
                }
            }

            // Sort clients alphabetically within the month
            if (isset($byMonth[$m])) {
                uasort($byMonth[$m], fn($a, $b) => strcmp($a['name'], $b['name']));
            }
        }

        // Remove empty months
        $byMonth = array_filter($byMonth, fn($rows) => count($rows) > 0);

        // ── Month totals ──
        $monthTotals = [];
        foreach ($byMonth as $m => $rows) {
            $monthTotals[$m] = ['entry' => 0.0, 'exit' => 0.0, 'stock' => 0.0, 'facture' => 0.0];
            foreach ($rows as $row) {
                $monthTotals[$m]['entry']   += $row['entry'];
                $monthTotals[$m]['exit']    += $row['exit'];
                $monthTotals[$m]['stock']   += $row['stock'];
                $monthTotals[$m]['facture'] += $row['facture'];
            }
        }

        // ── Grand total ──
        $grandTotal = ['entry' => 0.0, 'exit' => 0.0, 'stock' => 0.0, 'facture' => 0.0];
        foreach ($monthTotals as $t) {
            $grandTotal['entry']   += $t['entry'];
            $grandTotal['exit']    += $t['exit'];
            $grandTotal['facture'] += $t['facture'];
        }
        // Grand stock = sum of end-of-year running stocks for each client
        foreach ($runningStocks as $s) {
            $grandTotal['stock'] += $s;
        }

        $years = range((int) date('Y'), max(2020, (int) date('Y') - 8));

        return $this->render('dashboard/recap.html.twig', [
            'byMonth'      => $byMonth,
            'monthTotals'  => $monthTotals,
            'grandTotal'   => $grandTotal,
            'year'         => $year,
            'years'        => $years,
            'clients'      => $allClients,
            'clientFilter' => $clientId,
            'monthFilter'  => $monthFilter,
        ]);
    }
}
