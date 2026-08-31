/*
 * Cœur « Ajouter aux favoris » des cartes.
 *
 * Le bouton existait déjà dans la maquette mais ne faisait rien : 180 boutons
 * inertes sur l'ensemble du site. On lui donne un comportement SANS toucher au
 * balisage visible — seuls des attributs `data-` sont ajoutés, invisibles à
 * l'écran.
 *
 * Un seul écouteur posé sur le document plutôt qu'un par bouton : les grilles
 * en comptent jusqu'à seize par page, et certaines sections sont réécrites par
 * d'autres scripts. La délégation survit à ces remplacements.
 *
 * CAS PARTICULIER DE LA PAGE « MES FAVORIS » (ajouté le 31/08)
 * Partout ailleurs, retirer un favori ne fait que vider le cœur : la carte a
 * toujours sa place dans une grille de catalogue. Sur /compte/favoris, non :
 * cette page ne montre QUE des favoris, et laisser la carte y figurerait une
 * liste qui se contredit elle-même. La grille y porte donc `data-favorite-list`
 * et la carte disparaît.
 *
 * Elle disparaît AVEC UN RECOURS. Un clic de travers sur un cœur ne doit pas
 * effacer un favori sans retour possible, et la page ne propose rien d'autre
 * pour le retrouver : un bandeau « Annuler » remet la carte et le favori.
 */

const SELECTEUR = '[data-favorite-slug]';

/** Empêche deux envois simultanés sur le même bouton (double clic). */
const enCours = new WeakSet();

async function basculer(bouton) {
    if (enCours.has(bouton)) {
        return;
    }
    enCours.add(bouton);

    const corps = new URLSearchParams({
        type: bouton.dataset.favoriteType || 'activite',
        slug: bouton.dataset.favoriteSlug,
        _token: bouton.dataset.favoriteToken || '',
    });

    try {
        const reponse = await fetch(bouton.dataset.favoriteUrl, {
            method: 'POST',
            body: corps,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            // Sans cela le cookie de session n'est pas transmis et le serveur
            // nous croit déconnecté.
            credentials: 'same-origin',
        });

        const donnees = await reponse.json().catch(() => ({}));

        if (reponse.status === 401) {
            // Non connecté : on emmène la personne se connecter et on lui
            // rend sa page ensuite.
            const retour = encodeURIComponent(window.location.pathname + window.location.search);
            window.location.href = `${donnees.connexion || '/login'}?retour=${retour}`;
            return;
        }

        if (!reponse.ok) {
            bouton.setAttribute('title', donnees.message || 'Action impossible pour le moment.');
            return;
        }

        appliquerEtat(bouton, donnees.favori === true);
        repercuterDansLaListe(bouton, donnees.favori === true);
    } catch (erreur) {
        // Réseau coupé : on ne change pas l'affichage, pour ne pas faire croire
        // que le favori est enregistré alors qu'il ne l'est pas.
        bouton.setAttribute('title', 'Connexion indisponible, réessayez.');
    } finally {
        enCours.delete(bouton);
    }
}

function appliquerEtat(bouton, actif) {
    bouton.classList.toggle('is-active', actif);
    bouton.setAttribute('aria-pressed', actif ? 'true' : 'false');
    bouton.setAttribute(
        'aria-label',
        actif ? bouton.dataset.favoriteLabelOn : bouton.dataset.favoriteLabelOff,
    );
}

/**
 * Sur la page des favoris seulement : fait disparaître ou réapparaître la carte.
 */
function repercuterDansLaListe(bouton, actif) {
    const liste = bouton.closest('[data-favorite-list]');
    const carte = bouton.closest('.pl-card');

    if (!liste || !carte) {
        return;
    }

    // `hidden` plutôt qu'un retrait du DOM : la carte doit pouvoir revenir
    // telle quelle, à sa place, si la personne annule.
    carte.hidden = !actif;

    if (actif) {
        retirerLeBandeau(liste);
        return;
    }

    if (aucuneCarteVisible(liste)) {
        // La grille est vide : on recharge, ce qui affiche l'état vide dessiné
        // dans la maquette. Le reconstruire ici en dupliquerait le balisage,
        // et les deux versions finiraient par diverger.
        window.location.reload();
        return;
    }

    afficherLeBandeau(liste, bouton);
}

function aucuneCarteVisible(liste) {
    return [...liste.querySelectorAll('.pl-card')].every((carte) => carte.hidden);
}

function retirerLeBandeau(liste) {
    liste.parentElement?.querySelector('[data-favorite-undo-bar]')?.remove();
}

function afficherLeBandeau(liste, bouton) {
    retirerLeBandeau(liste);

    const bandeau = document.createElement('div');
    bandeau.className = 'acc-fav-undo';
    bandeau.setAttribute('data-favorite-undo-bar', '');
    // « polite » et non « assertive » : l'information est utile, elle n'a pas
    // à couper la lecture en cours d'un lecteur d'écran.
    bandeau.setAttribute('role', 'status');

    const texte = document.createElement('span');
    texte.textContent = liste.dataset.favoriteRemoved || '';

    const annuler = document.createElement('button');
    annuler.type = 'button';
    annuler.className = 'acc-fav-undo__btn';
    annuler.textContent = liste.dataset.favoriteUndo || 'Annuler';
    annuler.addEventListener('click', () => {
        // On rejoue la bascule : le serveur remet le favori, et
        // `repercuterDansLaListe` fait revenir la carte.
        basculer(bouton);
    });

    bandeau.append(texte, annuler);
    liste.parentElement?.insertBefore(bandeau, liste);
}

document.addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest(SELECTEUR);

    if (!bouton) {
        return;
    }

    // Les cartes sont des liens : sans cela, cliquer sur le cœur ouvrirait la
    // fiche au lieu d'enregistrer le favori.
    evenement.preventDefault();
    evenement.stopPropagation();

    basculer(bouton);
});
