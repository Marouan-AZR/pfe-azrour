<?php

namespace App\Tests\Unit\Service;

use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Service\AuditService;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests unitaires du workflow de validation des factures (InvoiceService).
 * Aucune base de données requise.
 */
class InvoiceServiceWorkflowTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private InvoiceRepository&MockObject      $invoiceRepo;
    private AuditService&MockObject           $auditService;
    private InvoiceService                    $service;

    protected function setUp(): void
    {
        $this->em           = $this->createMock(EntityManagerInterface::class);
        $this->invoiceRepo  = $this->createMock(InvoiceRepository::class);
        $this->auditService = $this->createMock(AuditService::class);

        $this->service = new InvoiceService(
            $this->em,
            $this->invoiceRepo,
            $this->auditService,
            $this->createMock(ClientRepository::class),
        );
    }

    private function makeUser(string $role = 'ROLE_CHEF_STOCK'): User
    {
        $user = new User();
        $user->setFirstName('Test')->setLastName('User')
             ->setEmail('u@test.com')->setPassword('x')
             ->setRoles([$role]);
        return $user;
    }

    private function makeInvoice(InvoiceStatus $status): Invoice
    {
        $invoice = new Invoice();
        $invoice->setStatus($status);
        return $invoice;
    }

    // ── submitForValidation ───────────────────────────────────────────────

    public function testSubmitForValidationChangesDraftToPending(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $user    = $this->makeUser();

        $this->em->expects($this->once())->method('flush');
        $this->auditService->expects($this->once())->method('log');

        $this->service->submitForValidation($invoice, $user);

        $this->assertSame(InvoiceStatus::PENDING_VALIDATION, $invoice->getStatus());
    }

    public function testSubmitForValidationThrowsWhenNotDraft(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::PENDING_VALIDATION);
        $user    = $this->makeUser();

        $this->expectException(BadRequestHttpException::class);

        $this->service->submitForValidation($invoice, $user);
    }

    public function testSubmitForValidationThrowsWhenAlreadyValidated(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::VALIDATED);
        $user    = $this->makeUser();

        $this->expectException(BadRequestHttpException::class);

        $this->service->submitForValidation($invoice, $user);
    }

    // ── validateInvoice ───────────────────────────────────────────────────

    public function testValidateInvoiceFromDraftChangesStatusToValidated(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $user    = $this->makeUser('ROLE_DIRECTEUR');

        $this->em->expects($this->once())->method('flush');
        $this->auditService->expects($this->once())->method('log');

        $this->service->validateInvoice($invoice, $user);

        $this->assertSame(InvoiceStatus::VALIDATED, $invoice->getStatus());
    }

    public function testValidateInvoiceFromPendingSetsValidatedBy(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::PENDING_VALIDATION);
        $user    = $this->makeUser('ROLE_DIRECTEUR');

        $this->em->expects($this->once())->method('flush');
        $this->auditService->expects($this->once())->method('log');

        $this->service->validateInvoice($invoice, $user);

        $this->assertSame($user, $invoice->getValidatedBy());
        $this->assertNotNull($invoice->getValidatedAt());
    }

    public function testValidateInvoiceThrowsWhenAlreadyValidated(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::VALIDATED);
        $user    = $this->makeUser('ROLE_DIRECTEUR');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cette facture ne peut pas être validée.');

        $this->service->validateInvoice($invoice, $user);
    }

    public function testValidateInvoiceThrowsWhenSent(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::SENT);
        $user    = $this->makeUser('ROLE_DIRECTEUR');

        $this->expectException(BadRequestHttpException::class);

        $this->service->validateInvoice($invoice, $user);
    }

    public function testValidateInvoiceSetsValidatedAtTimestamp(): void
    {
        $before  = new \DateTime('-1 second');
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $user    = $this->makeUser('ROLE_DIRECTEUR');

        $this->em->method('flush');
        $this->auditService->method('log');

        $this->service->validateInvoice($invoice, $user);

        $after = new \DateTime('+1 second');
        $this->assertGreaterThanOrEqual($before, $invoice->getValidatedAt());
        $this->assertLessThanOrEqual($after, $invoice->getValidatedAt());
    }

    // ── Audit trail ───────────────────────────────────────────────────────

    public function testSubmitForValidationLogsAuditEvent(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $user    = $this->makeUser();

        $this->em->method('flush');
        $this->auditService->expects($this->once())
            ->method('log')
            ->with('update', $invoice, $user, null, ['status' => 'pending_validation']);

        $this->service->submitForValidation($invoice, $user);
    }

    public function testValidateInvoiceLogsAuditEvent(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $user    = $this->makeUser('ROLE_DIRECTEUR');

        $this->em->method('flush');
        $this->auditService->expects($this->once())
            ->method('log')
            ->with('validate', $invoice, $user);

        $this->service->validateInvoice($invoice, $user);
    }
}
