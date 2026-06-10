<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
#[ORM\HasLifecycleCallbacks]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $code;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroAgrement = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $companyName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomInterne = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomCommercial = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $rc = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ice = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $address;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private string $phone;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: StockItem::class)]
    private Collection $stockItems;

    public function __construct()
    {
        $this->stockItems = new ArrayCollection();
        $this->code = self::generateCode();
    }

    public static function generateCode(): string
    {
        return 'CLI-' . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    public function getId(): ?int { return $this->id; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getNumeroAgrement(): ?string { return $this->numeroAgrement; }
    public function setNumeroAgrement(?string $numeroAgrement): static { $this->numeroAgrement = $numeroAgrement; return $this; }

    public function getCompanyName(): string { return $this->companyName; }
    public function setCompanyName(string $companyName): static { $this->companyName = $companyName; return $this; }

    public function getNomInterne(): ?string { return $this->nomInterne; }
    public function setNomInterne(?string $nomInterne): static { $this->nomInterne = $nomInterne; return $this; }

    public function getNomCommercial(): ?string { return $this->nomCommercial; }
    public function setNomCommercial(?string $nomCommercial): static { $this->nomCommercial = $nomCommercial; return $this; }

    public function getRc(): ?string { return $this->rc; }
    public function setRc(?string $rc): static { $this->rc = $rc; return $this; }

    public function getIce(): ?string { return $this->ice; }
    public function setIce(?string $ice): static { $this->ice = $ice; return $this; }

    public function getAddress(): string { return $this->address; }
    public function setAddress(string $address): static { $this->address = $address; return $this; }

    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): static { $this->phone = $phone; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    public function getStockItems(): Collection { return $this->stockItems; }

    public function hasStock(): bool { return !$this->stockItems->isEmpty(); }

    public function getTotalStockTonnage(): float
    {
        return $this->stockItems->reduce(
            fn(float $total, StockItem $item) => $total + (float)$item->getQuantityTons(),
            0.0
        );
    }

    /** Returns the display name for internal usage (nomInterne if set, else companyName) */
    public function getDisplayName(): string
    {
        return $this->nomInterne ?: $this->companyName;
    }

    /** Returns the official name for invoices (nomCommercial if set, else companyName) */
    public function getOfficialName(): string
    {
        return $this->nomCommercial ?: $this->companyName;
    }

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

    public function __toString(): string
    {
        return ($this->nomInterne ?: $this->companyName) . ' (' . $this->code . ')';
    }
}
