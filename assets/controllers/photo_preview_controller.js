import { Controller } from '@hotwired/stimulus';

/*
 * Aperçu immédiat de la photo de profil choisie dans <input type="file">,
 * avant l'envoi du formulaire (account/parametres.html.twig). Remplace le
 * src de la cible "image" par une URL objet locale ; aucun appel réseau.
 */
export default class extends Controller {
    static targets = ['input', 'image'];

    preview() {
        const file = this.inputTarget.files?.[0];
        if (!file) {
            return;
        }

        if (this.currentObjectUrl) {
            URL.revokeObjectURL(this.currentObjectUrl);
        }

        this.currentObjectUrl = URL.createObjectURL(file);
        this.imageTarget.src = this.currentObjectUrl;
        this.imageTarget.hidden = false;
    }

    disconnect() {
        if (this.currentObjectUrl) {
            URL.revokeObjectURL(this.currentObjectUrl);
        }
    }
}
