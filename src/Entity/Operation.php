<?php

namespace App\Entity;

use App\Enum\OperationType;
use App\Enum\StockStatus;
use App\Repository\OperationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OperationRepository::class)]
#[ORM\Table(name: 'operations')]
#[ORM\HasLifecycleCallbacks]
class Operation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', enumType: OperationType::class)]
    private OperationType $type;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cdLotClient = null;

    #[ORM\Column(type: 'string', enumType: StockStatus::class)]
    private StockStatus $status = StockStatus::PENDING;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $dateReception;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $controleur = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validatedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transporteur = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $immatriculation = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $temperature = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroConteneur = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroAgrement = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    /** @var Collection<int, Palette> */
    #[ORM\OneToMany(mappedBy: 'operation', targetEntity: Palette::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $palettes;

    #[ORM\OneToOne(mappedBy: 'operation', targetEntity: FicheDecharge::class, cascade: ['persist', 'remove'])]
    private ?FicheDecharge $ficheDecharge = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $bonNumber = null;

    public function __construct()
    {
        $this->palettes = new ArrayCollection();
        $this->dateReception = new \DateTime();
    }

    public static function generateCode(OperationType $type): string
    {
        $prefix = $type === OperationType::ENTRY ? 'BR' : 'BS';
        return $prefix . '-' . date('Ym') . '-' . strtoupper(substr(md5(uniqid()), 0, 7));
    }

    public function getId(): ?int { return $this->id; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getType(): OperationType { return $this->type; }
    public function setType(OperationType $type): static { $this->type = $type; return $this; }

    public function getClient(): Client { return $this->client; }
    public function setClient(Client $client): static { $this->client = $client; return $this; }

    public function getCdLotClient(): ?string { return $this->cdLotClient; }
    public function setCdLotClient(?string $cdLotClient): static { $this->cdLotClient = $cdLotClient; return $this; }

    public function getStatus(): StockStatus { return $this->status; }
    public function setStatus(StockStatus $status): static { $this->status = $status; return $this; }

    public function getDateReception(): \DateTimeInterface { return $this->dateReception; }
    public function setDateReception(\DateTimeInterface $dateReception): static { $this->dateReception = $dateReception; return $this; }

    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getControleur(): ?User { return $this->controleur; }
    public function setControleur(?User $controleur): static { $this->controleur = $controleur; return $this; }

    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $validatedBy): static { $this->validatedBy = $validatedBy; return $this; }

    public function getValidatedAt(): ?\DateTimeInterface { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeInterface $validatedAt): static { $this->validatedAt = $validatedAt; return $this; }

    public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function setRejectionReason(?string $rejectionReason): static { $this->rejectionReason = $rejectionReason; return $this; }

    public function getTransporteur(): ?string { return $this->transporteur; }
    public function setTransporteur(?string $transporteur): static { $this->transporteur = $transporteur; return $this; }

    public function getImmatriculation(): ?string { return $this->immatriculation; }
    public function setImmatriculation(?string $immatriculation): static { $this->immatriculation = $immatriculation; return $this; }

    public function getTemperature(): ?string { return $this->temperature; }
    public function setTemperature(?string $temperature): static { $this->temperature = $temperature; return $this; }

    public function getNumeroConteneur(): ?string { return $this->numeroConteneur; }
    public function setNumeroConteneur(?string $numeroConteneur): static { $this->numeroConteneur = $numeroConteneur; return $this; }

    public function getNumeroAgrement(): ?string { return $this->numeroAgrement; }
    public function setNumeroAgrement(?string $numeroAgrement): static { $this->numeroAgrement = $numeroAgrement; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    public function getBonNumber(): ?string { return $this->bonNumber; }
    public function setBonNumber(?string $bonNumber): static { $this->bonNumber = $bonNumber; return $this; }

    /** @return Collection<int, Palette> */
    public function getPalettes(): Collection { return $this->palettes; }

    public function addPalette(Palette $palette): static
    {
        if (!$this->palettes->contains($palette)) {
            $this->palettes->add($palette);
            $palette->setOperation($this);
        }
        return $this;
    }

    public function removePalette(Palette $palette): static
    {
        if ($this->palettes->removeElement($palette)) {
            if ($palette->getOperation() === $this) {
                $palette->setOperation($this);
            }
        }
        return $this;
    }

    public function getFicheDecharge(): ?FicheDecharge { return $this->ficheDecharge; }
    public function setFicheDecharge(?FicheDecharge $ficheDecharge): static
    {
        $this->ficheDecharge = $ficheDecharge;
        if ($ficheDecharge !== null && $ficheDecharge->getOperation() !== $this) {
            $ficheDecharge->setOperation($this);
        }
        return $this;
    }

    // Computed properties
    public function getNombrePalettes(): int { return $this->palettes->count(); }

    public function getTotalCartons(): int
    {
        return $this->palettes->reduce(fn(int $t, Palette $p) => $t + ($p->getNombreCartons() ?? 0), 0);
    }

    public function getPoidsTotal(): float
    {
        return $this->palettes->reduce(fn(float $t, Palette $p) => $t + (float)($p->getPoidsTotal() ?? 0), 0.0);
    }

    public function getPoidsTotalTonnes(): float { return $this->getPoidsTotal() / 1000; }

    public function getEspeces(): array
    {
        $especes = [];
        foreach ($this->palettes as $palette) {
            $espece = $palette->getEspece();
            if ($espece && !in_array($espece, $especes)) {
                $especes[] = $espece;
            }
        }
        return $especes;
    }

    public function isPending(): bool { return $this->status === StockStatus::PENDING; }
    public function isValidated(): bool { return $this->status === StockStatus::VALIDATED; }
    public function isRejected(): bool { return $this->status === StockStatus::REJECTED; }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
