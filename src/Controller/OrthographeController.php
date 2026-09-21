<?php

namespace App\Controller;

use App\Entity\AjustementNiveauOrthographe;
use App\Entity\Enfant;
use App\Entity\Enum\TypeExerciceOrthographe;
use App\Entity\ExerciceOrthographe;
use App\Repository\EnfantRepository;
use App\Repository\ExerciceOrthographeRepository;
use App\Service\AjustementService;
use App\Service\Orthographe\BanqueExercicesService;
use App\Service\Orthographe\CorrectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Partie « Orthographe » (CE2) : mots à trouver (texte à trous), phrases à corriger, ou
 * mot à choisir parmi plusieurs propositions. Le contenu vient d'une banque de
 * règles/mots fixe (BanqueExercicesService), pas d'un appel LLM — donc, à la différence
 * de la compréhension de texte, l'enfant y accède directement, sans relecture adulte.
 * Niveau (1-6) propre à cette partie (Enfant::niveauOrthographe), ajusté avec la même
 * règle que la compréhension (AjustementService, réutilisé tel quel — cf.
 * appliquerAjustement() ci-dessous).
 */
#[IsGranted('ROLE_ADULTE')]
class OrthographeController extends AbstractController
{
    #[Route('/orthographe', name: 'enfant_orthographe_accueil')]
    public function accueil(
        Request $request,
        EnfantRepository $enfantRepository,
        ExerciceOrthographeRepository $exerciceRepository,
    ): Response {
        $enfant = $this->enfantSelectionne($request, $enfantRepository);

        if (null === $enfant) {
            throw $this->createNotFoundException('Aucune fiche enfant — crée-en une avec la commande app:creer-enfant.');
        }

        return $this->render('enfant/orthographe_accueil.html.twig', [
            'enfant' => $enfant,
            'derniersExercices' => $exerciceRepository->lesPlusRecentsRepondus($enfant, 5),
        ]);
    }

    #[Route('/orthographe/demarrer', name: 'enfant_orthographe_demarrer', methods: ['POST'])]
    public function demarrer(
        Request $request,
        EnfantRepository $enfantRepository,
        BanqueExercicesService $banqueExercicesService,
        EntityManagerInterface $em,
    ): Response {
        $enfant = $this->enfantSelectionne($request, $enfantRepository);

        if (null === $enfant) {
            throw $this->createNotFoundException();
        }

        // Choix de format optionnel (cf. accueil orthographe) : sans choix explicite, un
        // format est tiré au hasard parmi les 3 (texte à trous / correction / choix de mot).
        $typeBrut = $request->request->get('type');
        $typeForce = is_string($typeBrut) && '' !== $typeBrut ? TypeExerciceOrthographe::from($typeBrut) : null;

        $genere = $banqueExercicesService->genererExercice($enfant->getNiveauOrthographe(), $typeForce);

        $exercice = new ExerciceOrthographe($enfant, $genere['type'], $genere['regle'], $enfant->getNiveauOrthographe(), $genere['contenu']);
        $em->persist($exercice);
        $em->flush();

        return $this->redirectToRoute('enfant_orthographe_jouer', ['id' => $exercice->getId()]);
    }

    #[Route('/orthographe/{id}', name: 'enfant_orthographe_jouer')]
    public function jouer(int $id, ExerciceOrthographeRepository $exerciceRepository): Response
    {
        $exercice = $exerciceRepository->find($id);

        if (null === $exercice) {
            throw $this->createNotFoundException();
        }

        if ($exercice->estRepondu()) {
            // Un seul essai (même règle générale que la compréhension) : pas de rejeu,
            // on renvoie directement vers le résultat déjà enregistré.
            return $this->redirectToRoute('enfant_orthographe_resultat', ['id' => $id]);
        }

        return $this->render('enfant/orthographe_exercice.html.twig', [
            'exercice' => $exercice,
        ]);
    }

    #[Route('/orthographe/{id}/repondre', name: 'enfant_orthographe_repondre', methods: ['POST'])]
    public function repondre(
        int $id,
        Request $request,
        ExerciceOrthographeRepository $exerciceRepository,
        CorrectionService $correctionService,
        AjustementService $ajustementService,
        EntityManagerInterface $em,
    ): Response {
        $exercice = $exerciceRepository->find($id);

        if (null === $exercice) {
            throw $this->createNotFoundException();
        }

        if ($exercice->estRepondu()) {
            return $this->redirectToRoute('enfant_orthographe_resultat', ['id' => $id]);
        }

        $reponseDonnee = [(string) $request->request->get('reponse', '')];
        $correcte = $correctionService->corriger($exercice, $reponseDonnee);
        $exercice->enregistrerReponse($reponseDonnee, $correcte);
        $em->flush();

        $this->appliquerAjustement($exercice->getEnfant(), $exerciceRepository, $ajustementService, $em);

        return $this->redirectToRoute('enfant_orthographe_resultat', ['id' => $id]);
    }

    #[Route('/orthographe/{id}/resultat', name: 'enfant_orthographe_resultat')]
    public function resultat(int $id, ExerciceOrthographeRepository $exerciceRepository): Response
    {
        $exercice = $exerciceRepository->find($id);

        if (null === $exercice || !$exercice->estRepondu()) {
            throw $this->createNotFoundException();
        }

        return $this->render('enfant/orthographe_resultat.html.twig', [
            'exercice' => $exercice,
        ]);
    }

    /**
     * Ajustement de niveau (même règle que la compréhension, cf. Product Specification
     * §5 et AjustementService::calculer() — générique, il ne dépend d'aucune notion
     * propre à Session, seule tauxReussite() en dépend et n'est pas utilisée ici).
     * Chaque exercice pèse pour 1 (réussi) ou 0 (raté) : pas de "partiel" possible,
     * contrairement aux réponses rédigées de compréhension.
     */
    private function appliquerAjustement(
        Enfant $enfant,
        ExerciceOrthographeRepository $exerciceRepository,
        AjustementService $ajustementService,
        EntityManagerInterface $em,
    ): void {
        $recents = $exerciceRepository->lesPlusRecentsRepondus($enfant, 2);
        $tauxReussiteRecents = array_map(
            static fn (ExerciceOrthographe $e) => $e->getCorrecte()?->poids() ?? 0.0,
            $recents,
        );

        $resultat = $ajustementService->calculer($enfant->getNiveauOrthographe(), $tauxReussiteRecents);

        if ($resultat->niveauApres !== $resultat->niveauAvant) {
            $em->persist(new AjustementNiveauOrthographe($enfant, $resultat->niveauAvant, $resultat->niveauApres, $resultat->regleAppliquee));
        }

        $enfant->setNiveauOrthographe($resultat->niveauApres);

        $em->flush();
    }

    /**
     * Même logique que AdulteController::enfantSelectionne() (sélection mémorisée en
     * session HTTP) — dupliquée ici pour garder ce contrôleur autonome plutôt que de
     * factoriser un service partagé pour une seule ligne d'appelant ; à revoir si une
     * 3e partie du même genre apparaît.
     */
    private function enfantSelectionne(Request $request, EnfantRepository $enfantRepository): ?Enfant
    {
        $enfants = $enfantRepository->findBy([], ['id' => 'ASC']);

        if ([] === $enfants) {
            return null;
        }

        $idMemorise = $request->getSession()->get('enfant_id_selectionne');

        foreach ($enfants as $enfant) {
            if ($enfant->getId() === $idMemorise) {
                return $enfant;
            }
        }

        return $enfants[0];
    }
}
