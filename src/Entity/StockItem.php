<?php

namespace App\Entity;

use App\Repository\StockItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockItemRepository::class)]
#[ORM\Table(name: 'stock_items')]
class StockItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'stockItems')]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: ColdRoom::class, inversedBy: 'stockItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ColdRoom $coldRoom;

    #[ORM\OneToOne(inversedBy: 'stockItem', targetEntity: StockEntry::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StockEntry $stockEntry;

    #[ORM\Column(length: 255)]
    private string $productName;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $quantityTons;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $entryDate;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $rackCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paletteCode = null;

    #[ORM\OneToMany(mappedBy: 'stockItem', targetEntity: StockExit::class)]
    private Collection $stockExits;

    public function __construct()
    {
        $this->stockExits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getColdRoom(): ColdRoom
    {
        return $this->coldRoom;
    }

    public function setColdRoom(ColdRoom $coldRoom): static
    {
        $this->coldRoom = $coldRoom;
        return $this;
    }

    public function getStockEntry(): StockEntry
    {
        return $this->stockEntry;
    }

    public function setStockEntry(StockEntry $stockEntry): static
    {
        $this->stockEntry = $stockEntry;
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

    public function getEntryDate(): \DateTimeInterface
    {
        return $this->entryDate;
    }

    public function setEntryDate(\DateTimeInterface $entryDate): static
    {
        $this->entryDate = $entryDate;
        return $this;
    }

    public function getStockExits(): Collection
    {
        return $this->stockExits;
    }

    public function getStorageDays(?\DateTimeInterface $referenceDate = null): int
    {
        $reference = $referenceDate ?? new \DateTime();
        return $this->entryDate->diff($reference)->days;
    }

    public function getRemainingQuantity(): float
    {
        $exitedQuantity = $this->stockExits
            ->filter(fn(StockExit $exit) => $exit->getStatus()->value === 'validated')
            ->reduce(fn(float $total, StockExit $exit) => $total + (float)$exit->getQuantityTons(), 0.0);
        
        return (float)$this->quantityTons - $exitedQuantity;
    }

    public function hasAvailableQuantity(float $quantity): bool
    {
        return $this->getRemainingQuantity() >= $quantity;
    }

    public function getRackCode(): ?string
    {
        return $this->rackCode;
    }

    public function setRackCode(?string $rackCode): static
    {
        $this->rackCode = $rackCode;
        return $this;
    }

    public function getPaletteCode(): ?string
    {
        return $this->paletteCode;
    }

    public function setPaletteCode(?string $paletteCode): static
    {
        $this->paletteCode = $paletteCode;
        return $this;
    }
}
