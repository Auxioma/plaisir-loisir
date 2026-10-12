import { Controller } from '@hotwired/stimulus';

/*
 * Contrôleur des zones de dépôt (inscription professionnelle, étape 2/2).
 *
 * Trois services, tous facultatifs :
 *   · afficher le nom du fichier choisi — sinon rien ne prouve à l'écran que
 *     le dépôt a été pris en compte ;
 *   · accepter le glisser-déposer, que la maquette annonce noir sur blanc
 *     (« ou glisser et déposer le document ») ;
 *   · ajouter une zone supplémentaire à chaque clic sur « Ajouter un autre
 *     document », en clonant le gabarit <template>.
 *
 * Sans JavaScript, les deux zones nommées restent de simples champs fichier et
 * le formulaire fonctionne ; seul le bouton d'ajout devient inopérant.
 */
export default class extends Controller {
    static targets = ['zone', 'input', 'name', 'others', 'template'];

    connect() {
        // Le contenu d'un <template> vit hors du document : Stimulus ne le
        // parcourt pas, le gabarit n'est donc ni armé ni posté tant qu'il n'a
        // pas été cloné.
        this.zoneTargets.forEach((zone) => this.armer(zone));
    }

    /** Ajoute une zone de dépôt libre. */
    add() {
        if (!this.hasTemplateTarget || !this.hasOthersTarget) {
            return;
        }

        const clone = this.templateTarget.content.firstElementChild.cloneNode(true);
        this.othersTarget.appendChild(clone);
        this.armer(clone);
        clone.querySelector('input[type="file"]')?.focus();
    }

    /** Reflète le fichier choisi dans le libellé de la zone. */
    picked(evenement) {
        const champ = evenement.target;
        const zone = champ.closest('.pa-drop');
        const fichier = champ.files && champ.files[0];

        if (!zone) {
            return;
        }

        const libelle = zone.querySelector('.pa-drop__hint');

        if (fichier && libelle) {
            libelle.textContent = raccourcir(fichier.name);
            libelle.title = fichier.name;
            zone.classList.add('is-filled');
        } else if (libelle) {
            libelle.removeAttribute('title');
            zone.classList.remove('is-filled');
        }
    }

    /*
     * Glisser-déposer. `dragover` DOIT annuler l'événement : sans cela le
     * navigateur refuse le dépôt et ouvre le fichier dans un nouvel onglet.
     */
    armer(zone) {
        const champ = zone.querySelector('input[type="file"]');

        if (!champ) {
            return;
        }

        ['dragenter', 'dragover'].forEach((nom) => {
            zone.addEventListener(nom, (evenement) => {
                evenement.preventDefault();
                zone.classList.add('is-hovered');
            });
        });

        ['dragleave', 'drop'].forEach((nom) => {
            zone.addEventListener(nom, () => zone.classList.remove('is-hovered'));
        });

        zone.addEventListener('drop', (evenement) => {
            evenement.preventDefault();

            const fichiers = evenement.dataTransfer?.files;

            if (!fichiers || fichiers.length === 0) {
                return;
            }

            // DataTransfer est le seul moyen d'affecter `files` par programme :
            // la propriété est en lecture seule sur un champ de formulaire.
            const transfert = new DataTransfer();
            transfert.items.add(fichiers[0]);
            champ.files = transfert.files;

            champ.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
}

/**
 * Nom de fichier trop long pour la case : coupé AU MILIEU pour garder le début
 * et l'extension (« attestation-urssaf-…-2026.pdf »). Le nom complet reste
 * dans l'infobulle.
 */
function raccourcir(nom, max = 34) {
    if (nom.length <= max) {
        return nom;
    }
    const point = nom.lastIndexOf('.');
    const extension = point > 0 && nom.length - point <= 6 ? nom.slice(point) : '';
    const base = nom.slice(0, nom.length - extension.length);
    const reste = max - extension.length - 1;
    const debut = Math.ceil(reste * 0.6);

    return `${base.slice(0, debut)}…${base.slice(base.length - (reste - debut))}${extension}`;
}
