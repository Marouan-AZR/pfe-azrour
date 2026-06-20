<?php

namespace App\Tests\Unit\Security;

use App\Entity\Invoice;
use App\Entity\User;
use App\Security\Voter\InvoiceVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Tests unitaires du voter de contrôle d'accès aux factures.
 * Vérifie que chaque rôle n'a accès qu'aux opérations autorisées.
 */
class InvoiceVoterTest extends TestCase
{
    private InvoiceVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new InvoiceVoter();
    }

    private function makeToken(array $roles): TokenInterface
    {
        $user = new User();
        $user->setFirstName('Test')->setLastName('User')
             ->setEmail('u@test.com')->setPassword('x')
             ->setRoles($roles);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function vote(string $attribute, array $roles): int
    {
        return $this->voter->vote($this->makeToken($roles), new Invoice(), [$attribute]);
    }

    // ── INVOICE_CREATE ────────────────────────────────────────────────────

    public function testChefStockCanCreateInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::CREATE, ['ROLE_CHEF_STOCK']);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testControleurCannotCreateInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::CREATE, ['ROLE_CONTROLEUR']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testClientCannotCreateInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::CREATE, ['ROLE_CLIENT']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── INVOICE_VALIDATE ──────────────────────────────────────────────────

    public function testDirecteurCanValidateInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::VALIDATE, ['ROLE_DIRECTEUR']);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testChefStockCannotValidateInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::VALIDATE, ['ROLE_CHEF_STOCK']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testControleurCannotValidateInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::VALIDATE, ['ROLE_CONTROLEUR']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── INVOICE_VIEW ──────────────────────────────────────────────────────

    public function testChefStockCanViewInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::VIEW, ['ROLE_CHEF_STOCK']);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDirecteurCanViewInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::VIEW, ['ROLE_DIRECTEUR']);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testPatronCannotViewInvoice(): void
    {
        // Patron hérite DIRECTEUR mais le voter l'exclut explicitement
        $result = $this->vote(InvoiceVoter::VIEW, ['ROLE_PATRON']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testControleurCannotViewInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::VIEW, ['ROLE_CONTROLEUR']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── INVOICE_SEND ──────────────────────────────────────────────────────

    public function testChefStockCanSendInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::SEND, ['ROLE_CHEF_STOCK']);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDirecteurCannotSendInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::SEND, ['ROLE_DIRECTEUR']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── INVOICE_DELETE ────────────────────────────────────────────────────

    public function testChefStockCanDeleteInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::DELETE, ['ROLE_CHEF_STOCK']);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDirecteurCanDeleteInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::DELETE, ['ROLE_DIRECTEUR']);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testControleurCannotDeleteInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::DELETE, ['ROLE_CONTROLEUR']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── INVOICE_EXPORT ────────────────────────────────────────────────────

    public function testChefStockCanExportInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::EXPORT, ['ROLE_CHEF_STOCK']);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testPatronCannotExportInvoice(): void
    {
        $result = $this->vote(InvoiceVoter::EXPORT, ['ROLE_PATRON']);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── Utilisateur non authentifié ───────────────────────────────────────

    public function testUnauthenticatedUserIsDenied(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $result = $this->voter->vote($token, new Invoice(), [InvoiceVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── Attribut inconnu ─────────────────────────────────────────────────

    public function testVoterAbstainsOnUnknownAttribute(): void
    {
        $result = $this->vote('INVOICE_UNKNOWN_ACTION', ['ROLE_CHEF_STOCK']);
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }
}
