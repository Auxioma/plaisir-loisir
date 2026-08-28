/*
 * Suggestions pendant la frappe dans les champs de recherche.
 *
 * POURQUOI CE FICHIER
 * Les champs de recherche du site étaient muets : il fallait connaître
 * l'orthographe exacte d'une ville pour la trouver, et une faute de frappe ne
 * donnait aucun résultat sans dire pourquoi. Taper « pa » propose désormais
 * Paris. Demande du CTO du 25/08.
 *
 * COMMENT UN CHAMP S'INSCRIT
 * Un attribut suffit sur le champ : `data-suggest="lieu"` pour les villes et
 * les destinations, `data-suggest="activite"` pour les titres d'activités.
 * L'adresse interrogée est lue une fois sur <body> (elle est traduite).
 *
 * CE QUI EST VOLONTAIRE
 *  - 200 ms d'attente après la dernière touche : sans cela, « paris » lance
 *    cinq requêtes dont quatre sont déjà périmées à leur arrivée ;
 *  - la requête précédente est ANNULÉE quand une nouvelle part, sinon une
 *    réponse lente écraserait une réponse récente et la liste afficherait les
 *    propositions de « pa » alors que le champ contient « paris » ;
 *  - deux caractères minimum, côté serveur comme ici ;
 *  - le clavier fonctionne seul : flèches, Entrée, Échap. Une liste qu'on ne
 *    peut parcourir qu'à la souris exclut une partie des visiteurs, et le
 *    lecteur d'écran a besoin du rôle « listbox » pour l'annoncer.
 */

const ATTENTE_MS = 200;
const MINIMUM = 2;

function creerListe(champ) {
    const liste = document.createElement('ul');
    liste.className = 'pl-suggest';
    liste.setAttribute('role', 'listbox');
    liste.hidden = true;

    // Le champ vit souvent dans une boîte en `display: flex` dont le
    // débordement est masqué : la liste est donc posée dans le parent
    // positionné le plus proche, à défaut le champ lui-même.
    const hote = champ.closest('.pl-suggest-anchor') || champ.parentElement;
    hote.classList.add('pl-suggest-anchor');
    hote.appendChild(liste);

    return liste;
}

function initChamp(champ, adresse) {
    const type = champ.dataset.suggest;
    const liste = creerListe(champ);

    let minuteur = null;
    let requete = null;
    let items = [];
    let actif = -1;

    champ.setAttribute('role', 'combobox');
    champ.setAttribute('aria-autocomplete', 'list');
    champ.setAttribute('aria-expanded', 'false');
    champ.setAttribute('autocomplete', 'off');

    function fermer() {
        liste.hidden = true;
        liste.innerHTML = '';
        champ.setAttribute('aria-expanded', 'false');
        champ.removeAttribute('aria-activedescendant');
        items = [];
        actif = -1;
    }

    function surligner(index) {
        actif = index;
        [...liste.children].forEach((li, i) => {
            const choisi = i === index;
            li.classList.toggle('is-active', choisi);
            li.setAttribute('aria-selected', choisi ? 'true' : 'false');
            if (choisi) {
                champ.setAttribute('aria-activedescendant', li.id);
                li.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function choisir(index) {
        const item = items[index];
        if (!item) {
            return;
        }

        champ.value = item.label;
        fermer();

        // Une activité mène à sa fiche ; un lieu remplit le champ et laisse la
        // personne poursuivre sa recherche, car il n'a pas de page à lui.
        if (item.url) {
            window.location.assign(item.url);
        }
    }

    function afficher(resultats) {
        items = resultats;

        if (items.length === 0) {
            fermer();
            return;
        }

        liste.innerHTML = '';
        items.forEach((item, i) => {
            const li = document.createElement('li');
            li.id = `${champ.id || 'suggest'}-option-${i}`;
            li.className = 'pl-suggest__item';
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', 'false');
            li.textContent = item.label;
            li.addEventListener('mousedown', (e) => {
                // `mousedown` et non `click` : le champ perd le focus avant le
                // clic, ce qui fermerait la liste et annulerait le choix.
                e.preventDefault();
                choisir(i);
            });
            liste.appendChild(li);
        });

        liste.hidden = false;
        champ.setAttribute('aria-expanded', 'true');
        actif = -1;
    }

    function interroger() {
        const saisie = champ.value.trim();

        if (saisie.length < MINIMUM) {
            fermer();
            return;
        }

        if (requete) {
            requete.abort();
        }
        requete = new AbortController();

        const url = `${adresse}?q=${encodeURIComponent(saisie)}&type=${encodeURIComponent(type)}`;

        fetch(url, { signal: requete.signal, headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : { items: [] }))
            .then((data) => afficher(Array.isArray(data.items) ? data.items : []))
            .catch(() => {
                // Requête annulée ou réseau indisponible : on ne dérange pas la
                // personne avec un message, le champ reste utilisable tel quel.
            });
    }

    champ.addEventListener('input', () => {
        window.clearTimeout(minuteur);
        minuteur = window.setTimeout(interroger, ATTENTE_MS);
    });

    champ.addEventListener('keydown', (e) => {
        if (liste.hidden) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            surligner((actif + 1) % items.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            surligner((actif - 1 + items.length) % items.length);
        } else if (e.key === 'Enter' && actif >= 0) {
            // Uniquement quand une proposition est surlignée : sinon Entrée
            // doit envoyer le formulaire, comme avant.
            e.preventDefault();
            choisir(actif);
        } else if (e.key === 'Escape') {
            fermer();
        }
    });

    champ.addEventListener('blur', () => {
        // Court délai : laisse passer le `mousedown` d'un choix à la souris.
        window.setTimeout(fermer, 120);
    });
}

function start() {
    const adresse = document.body?.dataset.suggestUrl;
    if (!adresse) {
        return;
    }

    document.querySelectorAll('input[data-suggest]').forEach((champ) => initChamp(champ, adresse));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
