import { Controller } from '@hotwired/stimulus';

/*
 * Contrôleur des cases du code de vérification (écran OTP professionnel).
 *
 * Le formulaire poste `code[]`, une valeur par case ; le contrôleur PHP les
 * recolle. Ici on ne fait que rendre la saisie supportable :
 *   · une frappe fait glisser le curseur dans la case suivante ;
 *   · Retour arrière sur une case vide revient à la précédente ;
 *   · un collage du code entier se répartit sur toutes les cases — c'est le
 *     geste naturel quand on a l'e-mail sous les yeux, et sans lui il faudrait
 *     retaper huit caractères à la main.
 *
 * Rien n'est indispensable : sans JavaScript, les cases restent huit champs
 * ordinaires et le formulaire fonctionne.
 */
export default class extends Controller {
    static targets = ['cell'];

    connect() {
        this.cellTargets.forEach((cell, index) => {
            cell.addEventListener('input', () => this.avancer(index));
            cell.addEventListener('keydown', (evenement) => this.reculer(evenement, index));
            cell.addEventListener('paste', (evenement) => this.coller(evenement));
        });
    }

    avancer(index) {
        const cellule = this.cellTargets[index];

        // Une case ne contient qu'un caractère : si le navigateur en a laissé
        // passer plusieurs (saisie vocale, clavier prédictif), on garde le
        // premier et on répartit le reste.
        if (cellule.value.length > 1) {
            this.repartir(cellule.value, index);
            return;
        }

        if (cellule.value !== '' && index < this.cellTargets.length - 1) {
            this.cellTargets[index + 1].focus();
            this.cellTargets[index + 1].select();
        }
    }

    reculer(evenement, index) {
        if (evenement.key !== 'Backspace' || this.cellTargets[index].value !== '' || index === 0) {
            return;
        }

        // La case est déjà vide : le retour arrière n'aurait rien effacé. On
        // remonte d'une case et on y efface, ce que l'utilisateur voulait.
        evenement.preventDefault();
        const precedente = this.cellTargets[index - 1];
        precedente.value = '';
        precedente.focus();
    }

    coller(evenement) {
        const colle = (evenement.clipboardData || window.clipboardData)?.getData('text') ?? '';

        if (colle.trim() === '') {
            return;
        }

        evenement.preventDefault();
        this.repartir(colle, 0);
    }

    /** Répartit une chaîne sur les cases, à partir de `depart`. */
    repartir(texte, depart) {
        const caracteres = texte.replace(/\s+/g, '').split('');

        caracteres.forEach((caractere, decalage) => {
            const cellule = this.cellTargets[depart + decalage];

            if (cellule) {
                cellule.value = caractere;
            }
        });

        const derniere = Math.min(depart + caracteres.length, this.cellTargets.length - 1);
        this.cellTargets[derniere].focus();
    }
}
