<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_lines')]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 255)]
    private string $designation = '';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $quantity = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4)]
    private string $unitPrice = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $lineTotal = '0.00';

    /** Nombre de jours (colonne séjour) */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantityJours = null;

    /** Libellé PU personnalisé (ex: "FORFAIT") */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $unitPriceDisplay = null;

    public function getId(): ?int { return $this->id; }

    public function getInvoice(): ?Invoice { return $this->invoice; }
    public function setInvoice(?Invoice $invoice): static { $this->invoice = $invoice; return $this; }

    public function getDesignation(): string { return $this->designation; }
    public function setDesignation(string $designation): static { $this->designation = $designation; return $this; }

    public function getQuantity(): float { return (float)$this->quantity; }
    public function setQuantity(float $quantity): static { $this->quantity = (string)$quantity; return $this; }

    public function getUnitPrice(): float { return (float)$this->unitPrice; }
    public function setUnitPrice(float $unitPrice): static { $this->unitPrice = (string)$unitPrice; return $this; }

    public function getLineTotal(): string { return $this->lineTotal; }
    public function setLineTotal(float $lineTotal): static { $this->lineTotal = number_format($lineTotal, 2, '.', ''); return $this; }

    public function getQuantityJours(): ?int { return $this->quantityJours; }
    public function setQuantityJours(?int $v): static { $this->quantityJours = $v; return $this; }

    public function getUnitPriceDisplay(): ?string { return $this->unitPriceDisplay; }
    public function setUnitPriceDisplay(?string $v): static { $this->unitPriceDisplay = $v; return $this; }
}
