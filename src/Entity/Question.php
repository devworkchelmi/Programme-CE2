<?php

namespace App\Entity;

use App\Entity\Enum\TypeQuestion;
use App\Repository\QuestionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TexteGenere::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private TexteGenere $texteGenere;

    #[ORM\Column(enumType: TypeQuestion::class)]
    private TypeQuestion $type;

    #[ORM\Column(type: 'text')]
    private string $enonce;

    /** @var array<int, string>|null Choix proposés — uniquement pour une question de type QCM */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $choix = null;

    #[ORM\Column(type: 'text')]
    private string $reponseAttendueOuCriteres;

    #[ORM\OneToOne(targetEntity: Reponse::class, mappedBy: 'question', cascade: ['persist', 'remove'])]
    private ?Reponse $reponse = null;

    public function __construct(TypeQuestion $type, string $enonce, string $reponseAttendueOuCriteres, ?array $choix = null)
    {
        $this->type = $type;
        $this->enonce = $enonce;
        $this->reponseAttendueOuCriteres = $reponseAttendueOuCriteres;
        $this->choix = $choix;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTexteGenere(): TexteGenere
    {
        return $this->texteGenere;
    }

    public function setTexteGenere(TexteGenere $texteGenere): static
    {
        $this->texteGenere = $texteGenere;

        return $this;
    }

    public function getType(): TypeQuestion
    {
        return $this->type;
    }

    public function getEnonce(): string
    {
        return $this->enonce;
    }

    /** @return array<int, string>|null */
    public function getChoix(): ?array
    {
        return $this->choix;
    }

    public function getReponseAttendueOuCriteres(): string
    {
        return $this->reponseAttendueOuCriteres;
    }

    public function getReponse(): ?Reponse
    {
        return $this->reponse;
    }

    public function setReponse(Reponse $reponse): static
    {
        $this->reponse = $reponse;
        $reponse->setQuestion($this);

        return $this;
    }
}
