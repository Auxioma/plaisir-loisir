/*
 * Menus déroulants de l'en-tête d'accueil (« Découvrez » et sélecteur de langue).
 *
 * On utilise des balises natives <details class="pl-dropdown"> : elles s'ouvrent
 * et se ferment déjà toutes seules au clic sur leur libellé. Ce petit script
 * ajoute juste le comportement attendu d'un menu de site :
 *   - un seul menu ouvert à la fois ;
 *   - fermeture quand on clique n'importe où ailleurs sur la page ;
 *   - fermeture avec la touche Échap.
 */
function initHomeHeaderDropdowns() {
    const dropdowns = document.querySelectorAll('details.pl-dropdown');
    if (dropdowns.length === 0) {
        return;
    }

    const closeAll = (except = null) => {
        dropdowns.forEach((d) => {
            if (d !== except) {
                d.removeAttribute('open');
            }
        });
    };

    dropdowns.forEach((d) => {
        const summary = d.querySelector('summary');
        if (summary) {
            // Au clic, `d.open` reflète encore l'état AVANT bascule : si le menu
            // va s'ouvrir, on referme les autres immédiatement (fermeture instantanée).
            summary.addEventListener('click', () => {
                if (!d.open) {
                    closeAll(d);
                }
            });
        }
        // Filet de sécurité (l'événement toggle est asynchrone).
        d.addEventListener('toggle', () => {
            if (d.open) {
                closeAll(d);
            }
        });
        // Choisir une option referme le menu.
        d.querySelectorAll('.pl-dropdown__item').forEach((item) => {
            item.addEventListener('click', () => d.removeAttribute('open'));
        });
    });

    // Clic en dehors de tout menu → on referme.
    document.addEventListener('click', (event) => {
        if (!event.target.closest('details.pl-dropdown')) {
            closeAll();
        }
    });

    // Touche Échap → on referme.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHomeHeaderDropdowns);
} else {
    initHomeHeaderDropdowns();
}
