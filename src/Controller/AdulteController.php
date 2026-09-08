<?php

namespace App\Controller;

use App\Entity\Enfant;
use App\Entity\Enum\StatutSession;
use App\Entity\Enum\TypeQuestion;
use App\Entity\Enum\TypeSession;
use App\Entity\Question;
use App\Entity\Session;
use App\Entity\TexteGenere;
use App\Repository\EnfantRepository;
use App\Repository\SessionRepository;
use App\Service\AjustementService;
use App\Service\Llm\TexteGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écrans côté adulte (cf. Product Specification §2.4 et §2.5).
 */
#[IsGranted('ROLE_ADULTE')]
class AdulteController extends AbstractController
{
    #[Route('/adulte/suivi', name: 'adulte_suivi')]
    public function suivi(EnfantRepository $enfantRepository, SessionRepository $sessionRepository, AjustementService $ajustementService): Response
    {
        // MVP : un seul enfant (cf. Product Specification §0) — on prend le premier connu.
        // TODO (jour 11-12) : remplacer par la sélection réelle une fois la fiche Enfant créée.
        $enfant = $enfantRepository->findOneBy([]);

        $sessionsJouees = $enfant instanceof Enfant
            ? $sessionRepository->lesPlusRecentesJouees($enfant, 10)
            : [];

        $tendance = array_map(
            static fn ($session) => $ajustementService->tauxReussite($session),
            $sessionsJouees,
        );

        // Affichage de la liste (tous statuts, y compris en attente de relecture) : distinct
        // de $sessionsJouees ci-dessus, qui sert uniquement au calcul de tendance.
        $sessionsAffichees = $enfant instanceof Enfant
            ? $sessionRepository->toutesRecentes($enfant, 10)
            : [];

        return $this->render('adulte/suivi.html.twig', [
            'enfant' => $enfant,
            'sessions' => $sessionsAffichees,
            'tendance' => array_reverse($tendance),
        ]);
    }

    /**
     * Démarre une nouvelle session pour l'enfant du MVP : génère un texte + des questions
     * (Appel 1, cf. Product Specification §3) et l'envoie en relecture avant de le proposer.
     *
     * Diagnostic pour la toute première session de l'enfant, standard ensuite — la version
     * complète du diagnostic initial (3 à 5 textes, cf. Product Specification §4) reste à
     * affiner plus tard ; pour l'instant on démarre simplement au niveau courant de l'enfant.
     */
    #[Route('/adulte/session/demarrer', name: 'adulte_session_demarrer', methods: ['POST'])]
    public function demarrerSession(
        EnfantRepository $enfantRepository,
        TexteGenerationService $texteGenerationService,
        EntityManagerInterface $em,
    ): Response {
        $enfant = $enfantRepository->findOneBy([]);

        if (null === $enfant) {
            throw $this->createNotFoundException('Aucune fiche enfant — crée-en une avec la commande app:creer-enfant.');
        }

        $type = $enfant->getSessions()->isEmpty() ? TypeSession::DIAGNOSTIC : TypeSession::STANDARD;
        $niveauVise = $enfant->getNiveauActuel();

        $session = new Session($enfant, $type, $niveauVise);
        $em->persist($session);

        $resultat = $texteGenerationService->genererTexteEtQuestions($niveauVise);

        $texte = new TexteGenere($resultat['titre'], $resultat['texte'], $niveauVise);
        $session->setTexteGenere($texte);
        $em->persist($texte);

        foreach ($resultat['questions'] as $questionData) {
            $texte->addQuestion(new Question(
                TypeQuestion::from($questionData['type']),
                $questionData['enonce'],
                $questionData['reponse_attendue_ou_criteres'],
                $questionData['choix'] ?? null,
            ));
        }

        $session->setStatut(StatutSession::EN_ATTENTE_RELECTURE);
        $em->flush();

        $this->addFlash('info', 'Nouveau texte généré — relis-le avant de le proposer à l\'enfant.');

        return $this->redirectToRoute('adulte_relecture', ['id' => $session->getId()]);
    }

    #[Route('/adulte/relecture/{id}', name: 'adulte_relecture')]
    public function relecture(int $id, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($id);

        if (null === $session || null === $session->getTexteGenere()) {
            throw $this->createNotFoundException('Aucun texte à relire pour cette session.');
        }

        return $this->render('adulte/relecture.html.twig', [
            'session' => $session,
            'texte' => $session->getTexteGenere(),
        ]);
    }

    #[Route('/adulte/relecture/{id}/valider', name: 'adulte_relecture_valider', methods: ['POST'])]
    public function valider(int $id, SessionRepository $sessionRepository, EntityManagerInterface $em): Response
    {
        $session = $sessionRepository->find($id);
        $texte = $session?->getTexteGenere();

        if (null === $texte) {
            throw $this->createNotFoundException();
        }

        // TODO (jour 5-6) : remplacer 'adulte' par l'identifiant réel une fois l'entité User pleinement câblée à la session.
        $texte->valider('adulte');
        $session->setStatut(StatutSession::VALIDEE);
        $em->flush();

        $this->addFlash('success', 'Texte validé — la session est maintenant disponible pour l\'enfant.');

        return $this->redirectToRoute('adulte_suivi');
    }

    #[Route('/adulte/relecture/{id}/regenerer', name: 'adulte_relecture_regenerer', methods: ['POST'])]
    public function regenerer(
        int $id,
        SessionRepository $sessionRepository,
        TexteGenerationService $texteGenerationService,
        EntityManagerInterface $em,
    ): Response {
        $session = $sessionRepository->find($id);
        $texte = $session?->getTexteGenere();

        if (null === $session || null === $texte) {
            throw $this->createNotFoundException();
        }

        $resultat = $texteGenerationService->genererTexteEtQuestions($session->getNiveauVise());
        $texte->setContenu($resultat['titre'], $resultat['texte']);
        // TODO (jour 5-6) : remplacer les Question existantes par $resultat['questions'].

        $em->flush();

        $this->addFlash('info', 'Nouveau texte généré — à relire à nouveau avant validation.');

        return $this->redirectToRoute('adulte_relecture', ['id' => $id]);
    }
}
