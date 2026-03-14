<?php

namespace App\Entity;

use App\Enum\StockStatus;
use App\Repository\StockEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockEntryRepository::class)]
#[ORM\Table(name: 'stock_entries')]
#[ORM\HasLifecycleCallbacks]
class StockEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $bonReceptionNumber = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cdLotClient = null;

    #[ORM\ManyToOne(targetEntity: ColdRoom::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ColdRoom $coldRoom;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $productName;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $famille = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $qualite = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $moule = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nombreCartons = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    #[Assert\Positive]
    private string $quantityTons;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $poidsNet = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $rayon = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $codePalette = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $codeRack = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transporteur = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $immatriculation = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $temperature = null;

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

    #[ORM\OneToOne(mappedBy: 'stockEntry', targetEntity: StockItem::class, cascade: ['persist'])]
    private ?StockItem $stockItem = null;

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

    public function getBonReceptionNumber(): ?string
    {
        return $this->bonReceptionNumber;
    }

    public function setBonReceptionNumber(?string $bonReceptionNumber): static
    {
        $this->bonReceptionNumber = $bonReceptionNumber;
        return $this;
    }

    public function getCdLotClient(): ?string
    {
        return $this->cdLotClient;
    }

    public function setCdLotClient(?string $cdLotClient): static
    {
        $this->cdLotClient = $cdLotClient;
        return $this;
    }

    public function getFamille(): ?string
    {
        return $this->famille;
    }

    public function setFamille(?string $famille): static
    {
        $this->famille = $famille;
        return $this;
    }

    public function getQualite(): ?string
    {
        return $this->qualite;
    }

    public function setQualite(?string $qualite): static
    {
        $this->qualite = $qualite;
        return $this;
    }

    public function getMoule(): ?string
    {
        return $this->moule;
    }

    public function setMoule(?string $moule): static
    {
        $this->moule = $moule;
        return $this;
    }

    public function getNombreCartons(): ?int
    {
        return $this->nombreCartons;
    }

    public function setNombreCartons(?int $nombreCartons): static
    {
        $this->nombreCartons = $nombreCartons;
        return $this;
    }

    public function getPoidsNet(): ?string
    {
        return $this->poidsNet;
    }

    public function setPoidsNet(?string $poidsNet): static
    {
        $this->poidsNet = $poidsNet;
        return $this;
    }

    public function getRayon(): ?string
    {
        return $this->rayon;
    }

    public function setRayon(?string $rayon): static
    {
        $this->rayon = $rayon;
        return $this;
    }

    public function getCodePalette(): ?string
    {
        return $this->codePalette;
    }

    public function setCodePalette(?string $codePalette): static
    {
        $this->codePalette = $codePalette;
        return $this;
    }

    public function getCodeRack(): ?string
    {
        return $this->codeRack;
    }

    public function setCodeRack(?string $codeRack): static
    {
        $this->codeRack = $codeRack;
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

    public function getTemperature(): ?string
    {
        return $this->temperature;
    }

    public function setTemperature(?string $temperature): static
    {
        $this->temperature = $temperature;
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

    public function getStockItem(): ?StockItem
    {
        return $this->stockItem;
    }

    public function setStockItem(?StockItem $stockItem): static
    {
        $this->stockItem = $stockItem;
        if ($stockItem !== null && $stockItem->getStockEntry() !== $this) {
            $stockItem->setStockEntry($this);
        }
        return $this;
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

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }
}
