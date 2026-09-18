import { Controller } from '@hotwired/stimulus';

/*
 * Contrôleur « indicateur pays compact » pour les champs téléphone.
 *
 * Remplace l'icône statique des champs téléphone (auth-input__icon,
 * pf-input__icon, gift-input__icon, ou le préfixe interne de pa-input) par
 * un drapeau + indicatif cliquables, sans réagencer la maquette : le vrai
 * <select> (PhoneNumberType::$country) reste dans le DOM — accessibilité,
 * clavier, sélecteur natif mobile — mais rendu quasi invisible et superposé
 * au drapeau, qui se contente de refléter sa valeur.
 *
 * Le drapeau et l'indicatif (+33) sont calculés depuis les propres options
 * du <select> (libellés "France (+33)" générés par PhoneNumberType côté
 * PHP) : aucune liste de pays n'est dupliquée ici.
 *
 *   <span data-controller="phone-country">
 *     <span data-phone-country-target="flag"></span>
 *     <span data-phone-country-target="code"></span>
 *     <select data-phone-country-target="select" data-action="change->phone-country#sync">…</select>
 *   </span>
 */
export default class extends Controller {
    static targets = ['select', 'flag', 'code'];

    connect() {
        this.sync();
    }

    sync() {
        const option = this.selectTarget.options[this.selectTarget.selectedIndex];

        if (!option) {
            return;
        }

        this.flagTarget.textContent = this.#flagFor(this.selectTarget.value);

        const callingCode = option.textContent.match(/\(\+(\d+)\)/);
        this.codeTarget.textContent = callingCode ? `+${callingCode[1]}` : '';
    }

    /**
     * Émoji drapeau à partir d'un code région ISO 3166-1 alpha-2 (ex. "FR"),
     * par décalage vers les indicateurs régionaux Unicode — pas de table de
     * correspondance à maintenir.
     */
    #flagFor(regionCode) {
        if (!/^[A-Z]{2}$/.test(regionCode)) {
            return '🏳';
        }

        const codePoints = [...regionCode].map((letter) => 0x1f1e6 + letter.charCodeAt(0) - 65);

        return String.fromCodePoint(...codePoints);
    }
}
