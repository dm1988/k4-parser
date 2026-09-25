import Alpine from 'alpinejs';
import { waypointFuelMonitor } from './waypoint-fuel-monitor';

window.offlineFuelScore = waypointFuelMonitor;
window.Alpine = Alpine;

Alpine.start();
