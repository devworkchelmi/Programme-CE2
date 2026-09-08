<?php

namespace App\Entity;

use App\Entity\Enum\StatutSession;
use App\Entity\Enum\TypeSession;
use App\Repository\SessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Enfant::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private Enfant $enfant;

    #[ORM\Column]
    private \DateTimeImmutable $date;

    #[ORM\Column(enumType: TypeSession::class)]
    private TypeSession $type;

    #[ORM\Column]
    private int $niveauVise;

    #[ORM\Column(enumType: StatutSession::class)]
    private StatutSession $statut;

    #[ORM\OneToOne(targetEntity: TexteGenere::class, mappedBy: 'session', cascade: ['persist', 'remove'])]
    private ?TexteGenere $texteGenere = null;

    #[ORM\OneToOne(targetEntity: AjustementNiveau::class, mappedBy: 'session', cascade: ['persist', 'remove'])]
    private ?AjustementNiveau $ajustementNiveau = null;

    public function __construct(Enfant $enfant, TypeSession $type, int $niveauVise)
    {
        $this->enfant = $enfant;
        $this->type = $type;
        $this->niveauVise = $niveauVise;
        $this->date = new \DateTimeImmutable();
        $this->statut = StatutSession::GENEREE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnfant(): Enfant
    {
        return $this->enfant;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getType(): TypeSession
    {
        return $this->type;
    }

    public function getNiveauVise(): int
    {
        return $this->niveauVise;
    }

    public function getStatut(): StatutSession
    {
        return $this->statut;
    }

    public function setStatut(StatutSession $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getTexteGenere(): ?TexteGenere
    {
        return $this->texteGenere;
    }

    public function setTexteGenere(TexteGenere $texteGenere): static
    {
        $this->texteGenere = $texteGenere;
        $texteGenere->setSession($this);

        return $this;
    }

    public function getAjustementNiveau(): ?AjustementNiveau
    {
        return $this->ajustementNiveau;
    }

    public function setAjustementNiveau(AjustementNiveau $ajustementNiveau): static
    {
        $this->ajustementNiveau = $ajustementNiveau;
        $ajustementNiveau->setSession($this);

        return $this;
    }
}
