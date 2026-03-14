<?php

namespace App\Entity;

use App\Enum\StockStatus;
use App\Repository\StockExitRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockExitRepository::class)]
#[ORM\Table(name: 'stock_exits')]
#[ORM\HasLifecycleCallbacks]
class StockExit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $bonLivraisonNumber = null;

    #[ORM\ManyToOne(targetEntity: StockItem::class, inversedBy: 'stockExits')]
    #[ORM\JoinColumn(nullable: false)]
    private StockItem $stockItem;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    #[Assert\Positive]
    private string $quantityTons;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $destination = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transporteur = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $immatriculation = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $chauffeur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    #[ORM\Column(type: 'string', enumType: StockStatus::class)]
    private StockStatus $status = StockStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validatedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'integer')]
    private int $storageDays = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStockItem(): StockItem
    {
        return $this->stockItem;
    }

    public function setStockItem(StockItem $stockItem): static
    {
        $this->stockItem = $stockItem;
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

    public function getBonLivraisonNumber(): ?string
    {
        return $this->bonLivraisonNumber;
    }

    public function setBonLivraisonNumber(?string $bonLivraisonNumber): static
    {
        $this->bonLivraisonNumber = $bonLivraisonNumber;
        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): static
    {
        $this->destination = $destination;
        return $this;
    }

    public function getTransporteur(): ?string
    {
        return $this->transporteur;
    }

    public function setTransporteur(?string $transporteur): static
    {
        $this->transporteur = $transporteur;
        return $this;
    }

    public function getImmatriculation(): ?string
    {
        return $this->immatriculation;
    }

    public function setImmatriculation(?string $immatriculation): static
    {
        $this->immatriculation = $immatriculation;
        return $this;
    }

    public function getChauffeur(): ?string
    {
        return $this->chauffeur;
    }

    public function setChauffeur(?string $chauffeur): static
    {
        $this->chauffeur = $chauffeur;
        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;
        return $this;
    }

    public function getStatus(): StockStatus
    {
        return $this->status;
    }

    public function setStatus(StockStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
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

    public function calculateStorageDays(): int
    {
        return $this->stockItem->getStorageDays($this->validatedAt ?? new \DateTime());
    }

    public function isPending(): bool
    {
        return $this->status === StockStatus::PENDING;
    }

    public function isValidated(): bool
    {
        return $this->status === StockStatus::VALIDATED;
    }

    public function isRejected(): bool
    {
        return $this->status === StockStatus::REJECTED;
    }

    public function getClient(): Client
    {
        return $this->stockItem->getClient();
    }

    public function getColdRoom(): ColdRoom
    {
        return $this->stockItem->getColdRoom();
    }

    public function getProductName(): string
    {
        return $this->stockItem->getProductName();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }
}
