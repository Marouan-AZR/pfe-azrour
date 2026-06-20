<?php

namespace App\Entity;

use App\Repository\TarifClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifClientRepository::class)]
#[ORM\Table(name: 'tarif_clients')]
#[ORM\HasLifecycleCallbacks]
class TarifClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Client $client;

    /** DH par kg — manipulation à l'entrée (séjour et forfait) */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 5)]
    private string $puManipEntree = '0.07000';

    /** DH par kg — manipulation à la sortie (séjour et forfait) */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 5)]
    private string $puManipSortie = '0.07000';

    /** DH par kg par jour — tarif séjour */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 5)]
    private string $puSejour = '0.00970';

    /** Montant fixe mensuel DH — tarif forfait */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $montantForfait = '0.00';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $updatedBy = null;

    public function getId(): ?int { return $this->id; }

    public function getClient(): Client { return $this->client; }
    public function setClient(Client $client): static { $this->client = $client; return $this; }

    public function getPuManipEntree(): float { return (float)$this->puManipEntree; }
    public function setPuManipEntree(float $v): static { $this->puManipEntree = (string)$v; return $this; }

    public function getPuManipSortie(): float { return (float)$this->puManipSortie; }
    public function setPuManipSortie(float $v): static { $this->puManipSortie = (string)$v; return $this; }

    public function getPuSejour(): float { return (float)$this->puSejour; }
    public function setPuSejour(float $v): static { $this->puSejour = (string)$v; return $this; }

    public function getMontantForfait(): float { return (float)$this->montantForfait; }
    public function setMontantForfait(float $v): static { $this->montantForfait = (string)$v; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function getUpdatedBy(): ?User { return $this->updatedBy; }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTime(); }

    public function setUpdatedBy(?User $user): static { $this->updatedBy = $user; return $this; }
}