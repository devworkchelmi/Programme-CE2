<?php

namespace App\Controller;

use App\Entity\Enum\Correction;
use App\Entity\Enum\StatutSession;
use App\Entity\Enum\TypeErreur;
use App\Entity\Feedback;
use App\Entity\Reponse;
use App\Repository\SessionRepository;
use App\Service\Llm\AnalyseReponseService;
use App\Service\Llm\FeedbackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écrans côté enfant (cf. Product Specification §2.1, §2.2, §2.3).
 *
 * L'enfant n'a pas de compte séparé : ces routes exigent seulement que l'adulte
 * soit connecté (cf. config/packages/security.yaml et §0 de la Product Specification).
 */
#[IsGranted('ROLE_ADULTE')]
class EnfantController extends AbstractController
{
    #[Route('/diagnostic/{id}', name: 'enfant_diagnostic')]
    public function diagnostic(int $id, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($id);

        if (null === $session || StatutSession::VALIDEE !== $session->getStatut()) {
            throw $this->createNotFoundException('Ce texte de diagnostic n\'est pas encore disponible.');
        }

        return $this->render('enfant/diagnostic.html.twig', [
            'session' => $session,
            'texte' => $session->getTexteGenere(),
        ]);
    }

    #[Route('/exercice/{id}', name: 'enfant_exercice')]
    public function exercice(int $id, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($id);

        if (null === $session || StatutSession::VALIDEE !== $session->getStatut()) {
            // Règle non négociable (cf. Product Specification §2.4) : jamais de texte non relu proposé à l'enfant.
            throw $this->createNotFoundException('Cette session n\'est pas encore disponible — le texte doit d\'abord être validé par un adulte.');
        }

        return $this->render('enfant/exercice.html.twig', [
            'session' => $session,
            'texte' => $session->getTexteGenere(),
        ]);
    }

    #[Route('/exercice/{id}/repondre', name: 'enfant_exercice_repondre', methods: ['POST'])]
    public function repondre(
        int $id,
        Request $request,
        SessionRepository $sessionRepository,
        AnalyseReponseService $analyseReponseService,
        FeedbackService $feedbackService,
        EntityManagerInterface $em,
    ): Response {
        $session = $sessionRepository->find($id);
        $texte = $session?->getTexteGenere();

        if (null === $session || null === $texte) {
            throw $this->createNotFoundException();
        }

        // Un seul essai par question (cf. Product Specification §2.2) : chaque réponse
        // soumise déclenche directement l'Appel 2 puis, si besoin, l'Appel 3.
        foreach ($texte->getQuestions() as $question) {
            $contenu = (string) $request->request->get('reponse_'.$question->getId(), '');

            $reponse = new Reponse($contenu);
            $question->setReponse($reponse);

            $analyse = $analyseReponseService->analyser(
                $texte->getContenu(),
                $question->getEnonce(),
                $question->getReponseAttendueOuCriteres(),
                $contenu,
            );

            $typeErreur = null !== $analyse['type_erreur_propose']
                ? TypeErreur::from($analyse['type_erreur_propose'])
                : null;

            $reponse->enregistrerAnalyse(
                Correction::from($analyse['correcte']),
                $typeErreur,
                $analyse['justification_courte'],
            );

            if (Correction::OUI !== $reponse->getCorrecte()) {
                $feedback = $feedbackService->genererFeedback(
                    $session->getNiveauVise(),
                    $texte->getContenu(),
                    $question->getEnonce(),
                    $contenu,
                    $typeErreur?->value,
                );
                $reponse->setFeedback(new Feedback($feedback['texte_feedback']));
            }

            $em->persist($reponse);
        }

        $session->setStatut(StatutSession::JOUEE);
        $em->flush();

        // TODO (jour 9-10) : déclencher ici AjustementService::calculer() pour préparer
        // le niveau de la session suivante, et persister l'AjustementNiveau correspondant.

        return $this->redirectToRoute('enfant_feedback', ['id' => $id]);
    }

    #[Route('/feedback/{id}', name: 'enfant_feedback')]
    public function feedback(int $id, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($id);

        if (null === $session) {
            throw $this->createNotFoundException();
        }

        return $this->render('enfant/feedback.html.twig', [
            'session' => $session,
            'texte' => $session->getTexteGenere(),
        ]);
    }

}
