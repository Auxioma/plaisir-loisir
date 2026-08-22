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
