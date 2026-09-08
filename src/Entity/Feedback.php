<?php

namespace App\Entity;

use App\Repository\FeedbackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeedbackRepository::class)]
class Feedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Reponse::class, inversedBy: 'feedback')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Reponse $reponse;

    #[ORM\Column(type: 'text')]
    private string $texteFeedback;

    public function __construct(string $texteFeedback)
    {
        $this->texteFeedback = $texteFeedback;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReponse(): Reponse
    {
        return $this->reponse;
    }

    public function setReponse(Reponse $reponse): static
    {
        $this->reponse = $reponse;

        return $this;
    }

    public function getTexteFeedback(): string
    {
        return $this->texteFeedback;
    }
}
