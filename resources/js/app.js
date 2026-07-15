import Alpine from 'alpinejs';
import parserForm from './parser-form';

window.Alpine = Alpine;

Alpine.data('parserForm', parserForm);

Alpine.start();
