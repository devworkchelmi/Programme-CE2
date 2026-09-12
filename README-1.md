# Carnet CE2

Scaffold du projet — voir les documents de conception dans `Brainstorming/` (ou le
projet Programme CE2) : `02-validation-recherche.md`, `03-product-specification.md`,
`04-architecture-technique.md`.

Ce scaffold couvre les jours 1-2 du plan de réalisation (entités, config, Docker,
authentification) plus une bonne partie des jours 3-10 (services LLM, logique de
diagnostic/ajustement, écrans de base). Les `TODO` laissés dans le code pointent vers
ce qu'il reste à affiner — voir en particulier `AdulteController` et `EnfantController`.

## Prérequis

- PHP 8.2+ et Composer (pour lancer les commandes en dehors de Docker si tu préfères)
- Docker Desktop
- Node.js + npm (pour les assets front — Encore/Stimulus)
- Une clé API Claude sur [console.anthropic.com](https://console.anthropic.com)

## Installation

```bash
# 1. Dépendances PHP
composer install

# 2. Variables d'environnement — NE JAMAIS mettre la vraie clé API dans .env (versionné)
cp .env .env.local
# puis éditer .env.local et renseigner : ANTHROPIC_API_KEY=sk-ant-...

# 3. Conteneurs (PHP-FPM, Nginx, MariaDB)
docker compose up -d --build

# 4. Base de données
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console make:migration      # première fois : génère la migration à partir des entités
docker compose exec php bin/console doctrine:migrations:migrate

# 5. Comptes de départ (un seul adulte, un seul enfant pour le MVP)
docker compose exec php bin/console app:creer-adulte parent@example.com
docker compose exec php bin/console app:creer-enfant "Prénom de l'enfant"

# 6. Assets front
npm install
npm run watch
```

L'app est ensuite disponible sur http://localhost:8000 (connexion avec le compte
adulte créé à l'étape 5).

## Mode démo (sans appel API, sans crédit consommé)

Pour dérouler tout le parcours — diagnostic initial, relecture, réponses de l'enfant,
feedback, ajustement de niveau — sans appeler l'API Claude (utile quand le compte n'a
plus de crédit, ou pour développer l'interface sans coût) :

```bash
# dans .env.local
APP_LLM_FAKE=1
```

puis `docker compose exec php bin/console cache:clear`.

Un bandeau « Mode démo » s'affiche alors sur toutes les pages, et les titres des textes
générés sont préfixés `[DÉMO]` — impossible de confondre avec du contenu réel.

`FakeClaudeClient` n'est pas un bouchon aléatoire : l'analyse des réponses compare
réellement ce que l'enfant écrit à la réponse attendue (recoupement de mots
significatifs). Répondre juste fait monter le niveau, répondre à côté le fait baisser —
les règles de diagnostic (§4) et d'ajustement (§5) restent donc testables pour de vrai.

Repasser en réel : `APP_LLM_FAKE=0` dans `.env.local` (ou supprimer la ligne), puis
vider le cache.

## Tests

```bash
composer install --dev
vendor/bin/phpunit
```

Les tests unitaires (`tests/Unit/`) couvrent la logique pure : `AjustementService`
(règle de niveau, §5 de la Product Specification), `DiagnosticService` (calcul du
niveau de départ, §4), et `AnonymisationService` (garde-fou avant appel LLM, §6).
Ils ne nécessitent pas de base de données.

## Ce qui reste à faire (cf. architecture technique §4, jours 3-14)

- Charte visuelle — écran de relecture en 2 colonnes (texte + questions à gauche, panneau
  calibrage/anonymisation/actions dans une colonne latérale à droite), comme dans la
  maquette Relecture (`01-parcours-produit-maquettes.html`). Les autres écarts identifiés
  avec les maquettes (palette adulte, boutons QCM, cartes de feedback, sparkline) sont
  traités — voir plus bas.

## Fait depuis le scaffold initial

- Flux de démarrage de session (`POST /adulte/session/demarrer`) : génère un texte +
  des questions via Claude et envoie en relecture avant de le proposer à l'enfant.
- Navbar dans `base.html.twig` (suivi, déconnexion, email de l'adulte connecté).
- « Se souvenir de moi » sur la connexion (`remember_me` dans security.yaml).
- `AjustementService::calculer()` branché dans `EnfantController::repondre()` pour les
  sessions standard : niveau de l'enfant et historique (`AjustementNiveau`) mis à jour
  après chaque session jouée.
- Diagnostic initial multi-textes (§2.1 et §4) : `AdulteController::demarrerSession()`
  génère un lot de 3 textes de complexité croissante (niveaux 1, 3, 5), l'enfant les
  enchaîne via `EnfantController` (barre de progression, reprise au texte suivant déjà
  validé), et `DiagnosticService::calculerNiveauDepart()` fixe `Enfant.niveauActuel` +
  `diagnosticTermine` une fois tout le lot joué. Nouvelle colonne `Enfant.diagnostic_termine`
  (migration `Version20260908131936`).
- Confirmation du type d'erreur côté suivi adulte (§2.5) : section dédiée sur
  `adulte/suivi.html.twig`, route `POST /adulte/reponse/{id}/confirmer-erreur`.
- Timeout nginx relevé à 180s (`docker/nginx/default.conf`) : le lot de diagnostic
  enchaîne plusieurs appels Claude séquentiels, au-delà du défaut de 60s dans le pire cas.
- Mode démo (`APP_LLM_FAKE`) : `ClaudeClientInterface` + `FakeClaudeClient` +
  `ClaudeClientFactory`, pour tout tester sans appel API (voir section dédiée plus haut).
- `ClaudeClient` remonte désormais le message d'erreur exact de l'API Anthropic au lieu
  du seul code HTTP (le schéma JSON n'accepte notamment ni `minItems` ni `maxItems`).
- Vérification orthographique des réponses rédigées, greffée sur l'Appel 2 (aucun appel
  LLM supplémentaire) : stockée dans `Reponse.correctionsOrthographe`, affichée à
  l'enfant (2 mots maximum, après le feedback) et en intégralité dans le suivi adulte.
  L'orthographe ne pèse jamais sur l'évaluation de la compréhension ni sur le taux de
  réussite. Migration `Version20260909070351`.
- Régénération d'un texte en relecture (`AdulteController::regenerer`) : remplace aussi
  les questions liées, plus seulement le contenu du texte (`TexteGenere::remplacerQuestions()`,
  orphanRemoval supprime les anciennes questions en base).
- Sélection réelle de l'enfant : sélecteur sur `adulte/suivi.html.twig` (affiché seulement
  s'il y a plusieurs enfants), mémorisée en session HTTP (`AdulteController::enfantSelectionne()`,
  route `POST /adulte/enfant/selectionner`). Aucune nouvelle colonne — pas de migration.
- Mode démo : deux variantes de texte par niveau dans `FakeClaudeClient` (au lieu d'une
  seule) — sans ça, « Régénérer » en mode démo renvoyait systématiquement le même texte
  pour un même niveau visé, ce qui pouvait donner l'impression que le bouton ne
  fonctionnait pas.
- Édition inline d'un texte généré côté relecture (§2.4 — actions Valider / Modifier /
  Régénérer) : bouton « Modifier » sur `adulte/relecture.html.twig` (`?edition=1`, pas
  d'écran séparé), formulaire pré-rempli (titre, texte, énoncé/choix/critères de chaque
  question), route `POST /adulte/relecture/{id}/modifier`. Utilise
  `TexteGenere::marquerModifie()` (déjà présent, jamais câblé) — passe le texte en
  statutRelecture=`modifie`, mais il faut toujours cliquer sur « Valider » pour le rendre
  disponible à l'enfant (la modification seule ne valide pas).
- Charte graphique — mini-graphique de tendance sur le suivi adulte (`AdulteController::pointsSparkline()`
  / `libelleTendance()`, sparkline SVG dans `adulte/suivi.html.twig`) : la donnée `tendance`
  était déjà calculée côté contrôleur mais jamais affichée. Comparaison moyenne première
  moitié / seconde moitié des sessions récentes pour le libellé (« En progression » / « En
  baisse » / « Stable »), affiché seulement à partir de 2 sessions jouées.
- Charte graphique — palette bleue côté adulte : `body.theme-adulte` dans `base.html.twig`
  (activée automatiquement sur toutes les routes `adulte_*`) applique le fond bleu-gris
  des maquettes Suivi/Relecture au lieu du fond chaud partagé avec les écrans enfant.
- Charte graphique — boutons QCM stylisés (`enfant/_questions.html.twig`) : cercle
  personnalisé qui se remplit, bordure et fond accentués quand une option est sélectionnée
  (CSS pur, `:has()` + sélecteurs de fratrie — pas de JS supplémentaire), au lieu du radio
  natif du navigateur.
- Charte graphique — cartes de feedback (`enfant/feedback.html.twig`) : ajout d'un badge
  rond avec icône (coche verte / point orange) à côté de « C'est ça ! » / « Presque ! »,
  comme dans la maquette Feedback. Les couleurs vert/orange existaient déjà.
- Charte graphique — détails écrans enfant : icône (étoile) au-dessus du titre sur l'écran
  de diagnostic initial, sous-titre chaleureux sur l'écran de feedback (« Voici ce que le
  correcteur a noté pour toi »), comme dans les maquettes Diagnostic/Feedback.
- Charte graphique — écran d'exercice (`enfant/exercice.html.twig`) : icône (livre ouvert)
  et sous-titre chaleureux au-dessus du texte, pastille « Niveau X » avec icône étoile
  (remplace le simple libellé en majuscules), et grand guillemet décoratif en filigrane
  dans le coin de la carte de texte. Reprend les mêmes codes visuels que les écrans
  Diagnostic/Feedback pour que l'écran le plus utilisé au quotidien soit aussi agréable
  à l'œil qu'eux — aucun changement fonctionnel, uniquement décoratif (CSS + SVG inline,
  pas de JS).
