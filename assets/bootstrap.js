import { startStimulusApp } from '@symfony/stimulus-bridge';

// Enregistre automatiquement les contrôleurs Stimulus définis dans assets/controllers/
// (ex. exercice_controller.js pour la sélection QCM, cf. architecture technique §2).
export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.[jt]sx?$/,
));
