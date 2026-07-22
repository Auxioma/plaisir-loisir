/*
 * Comportements du parcours « Activités ».
 *
 * 1. Compte à rebours des offres exclusives (spec §2.5) :
 *    chaque badge porte data-countdown="<secondes restantes>" ; on
 *    affiche « 02j : 14h : 32 min » et on décompte chaque minute.
 *    À zéro → état « Offre expirée ».
 */

function formatCountdown(totalSeconds) {
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(days)}j : ${pad(hours)}h : ${pad(minutes)} min`;
}

function initOfferCountdowns(root = document) {
    root.querySelectorAll('[data-countdown]').forEach((el) => {
        let remaining = parseInt(el.dataset.countdown, 10);
        if (Number.isNaN(remaining)) return;

        const valueEl = el.querySelector('.offer-card__timer-value') ?? el;
        const render = () => {
            if (remaining <= 0) {
                el.classList.add('is-expired');
                el.textContent = 'Offre expirée';
                clearInterval(timer);
                return;
            }
            valueEl.textContent = formatCountdown(remaining);
        };

        render();
        const timer = setInterval(() => { remaining -= 60; render(); }, 60_000);
    });
}

/*
 * 2. Panneau de réservation (spec §F) : steppers voyageurs (adulte ≥ 1,
 *    enfant ≥ 0) et recalcul du total = prix × nombre de voyageurs.
 */
function initBookingPanel(root = document) {
    const panel = root.querySelector('[data-booking]');
    if (!panel) return;

    const price = parseInt(panel.dataset.price, 10) || 0;
    const totalEl = panel.querySelector('[data-booking-total]');
    const steppers = [...panel.querySelectorAll('[data-stepper]')];

    const refresh = () => {
        const travellers = steppers.reduce((sum, s) => sum + parseInt(s.dataset.count, 10), 0);
        if (totalEl) totalEl.textContent = `${price * travellers}€`;
        steppers.forEach((s) => {
            const min = parseInt(s.dataset.min, 10);
            const count = parseInt(s.dataset.count, 10);
            s.querySelector('[data-step="-1"]').disabled = count <= min;
            s.querySelector('[data-stepper-value]').textContent = count;
        });
    };

    steppers.forEach((s) => {
        s.querySelectorAll('[data-step]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const min = parseInt(s.dataset.min, 10);
                const next = parseInt(s.dataset.count, 10) + parseInt(btn.dataset.step, 10);
                s.dataset.count = Math.max(min, next);
                refresh();
            });
        });
    });
    refresh();
}

/*
 * 3. Sélecteur d'étoiles du formulaire « Ajouter un avis ».
 */
function initReviewFormStars(root = document) {
    const stars = [...root.querySelectorAll('.act-review-form__star')];
    stars.forEach((star) => {
        star.addEventListener('click', () => {
            const rate = parseInt(star.dataset.rate, 10);
            stars.forEach((s) => s.classList.toggle('is-on', parseInt(s.dataset.rate, 10) <= rate));
        });
    });
}

/*
 * 4. Modale « Tous les avis » (écran G) :
 *    - ouverture (Voir plus d'avis) / fermeture (✕, overlay, Échap) ;
 *    - blocage du scroll de fond (body.has-modal) ;
 *    - en-tête contextualisé au défilement (G.2) ;
 *    - dropdown « Trier par » (G.3) avec tri effectif de la liste.
 */
function initReviewsModal(root = document) {
    const modal = root.querySelector('#reviews-modal');
    if (!modal) return;

    const body = modal.querySelector('[data-modal-body]');
    const intro = modal.querySelector('[data-modal-intro]');
    const context = modal.querySelector('[data-modal-context]');

    const open = () => { modal.hidden = false; document.body.classList.add('has-modal'); };
    const close = () => { modal.hidden = true; document.body.classList.remove('has-modal'); };

    root.querySelectorAll('[data-modal-open]').forEach((btn) => btn.addEventListener('click', open));
    modal.querySelectorAll('[data-modal-close]').forEach((el) => el.addEventListener('click', close));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) close(); });

    // Deep-link : /activites/…#tous-les-avis ouvre directement la modale.
    if (window.location.hash === '#tous-les-avis') open();

    // G.2 : au-delà d'un léger défilement, l'en-tête affiche le titre de
    // l'activité à la place de la note globale.
    body?.addEventListener('scroll', () => {
        const scrolled = body.scrollTop > 40;
        intro.hidden = scrolled;
        context.hidden = !scrolled;
    });

    // G.3 : dropdown de tri.
    const toggle = modal.querySelector('[data-sort-toggle]');
    const menu = modal.querySelector('[data-sort-menu]');
    const label = modal.querySelector('[data-sort-label]');
    if (!toggle || !menu) return;

    const closeMenu = () => { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.hidden = !menu.hidden;
        toggle.setAttribute('aria-expanded', String(!menu.hidden));
    });
    document.addEventListener('click', (e) => { if (!menu.hidden && !e.target.closest('.reviews-sort')) closeMenu(); });

    const sorters = [
        (a, b) => a.dataset.index - b.dataset.index,          // Les plus récents (ordre d'origine)
        (a, b) => b.dataset.index - a.dataset.index,          // Les plus anciens
        (a, b) => b.dataset.stars - a.dataset.stars,          // Les mieux notés
        (a, b) => a.dataset.stars - b.dataset.stars,          // Les moins notés
    ];
    menu.querySelectorAll('[data-sort-option]').forEach((option) => {
        option.addEventListener('click', () => {
            menu.querySelectorAll('[data-sort-option]').forEach((o) => {
                o.classList.toggle('is-selected', o === option);
                o.setAttribute('aria-selected', String(o === option));
            });
            label.textContent = option.textContent.replace('✓', '').trim();
            [...body.querySelectorAll('[data-review]')]
                .sort(sorters[parseInt(option.dataset.sortOption, 10)])
                .forEach((item) => body.appendChild(item));
            closeMenu();
        });
    });
}

const start = () => {
    initOfferCountdowns();
    initBookingPanel();
    initReviewFormStars();
    initReviewsModal();
};

// Turbo recharge le <body> sans recharger la page : on ré-initialise à
// chaque rendu (turbo:load couvre aussi le premier chargement).
document.addEventListener('turbo:load', start);
if (window.Turbo === undefined) {
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', start)
        : start();
}
