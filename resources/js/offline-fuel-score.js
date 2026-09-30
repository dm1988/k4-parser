import Alpine from 'alpinejs';
import { offlineFuelScoreState } from './offline-fuel-score-state';

window.offlineFuelScore = offlineFuelScoreState;
window.Alpine = Alpine;

Alpine.start();
