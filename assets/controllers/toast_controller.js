import { Controller } from '@hotwired/stimulus';
import { Toast } from 'bootstrap';

/*
 * Affiche et minute un toast Bootstrap (assets/../flash_messages.html.twig).
 *
 * Un `.toast` Bootstrap est masqué par défaut : il faut l'instancier et
 * appeler show() en JS, ce que fait connect() dès l'arrivée du toast dans
 * le DOM (rendu initial, ou re-rendu Turbo après un POST).
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 5000 } };

    connect() {
        this.toast = new Toast(this.element, { delay: this.delayValue });
        this.toast.show();
    }

    disconnect() {
        this.toast?.dispose();
    }
}
