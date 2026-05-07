<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\OperationType;
use App\Repository\InvoiceRepository;
use App\Repository\OperationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvoiceService
{
    private const PU_MANUTENTION = 0.07; // DH/kg for entries and exits
    private const PU_SEJOUR = 8.0;       // DH/tonne/day
    private const FORFAIT = 375000;       // Fixed amount

    public function __construct(
        private EntityManagerInterface $em,
        private InvoiceRepository $invoiceRepo,
        private OperationRepository $operationRepo,
        private AuditService $auditService
    ) {}

    public function generateInvoice(Client $client, \DateTimeInterface $startDate, \DateTimeInterface $endDate, User $createdBy): Invoice
    {
        $operations = $this->operationRepo->findByClientAndPeriod($client, $startDate, $endDate);

        $invoice = new Invoice();
        $invoice->setClient($client);
        $invoice->setPeriodStart($startDate);
        $invoice->setPeriodEnd($endDate);
        $invoice->setCreatedBy($createdBy);
        $invoice->setStatus(InvoiceStatus::DRAFT);

        // Calculate entries total weight
        $totalEntryKg = 0;
        $totalExitKg = 0;
        foreach ($operations as $op) {
            if ($op->getType() === OperationType::ENTRY) {
                $totalEntryKg += $op->getPoidsTotal();
            } else {
                $totalExitKg += $op->getPoidsTotal();
            }
        }

        // Line: Entrée
        if ($totalEntryKg > 0) {
            $line = new InvoiceLine();
            $line->setDesignation('Manutention Entrée');
            $line->setQuantity($totalEntryKg);
            $line->setUnitPrice(self::PU_MANUTENTION);
            $line->setLineTotal($totalEntryKg * self::PU_MANUTENTION);
            $invoice->addLine($line);
        }

        // Line: Sortie
        if ($totalExitKg > 0) {
            $line = new InvoiceLine();
            $line->setDesignation('Manutention Sortie');
            $line->setQuantity($totalExitKg);
            $line->setUnitPrice(self::PU_MANUTENTION);
            $line->setLineTotal($totalExitKg * self::PU_MANUTENTION);
            $invoice->addLine($line);
        }

        // Line: Séjour (days × tonnage × 8 DH)
        $days = $startDate->diff($endDate)->days ?: 30;
        $tonnageStocked = $this->getTonnageStockedForPeriod($client, $startDate, $endDate);
        if ($tonnageStocked > 0) {
            $sejourTotal = $days * $tonnageStocked * self::PU_SEJOUR;
            $line = new InvoiceLine();
            $line->setDesignation('Séjour (' . $days . ' jours × ' . number_format($tonnageStocked, 3) . ' T × 8 DH)');
            $line->setQuantity($tonnageStocked);
            $line->setUnitPrice($days * self::PU_SEJOUR);
            $line->setLineTotal($sejourTotal);
            $invoice->addLine($line);
        }

        // Line: Forfait
        $line = new InvoiceLine();
        $line->setDesignation('Forfait mensuel');
        $line->setQuantity(1);
        $line->setUnitPrice(self::FORFAIT);
        $line->setLineTotal(self::FORFAIT);
        $invoice->addLine($line);

        $invoice->calculateTotals();

        $this->em->persist($invoice);
        $this->em->flush();

        $this->auditService->log('create', $invoice, $createdBy);

        return $invoice;
    }

    private function getTonnageStockedForPeriod(Client $client, \DateTimeInterface $start, \DateTimeInterface $end): float
    {
        // Get total weight of palettes in stock for this client during the period
        $result = $this->em->createQueryBuilder()
            ->select('SUM(p.poidsRestant) / 1000')
            ->from(\App\Entity\Palette::class, 'p')
            ->join('p.operation', 'o')
            ->where('o.client = :client')
            ->andWhere('o.type = :type')
            ->andWhere('o.dateReception <= :end')
            ->andWhere('p.cartonsRestants > 0')
            ->setParameter('client', $client)
            ->setParameter('type', OperationType::ENTRY)
            ->setParameter('end', $end)
            ->getQuery()->getSingleScalarResult();

        return (float)($result ?? 0);
    }

    public function validateInvoice(Invoice $invoice, User $validatedBy): void
    {
        if ($invoice->getStatus() !== InvoiceStatus::PENDING_VALIDATION && $invoice->getStatus() !== InvoiceStatus::DRAFT) {
            throw new BadRequestHttpException('Cette facture ne peut pas être validée');
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
            throw new BadRequestHttpException('Cette facture ne peut pas être soumise');
        }
        $invoice->setStatus(InvoiceStatus::PENDING_VALIDATION);
        $this->em->flush();
        $this->auditService->log('update', $invoice, $user, null, ['status' => 'pending_validation']);
    }

    public function getPendingValidation(): array
    {
        return $this->invoiceRepo->findPendingValidation();
    }

    public function getByClient(Client $client): array
    {
        return $this->invoiceRepo->findByClient($client);
    }

    public function getTotalRevenue(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return $this->invoiceRepo->getTotalRevenue($from, $to);
    }
}
