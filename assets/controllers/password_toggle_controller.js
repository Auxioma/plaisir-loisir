import { Controller } from '@hotwired/stimulus';

/*
 * Contrôleur « afficher / masquer le mot de passe ».
 *
 * Branché sur l'habillage d'un champ mot de passe :
 *   <div data-controller="password-toggle">
 *     <input data-password-toggle-target="input" type="password">
 *     <button data-action="password-toggle#toggle">
 *       <span data-password-toggle-target="show">…œil…</span>
 *       <span data-password-toggle-target="hide" hidden>…œil barré…</span>
 *     </button>
 *   </div>
 */
export default class extends Controller {
    static targets = ['input', 'show', 'hide'];

    toggle() {
        const isHidden = this.inputTarget.type === 'password';
        this.inputTarget.type = isHidden ? 'text' : 'password';

        // On échange les deux icônes (œil ouvert / œil barré).
        if (this.hasShowTarget && this.hasHideTarget) {
            this.showTarget.hidden = isHidden;
            this.hideTarget.hidden = !isHidden;
        }
    }
}
