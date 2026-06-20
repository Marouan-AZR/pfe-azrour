<?php

namespace App\Tests\Unit\Twig;

use App\Twig\AuditExtension;
use PHPUnit\Framework\TestCase;

class AuditExtensionTest extends TestCase
{
    private AuditExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new AuditExtension();
    }

    // --- entity_label ---

    public function testEntityLabelKnownType(): void
    {
        $this->assertSame('Client', $this->ext->entityLabel('Client'));
    }

    public function testEntityLabelFicheDecharge(): void
    {
        $this->assertSame('Fiche de décharge', $this->ext->entityLabel('FicheDecharge'));
    }

    public function testEntityLabelFallsBackToRawName(): void
    {
        $this->assertSame('UnknownEntity', $this->ext->entityLabel('UnknownEntity'));
    }

    // --- getSummary ---

    public function testGetSummaryCreateAction(): void
    {
        $result = $this->ext->getSummary(['companyName' => 'ACME'], null, 'create', 'Client');
        $this->assertStringContainsString('Client', $result);
        $this->assertStringContainsString('ACME', $result);
    }

    public function testGetSummaryUpdateShowsChangedFieldCount(): void
    {
        $result = $this->ext->getSummary(
            ['companyName' => 'NewName', 'phone' => '0600'],
            ['companyName' => 'OldName', 'phone' => '0600'],
            'update',
            'Client'
        );
        $this->assertStringContainsString('1', $result); // 1 field changed
    }

    public function testGetSummaryDeleteAction(): void
    {
        $result = $this->ext->getSummary(null, ['companyName' => 'Gone Co'], 'delete', 'Client');
        $this->assertStringContainsString('Gone Co', $result);
    }

    public function testGetSummaryNullValues(): void
    {
        $result = $this->ext->getSummary(null, null, 'create', 'Client');
        $this->assertIsString($result);
    }

    // --- formatAudit (audit_readable filter) ---

    public function testFormatAuditCreateWithValues(): void
    {
        $result = $this->ext->formatAudit(['companyName' => 'Test SA'], 'create', null, 'Client');
        $this->assertStringContainsString('Raison sociale', $result);
        $this->assertStringContainsString('Test SA', $result);
    }

    public function testFormatAuditSkipsHiddenFields(): void
    {
        $result = $this->ext->formatAudit(['id' => 99, 'companyName' => 'Corp'], 'create', null, 'Client');
        $this->assertStringNotContainsString('>id<', $result);
        $this->assertStringContainsString('Corp', $result);
    }

    // --- formatCard (audit_card filter) ---

    public function testFormatCardReturnsHtmlString(): void
    {
        $result = $this->ext->formatCard(['companyName' => 'TestCo'], 'create', null, 'Client');
        $this->assertStringContainsString('Raison sociale', $result);
        $this->assertStringContainsString('TestCo', $result);
    }

    public function testFormatCardTranslatesDoctrineObjectRef(): void
    {
        $result = $this->ext->formatCard(
            ['client' => 'App\\Entity\\Client#5'],
            'update',
            ['client' => 'App\\Entity\\Client#3'],
            'Operation'
        );
        $this->assertStringNotContainsString('App\\Entity\\', $result);
    }

    public function testFormatCardTranslatesStatusValue(): void
    {
        $result = $this->ext->formatCard(
            ['status' => 'validee'],
            'update',
            ['status' => 'pending'],
            'Palette'
        );
        $this->assertStringContainsString('Validée', $result);
    }

    // --- getFilters ---

    public function testGetFiltersRegistersAllFilters(): void
    {
        $names = array_map(fn($f) => $f->getName(), $this->ext->getFilters());
        $this->assertContains('audit_readable', $names);
        $this->assertContains('audit_summary', $names);
        $this->assertContains('audit_card', $names);
        $this->assertContains('entity_label', $names);
    }
}
