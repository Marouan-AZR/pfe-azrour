<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Palette;
use App\Entity\TarifClient;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\OperationType;
use App\Enum\PaletteStatus;
use App\Enum\StockStatus;
use App\Enum\TypeStockage;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvoiceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InvoiceRepository $invoiceRepo,
        private AuditService $auditService,
        private ClientRepository $clientRepo,
    ) {}

    /**
     * Génère une facture pour un client et un mois donné.
     * Calcul basé sur les tarifs configurés dans TarifClient.
     */
    public function generateInvoice(Client $client, int $year, int $month, User $createdBy, string $storageType = 'sejour'): Invoice
    {
        $tarif = $this->em->getRepository(TarifClient::class)->findOneBy(['client' => $client]);
        if (!$tarif) {
            throw new BadRequestHttpException(
                "Aucun tarif configuré pour « {$client->getCompanyName()} ». Définissez d'abord la grille tarifaire."
            );
        }

        $monthStart   = new \DateTime(sprintf('%d-%02d-01 00:00:00', $year, $month));
        $daysInMonth  = (int)(new \DateTime("last day of $year-$month"))->format('d');
        $monthEnd     = new \DateTime(sprintf('%d-%02d-%02d 23:59:59', $year, $month, $daysInMonth));
        $periodEnd    = new \DateTime(sprintf('%d-%02d-%02d', $year, $month, $daysInMonth));

        $invoice = new Invoice();
        $invoice->setClient($client);
        $invoice->setPeriodStart($monthStart);
        $invoice->setPeriodEnd($periodEnd);
        $invoice->setCreatedBy($createdBy);
        $invoice->setStatus(InvoiceStatus::DRAFT);

        // ── 1. Manipulation d'ENTRÉE ─────────────────────────────────────
        $entryKg = $this->getTotalEntryWeightForMonth($client, $monthStart, $monthEnd);
        if ($entryKg > 0 && $tarif->getPuManipEntree() > 0) {
            $line = new InvoiceLine();
            $line->setDesignation("MANIPULATION DE PALETTES A L'ENTREE");
            $line->setQuantity($entryKg);
            $line->setUnitPrice($tarif->getPuManipEntree());
            $line->setLineTotal($entryKg * $tarif->getPuManipEntree());
            $invoice->addLine($line);
        }

        // ── 2. Manipulation de SORTIE ────────────────────────────────────
        $exitKg = $this->getTotalExitWeightForMonth($client, $monthStart, $monthEnd);
        if ($exitKg > 0 && $tarif->getPuManipSortie() > 0) {
            $line = new InvoiceLine();
            $line->setDesignation('MANIPULATION DE PALETTES A LA SORTIE');
            $line->setQuantity($exitKg);
            $line->setUnitPrice($tarif->getPuManipSortie());
            $line->setLineTotal($exitKg * $tarif->getPuManipSortie());
            $invoice->addLine($line);
        }

        // ── 3. SÉJOUR (uniquement si type = séjour) ─────────────────────────
        // Cap: pour le mois en cours, ne facturer que les jours déjà écoulés
        $today = new \DateTime('today');
        $effectiveEnd = ($today < $monthEnd) ? $today : $monthEnd;

        if ($storageType === 'sejour' && $tarif->getPuSejour() > 0) {
            $sejourBatches = $this->getSejourBatches($client, $monthStart, $monthEnd, $daysInMonth, $effectiveEnd);
            foreach ($sejourBatches as $batch) {
                if ($batch['weightKg'] <= 0 || $batch['days'] <= 0) {
                    continue;
                }
                $line = new InvoiceLine();
                $line->setDesignation('SEJOUR AU STOCKAGE');
                $line->setQuantity($batch['weightKg']);
                $line->setQuantityJours($batch['days']);
                $line->setUnitPrice($tarif->getPuSejour());
                $line->setLineTotal($batch['weightKg'] * $batch['days'] * $tarif->getPuSejour());
                $invoice->addLine($line);
            }
        }

        // ── 4. FORFAIT (uniquement si type = forfait) ───────────────────────
        if ($storageType === 'forfait' && $tarif->getMontantForfait() > 0) {
            $forfaitData = $this->getForfaitData($client, $monthEnd);
            if ($forfaitData['weightKg'] > 0) {
                $line = new InvoiceLine();
                $line->setDesignation('SEJOUR AU STOCKAGE');
                $line->setQuantity($forfaitData['weightKg']);
                $line->setQuantityJours($daysInMonth);
                $line->setUnitPriceDisplay('FORFAIT');
                $line->setUnitPrice(0);
                $line->setLineTotal($tarif->getMontantForfait());
                $invoice->addLine($line);
            }
        }

        $invoice->calculateTotals();
        $this->em->persist($invoice);
        $this->em->flush();
        $this->auditService->log('create', $invoice, $createdBy);

        return $invoice;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers de calcul
    // ─────────────────────────────────────────────────────────────────────

    /** Poids total (kg) des entrées validées du client dans le mois */
    private function getTotalEntryWeightForMonth(Client $client, \DateTime $start, \DateTime $end): float
    {
        $result = $this->em->createQueryBuilder()
            ->select('SUM(p.poidsTotal)')
            ->from(Palette::class, 'p')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('o.type = :entry')
            ->andWhere('o.status = :validated')
            ->andWhere('o.dateReception >= :start')
            ->andWhere('o.dateReception <= :end')
            ->setParameter('client', $client)
            ->setParameter('entry', OperationType::ENTRY)
            ->setParameter('validated', StockStatus::VALIDATED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()->getSingleScalarResult();

        return (float)($result ?? 0);
    }

    /** Poids total (kg) des sorties validées du client dans le mois */
    private function getTotalExitWeightForMonth(Client $client, \DateTime $start, \DateTime $end): float
    {
        $result = $this->em->createQueryBuilder()
            ->select('SUM(p.poidsTotal)')
            ->from(Palette::class, 'p')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('o.type = :exit')
            ->andWhere('o.status = :validated')
            ->andWhere('o.validatedAt >= :start')
            ->andWhere('o.validatedAt <= :end')
            ->setParameter('client', $client)
            ->setParameter('exit', OperationType::EXIT)
            ->setParameter('validated', StockStatus::VALIDATED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()->getSingleScalarResult();

        return (float)($result ?? 0);
    }

    /**
     * Groupes séjour par nombre de jours identique (§4.5 du cahier des charges).
     *
     * Règle des jours (par palette) :
     * - Entrée PENDANT le mois → jours = dernier_jour_mois − jour_entrée + 1
     * - Entrée AVANT le mois   → jours = nombre_de_jours_du_mois (repart de J1)
     * - Sortie PENDANT le mois → jours = jour_sortie (si entrée avant) ou jour_sortie − jour_entrée + 1
     *
     * Les palettes ayant la même durée sont regroupées sur une seule ligne.
     */
    private function getSejourBatches(Client $client, \DateTime $monthStart, \DateTime $monthEnd, int $daysInMonth, \DateTime $effectiveEnd): array
    {
        $palettes = $this->em->createQueryBuilder()
            ->select('p', 'o')
            ->from(Palette::class, 'p')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('o.type = :entry')
            ->andWhere('o.status = :validated')
            ->andWhere('p.typeStockage = :sejour')
            ->andWhere('o.dateReception <= :effectiveEnd')
            ->setParameter('client', $client)
            ->setParameter('entry', OperationType::ENTRY)
            ->setParameter('validated', StockStatus::VALIDATED)
            ->setParameter('sejour', TypeStockage::SEJOUR)
            ->setParameter('effectiveEnd', $effectiveEnd)
            ->getQuery()->getResult();

        // Jour effectif de fin : aujourd'hui si mois en cours, dernier jour du mois sinon
        $effectiveDay = (int)$effectiveEnd->format('j');

        $byDays = [];

        foreach ($palettes as $palette) {
            $entryDate = $palette->getOperation()->getDateReception();

            // Palette sortie avant le début du mois → ne pas facturer
            if ($palette->getStatus() === PaletteStatus::SORTIE_COMPLETE) {
                $exitDate = $this->findExitDate($palette);
                if ($exitDate !== null && $exitDate < $monthStart) {
                    continue;
                }
            }

            $weight = (float)$palette->getPoidsTotal();
            if ($weight <= 0) {
                continue;
            }

            // Calcul des jours de présence dans le mois (plafonné au jour effectif)
            if ($entryDate >= $monthStart) {
                $days = $effectiveDay - (int)$entryDate->format('j') + 1;
            } else {
                $days = $effectiveDay;
            }

            // Réduire si la palette sort pendant ce mois (la date de sortie réelle prime)
            if ($palette->getStatus() === PaletteStatus::SORTIE_COMPLETE) {
                $exitDate = $this->findExitDate($palette);
                if ($exitDate !== null && $exitDate >= $monthStart && $exitDate <= $monthEnd) {
                    $dayOfExit = (int)$exitDate->format('j');
                    $days = $entryDate >= $monthStart
                        ? $dayOfExit - (int)$entryDate->format('j') + 1
                        : $dayOfExit;
                }
            }

            $days = max(1, $days);

            $byDays[$days] = ($byDays[$days] ?? 0.0) + $weight;
        }

        // Trier par durée croissante, retourner une ligne par groupe
        ksort($byDays);
        $batches = [];
        foreach ($byDays as $days => $weightKg) {
            if ($weightKg > 0) {
                $batches[] = ['weightKg' => round($weightKg, 2), 'days' => $days];
            }
        }

        return $batches;
    }

    /**
     * Poids total (kg) des palettes forfait présentes dans le mois (encore en stock ou sorties ce mois).
     */
    private function getForfaitData(Client $client, \DateTime $monthEnd): array
    {
        $result = $this->em->createQueryBuilder()
            ->select('SUM(p.poidsTotal)')
            ->from(Palette::class, 'p')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('o.type = :entry')
            ->andWhere('o.status = :validated')
            ->andWhere('p.typeStockage = :forfait')
            ->andWhere('o.dateReception <= :monthEnd')
            ->andWhere('p.status NOT IN (:done)')
            ->setParameter('client', $client)
            ->setParameter('entry', OperationType::ENTRY)
            ->setParameter('validated', StockStatus::VALIDATED)
            ->setParameter('forfait', TypeStockage::FORFAIT)
            ->setParameter('monthEnd', $monthEnd)
            ->setParameter('done', [PaletteStatus::SORTIE_COMPLETE, PaletteStatus::ANNULEE, PaletteStatus::REJETEE])
            ->getQuery()->getSingleScalarResult();

        return ['weightKg' => round((float)($result ?? 0), 2)];
    }

    /** Trouve la date de sortie validée d'une palette source */
    private function findExitDate(Palette $sourcePalette): ?\DateTimeInterface
    {
        try {
            $row = $this->em->createQueryBuilder()
                ->select('o.validatedAt')
                ->from(Palette::class, 'ep')
                ->join('ep.operation', 'o')
                ->where('ep.sourcePalette = :src')
                ->andWhere('o.status = :validated')
                ->setParameter('src', $sourcePalette)
                ->setParameter('validated', StockStatus::VALIDATED)
                ->orderBy('o.validatedAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();

            return $row ? $row['validatedAt'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Récapitulatif mensuel pour le tableau de bord
    // ─────────────────────────────────────────────────────────────────────

    /** Retourne le résumé mensuel par client : entrées/sorties/CA */
    public function getMonthSummary(int $year, int $month): array
    {
        $monthStart = new \DateTime(sprintf('%d-%02d-01 00:00:00', $year, $month));
        $daysInMonth = (int)(new \DateTime("last day of $year-$month"))->format('d');
        $monthEnd = new \DateTime(sprintf('%d-%02d-%02d 23:59:59', $year, $month, $daysInMonth));

        $clients = $this->clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']);
        $summary = [];

        foreach ($clients as $client) {
            $entryKg = $this->getTotalEntryWeightForMonth($client, $monthStart, $monthEnd);
            $exitKg  = $this->getTotalExitWeightForMonth($client, $monthStart, $monthEnd);

            // Stock en cours (palettes validées, non sorties complètes)
            $stockKg = (float)($this->em->createQueryBuilder()
                ->select('SUM(p.poidsRestant)')
                ->from(Palette::class, 'p')
                ->join('p.operation', 'o')
                ->where('o.client = :client')
                ->andWhere('o.type = :entry')
                ->andWhere('o.status = :validated')
                ->andWhere('p.cartonsRestants > 0')
                ->setParameter('client', $client)
                ->setParameter('entry', OperationType::ENTRY)
                ->setParameter('validated', StockStatus::VALIDATED)
                ->getQuery()->getSingleScalarResult() ?? 0);

            // CA : total HT des factures validées de ce mois
            $ca = (float)($this->em->createQueryBuilder()
                ->select('SUM(i.totalHt)')
                ->from(Invoice::class, 'i')
                ->where('i.client = :client')
                ->andWhere('i.status = :validated')
                ->andWhere('i.periodStart >= :start')
                ->andWhere('i.periodStart <= :end')
                ->setParameter('client', $client)
                ->setParameter('validated', InvoiceStatus::VALIDATED)
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()->getSingleScalarResult() ?? 0);

            if ($entryKg > 0 || $exitKg > 0 || $stockKg > 0 || $ca > 0) {
                $summary[] = [
                    'client'  => $client,
                    'ca'      => $ca,
                    'entryKg' => $entryKg,
                    'exitKg'  => $exitKg,
                    'stockKg' => $stockKg,
                ];
            }
        }

        return $summary;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Workflow factures
    // ─────────────────────────────────────────────────────────────────────

    public function validateInvoice(Invoice $invoice, User $validatedBy): void
    {
        if (!in_array($invoice->getStatus(), [InvoiceStatus::PENDING_VALIDATION, InvoiceStatus::DRAFT])) {
            throw new BadRequestHttpException('Cette facture ne peut pas être validée.');
        }
        $invoice->setStatus(InvoiceStatus::VALIDATED);
        $invoice->setValidatedBy($validatedBy);
        $invoice->setValidatedAt(new \DateTime());
        $this->em->flush();
        $this->auditService->log('validate', $invoice, $validatedBy);
    }

    public function submitForValidation(Invoice $invoice, User $user): void
    {
        if (!$invoice->isDraft()) {
            throw new BadRequestHttpException('Cette facture ne peut pas être soumise.');
        }
        $invoice->setStatus(InvoiceStatus::PENDING_VALIDATION);
        $this->em->flush();
        $this->auditService->log('update', $invoice, $user, null, ['status' => 'pending_validation']);
    }

    public function getPendingValidation(): array { return $this->invoiceRepo->findPendingValidation(); }
    public function getByClient(Client $client): array { return $this->invoiceRepo->findByClient($client); }
    public function getTotalRevenue(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return $this->invoiceRepo->getTotalRevenue($from, $to);
    }
}