<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'entité Invoice (calculs, statuts, numérotation).
 * Aucune base de données requise.
 */
class InvoiceTest extends TestCase
{
    private function makeClient(): Client
    {
        $client = new Client();
        $client->setCompanyName('MagroFresh')
               ->setCode('MF001')
               ->setAddress('Zone Industrielle')
               ->setPhone('0600000000')
               ->setEmail('contact@magrofresh.ma');
        return $client;
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setFirstName('Nada')->setLastName('Azrour')
             ->setEmail('n@test.com')->setPassword('x');
        return $user;
    }

    private function makeLine(float $total): InvoiceLine
    {
        $line = new InvoiceLine();
        $line->setDesignation('Test')->setQuantity(1.0)->setUnitPrice($total)->setLineTotal($total);
        return $line;
    }

    // ── Numéro de facture ──────────────────────────────────────────────────

    public function testInvoiceNumberGeneratedOnConstruct(): void
    {
        $invoice = new Invoice();
        $this->assertNotEmpty($invoice->getInvoiceNumber());
    }

    public function testInvoiceNumberStartsWithFAC(): void
    {
        $number = Invoice::generateInvoiceNumber();
        $this->assertStringStartsWith('FAC-', $number);
    }

    public function testInvoiceNumberContainsDate(): void
    {
        $number = Invoice::generateInvoiceNumber();
        $this->assertStringContainsString(date('Ymd'), $number);
    }

    public function testTwoInvoicesHaveDifferentNumbers(): void
    {
        $inv1 = new Invoice();
        $inv2 = new Invoice();
        $this->assertNotSame($inv1->getInvoiceNumber(), $inv2->getInvoiceNumber());
    }

    // ── calculateTotals ───────────────────────────────────────────────────

    public function testCalculateTotalsWithNoLines(): void
    {
        $invoice = new Invoice();
        $invoice->setClient($this->makeClient())
                ->setCreatedBy($this->makeUser())
                ->setPeriodStart(new \DateTime('2026-06-01'))
                ->setPeriodEnd(new \DateTime('2026-06-30'));

        $invoice->calculateTotals();

        $this->assertSame('0.00', $invoice->getTotalHt());
        $this->assertSame('0.00', $invoice->getTotalTtc());
    }

    public function testCalculateTotalsWithOneLine(): void
    {
        $invoice = new Invoice();
        $invoice->setClient($this->makeClient())
                ->setCreatedBy($this->makeUser())
                ->setPeriodStart(new \DateTime('2026-06-01'))
                ->setPeriodEnd(new \DateTime('2026-06-30'))
                ->addLine($this->makeLine(1500.00));

        $invoice->calculateTotals();

        $this->assertSame('1500.00', $invoice->getTotalHt());
    }

    public function testCalculateTotalsWithMultipleLines(): void
    {
        $invoice = new Invoice();
        $invoice->setClient($this->makeClient())
                ->setCreatedBy($this->makeUser())
                ->setPeriodStart(new \DateTime('2026-06-01'))
                ->setPeriodEnd(new \DateTime('2026-06-30'))
                ->addLine($this->makeLine(1000.00))
                ->addLine($this->makeLine(500.50))
                ->addLine($this->makeLine(249.50));

        $invoice->calculateTotals();

        $this->assertSame('1750.00', $invoice->getTotalHt());
    }

    public function testTotalTtcEqualsTotalHtWhenNoVat(): void
    {
        $invoice = new Invoice();
        $invoice->setClient($this->makeClient())
                ->setCreatedBy($this->makeUser())
                ->setPeriodStart(new \DateTime('2026-06-01'))
                ->setPeriodEnd(new \DateTime('2026-06-30'))
                ->addLine($this->makeLine(3000.00));

        $invoice->calculateTotals();

        $this->assertSame($invoice->getTotalHt(), $invoice->getTotalTtc());
    }

    // ── getVatAmount ──────────────────────────────────────────────────────

    public function testGetVatAmountZeroWhenNoVat(): void
    {
        $invoice = new Invoice();
        $invoice->setTotalHt('5000.00')->setVatRate('0.00');

        $this->assertSame(0.0, $invoice->getVatAmount());
    }

    public function testGetVatAmountCalculatedCorrectly(): void
    {
        $invoice = new Invoice();
        $invoice->setTotalHt('10000.00')->setVatRate('20.00');

        $this->assertEqualsWithDelta(2000.0, $invoice->getVatAmount(), 0.01);
    }

    // ── isDraft / isEditable ──────────────────────────────────────────────

    public function testIsDraftWhenStatusIsDraft(): void
    {
        $invoice = new Invoice();
        $invoice->setStatus(InvoiceStatus::DRAFT);

        $this->assertTrue($invoice->isDraft());
    }

    public function testIsNotDraftWhenPendingValidation(): void
    {
        $invoice = new Invoice();
        $invoice->setStatus(InvoiceStatus::PENDING_VALIDATION);

        $this->assertFalse($invoice->isDraft());
    }

    public function testIsNotDraftWhenValidated(): void
    {
        $invoice = new Invoice();
        $invoice->setStatus(InvoiceStatus::VALIDATED);

        $this->assertFalse($invoice->isDraft());
    }

    public function testIsEditableOnlyInDraftStatus(): void
    {
        $invoice = new Invoice();

        $invoice->setStatus(InvoiceStatus::DRAFT);
        $this->assertTrue($invoice->isEditable());

        $invoice->setStatus(InvoiceStatus::PENDING_VALIDATION);
        $this->assertFalse($invoice->isEditable());

        $invoice->setStatus(InvoiceStatus::VALIDATED);
        $this->assertFalse($invoice->isEditable());
    }

    // ── addLine / removeLine ──────────────────────────────────────────────

    public function testAddLineSetsBackReference(): void
    {
        $invoice = new Invoice();
        $line    = $this->makeLine(100.0);

        $invoice->addLine($line);

        $this->assertSame($invoice, $line->getInvoice());
        $this->assertCount(1, $invoice->getLines());
    }

    public function testAddLineTwiceDoesNotDuplicate(): void
    {
        $invoice = new Invoice();
        $line    = $this->makeLine(100.0);

        $invoice->addLine($line);
        $invoice->addLine($line); // même ligne deux fois

        $this->assertCount(1, $invoice->getLines());
    }

    public function testRemoveLineReleasesBackReference(): void
    {
        $invoice = new Invoice();
        $line    = $this->makeLine(200.0);
        $invoice->addLine($line);

        $invoice->removeLine($line);

        $this->assertCount(0, $invoice->getLines());
        $this->assertNull($line->getInvoice());
    }

    // ── Statut par défaut ─────────────────────────────────────────────────

    public function testNewInvoiceIsInDraftStatus(): void
    {
        $invoice = new Invoice();
        $this->assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
    }
}
