/*
 * Comportements de l'espace compte (spec profil).
 *
 * Déconnexion : « Annuler » revient à la page précédente (spec), avec
 * repli sur l'accueil quand il n'y a pas d'historique.
 */

document.querySelectorAll('[data-acc-back]').forEach((btn) => {
    btn.addEventListener('click', () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '/';
        }
    });
});
