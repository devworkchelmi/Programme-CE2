import { startStimulusApp } from '@symfony/stimulus-bundle';

// Enregistre automatiquement les contrôleurs Stimulus définis dans assets/controllers/
// (ex. exercice_controller.js pour la sélection QCM, cf. architecture technique §2).
const app = startStimulusApp();
