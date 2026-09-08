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

- Remplacer les questions existantes lors d'une régénération de texte (`AdulteController::regenerer`).
- Charte visuelle : reprendre plus finement le canevas de design (`01-parcours-produit-maquettes.html`).
- Sélection réelle de l'enfant (actuellement le premier trouvé — cf. TODO jour 11-12
  dans `AdulteController::suivi()`).
- Édition inline d'un texte généré côté relecture (pour l'instant : valider ou régénérer
  seulement, pas de modification directe — cf. Product Specification §2.4).

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
