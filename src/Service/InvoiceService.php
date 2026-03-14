<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use App\Repository\StockExitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvoiceService
{
    private const DEFAULT_DAILY_RATE = '0.50'; // €/tonne/jour

    public function __construct(
        private EntityManagerInterface $em,
        private InvoiceRepository $invoiceRepo,
        private StockExitRepository $exitRepo,
        private AuditService $auditService
    ) {}

    public function generateInvoice(Client $client, \DateTimeInterface $startDate, \DateTimeInterface $endDate, User $createdBy): Invoice
    {
        $exits = $this->exitRepo->findValidatedByClientAndPeriod($client, $startDate, $endDate);

        if (empty($exits)) {
            throw new BadRequestHttpException('Aucune sortie validée pour cette période');
        }

        $invoice = new Invoice();
        $invoice->setClient($client);
        $invoice->setPeriodStart($startDate);
        $invoice->setPeriodEnd($endDate);
        $invoice->setCreatedBy($createdBy);
        $invoice->setStatus(InvoiceStatus::DRAFT);

        foreach ($exits as $exit) {
            $line = InvoiceLine::createFromStockExit($exit, self::DEFAULT_DAILY_RATE);
            $invoice->addLine($line);
        }

        $invoice->calculateTotals();

        $this->em->persist($invoice);
        $this->em->flush();

        $this->auditService->log('create', $invoice, $createdBy);

        return $invoice;
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

    public function validateInvoice(Invoice $invoice, User $validatedBy): void
    {
        if ($invoice->getStatus() !== InvoiceStatus::PENDING_VALIDATION) {
            throw new BadRequestHttpException('Cette facture ne peut pas être validée');
        }

        $invoice->setStatus(InvoiceStatus::VALIDATED);
        $invoice->setValidatedBy($validatedBy);
        $invoice->setValidatedAt(new \DateTime());

        $this->em->flush();

        $this->auditService->log('validate', $invoice, $validatedBy);
    }

    public function deleteInvoice(Invoice $invoice, User $user): void
    {
        if (!$invoice->isEditable()) {
            throw new BadRequestHttpException('Cette facture ne peut pas être supprimée');
        }

        $this->auditService->log('delete', $invoice, $user);

        $this->em->remove($invoice);
        $this->em->flush();
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
