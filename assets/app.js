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
import 'leaflet/dist/leaflet.css';   /* vue carte du listing d'activités */
import './styles/fonts.css';           // Inter, hébergée par le projet
import './styles/design-system.css';
// Cale Bootstrap sur la grille et la charte d'Agnès (voir l'en-tête du fichier).
import './styles/bootstrap-theme.css';
import './styles/components.css';
import './styles/auth.css';
import './styles/home.css';
import './styles/activities.css';
import './styles/destinations.css';
import './styles/offers.css';
import './styles/gifts.css';
import './styles/events.css';
import './styles/account.css';
import './styles/app.css';

// Comportement des menus déroulants de l'en-tête d'accueil.
import './home_header.js';

// Comportements du parcours Activités (compte à rebours des offres…).
import './activities.js';
import './offers.js';

// Barre de recherche de l'accueil connecté (panneaux déroulants, calendrier).
import './home_search.js';

// Comportements du parcours Destinations (dropdown localisation, filtres…).
import './destinations.js';

// Comportements du parcours Bon cadeaux (montant, filtres, tunnel).
import './gifts.js';

// Wizard « Créer un événement » (compteurs, onglets, invitations).
import './events.js';

// Espace compte (bouton Annuler de la déconnexion).
import './account.js';
