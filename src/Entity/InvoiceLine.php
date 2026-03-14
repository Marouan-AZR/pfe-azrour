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

    #[ORM\ManyToOne(targetEntity: StockExit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StockExit $stockExit;

    #[ORM\Column(length: 255)]
    private string $productName;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $quantityTons;

    #[ORM\Column(type: 'integer')]
    private int $storageDays;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private string $dailyRatePerTon;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $lineTotal;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getStockExit(): StockExit
    {
        return $this->stockExit;
    }

    public function setStockExit(StockExit $stockExit): static
    {
        $this->stockExit = $stockExit;
        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;
        return $this;
    }

    public function getQuantityTons(): string
    {
        return $this->quantityTons;
    }

    public function setQuantityTons(string $quantityTons): static
    {
        $this->quantityTons = $quantityTons;
        return $this;
    }

    public function getStorageDays(): int
    {
        return $this->storageDays;
    }

    public function setStorageDays(int $storageDays): static
    {
        $this->storageDays = $storageDays;
        return $this;
    }

    public function getDailyRatePerTon(): string
    {
        return $this->dailyRatePerTon;
    }

    public function setDailyRatePerTon(string $dailyRatePerTon): static
    {
        $this->dailyRatePerTon = $dailyRatePerTon;
        return $this;
    }

    public function getLineTotal(): string
    {
        return $this->lineTotal;
    }

    public function setLineTotal(string $lineTotal): static
    {
        $this->lineTotal = $lineTotal;
        return $this;
    }

    public function calculateLineTotal(): void
    {
        $total = (float)$this->quantityTons * $this->storageDays * (float)$this->dailyRatePerTon;
        $this->lineTotal = number_format($total, 2, '.', '');
    }

    public static function createFromStockExit(StockExit $exit, string $dailyRate): self
    {
        $line = new self();
        $line->setStockExit($exit);
        $line->setProductName($exit->getProductName());
        $line->setQuantityTons($exit->getQuantityTons());
        $line->setStorageDays($exit->getStorageDays());
        $line->setDailyRatePerTon($dailyRate);
        $line->calculateLineTotal();
        return $line;
    }
}
