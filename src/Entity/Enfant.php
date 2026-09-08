<?php

namespace App\Entity;

use App\Repository\EnfantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le prénom d'affichage est une donnée strictement locale : il n'est jamais
 * transmis au LLM (cf. Product Specification §6 — AnonymisationService en garantit l'application).
 */
#[ORM\Entity(repositoryClass: EnfantRepository::class)]
class Enfant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $prenomAffichage;

    #[ORM\Column]
    private int $niveauActuel = 1;

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    /** @var Collection<int, Session> */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'enfant', orphanRemoval: true)]
    private Collection $sessions;

    public function __construct(string $prenomAffichage)
    {
        $this->prenomAffichage = $prenomAffichage;
        $this->dateCreation = new \DateTimeImmutable();
        $this->sessions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrenomAffichage(): string
    {
        return $this->prenomAffichage;
    }

    public function setPrenomAffichage(string $prenomAffichage): static
    {
        $this->prenomAffichage = $prenomAffichage;

        return $this;
    }

    public function getNiveauActuel(): int
    {
        return $this->niveauActuel;
    }

    public function setNiveauActuel(int $niveauActuel): static
    {
        $this->niveauActuel = max(1, min(6, $niveauActuel));

        return $this;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    /** @return Collection<int, Session> */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }
}
