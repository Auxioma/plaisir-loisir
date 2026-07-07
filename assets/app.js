import './stimulus_bootstrap.js';

/*
 * Point d'entrée JavaScript de l'application (chargé via importmap() dans base.html.twig).
 *
 * Ordre des imports volontaire :
 *  1. Bootstrap (JS + CSS) — le framework de base ;
 *  2. nos styles personnalisés, APRÈS, pour pouvoir surcharger Bootstrap.
 */
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/design-system.css';
import './styles/app.css';
