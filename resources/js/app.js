import Alpine from 'alpinejs';
import initializeFlightReleaseCopyButtons from './flight-release-copy';
import parserForm from './parser-form';

window.Alpine = Alpine;

Alpine.data('parserForm', parserForm);

Alpine.start();

initializeFlightReleaseCopyButtons();
