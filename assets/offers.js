/*
 * Flow « Offres du moments » — interactions de l'écran listing/filtres :
 *  - sidebar de filtres (chip « Filtre » ou ancre #filtres), rail gauche ;
 *  - bascule des chips (Toutes ⇄ Gastronomies actives) selon l'état ;
 *  - slider double du budget (bleu) synchronisé aux champs texte ;
 *  - inscription newsletter (validation e-mail côté front).
 * Le compte à rebours des cartes est déjà géré par activities.js
 * ([data-countdown] est global).
 */

function initOfferFilters() {
    const page = document.getElementById('of-page');
    const filters = document.getElementById('of-filters');
    const chip = document.getElementById('of-chip-filter');
    if (!page || !filters || !chip) {
        return;
    }

    const chipDefault = chip.querySelector('.of-chip-default');
    const chipOpen = chip.querySelector('.of-chip-open');
    const chipToutes = page.querySelector('[data-of-chip="toutes"]');
    const chipGastro = page.querySelector('[data-of-chip="gastro"]');

    const setOpen = (open) => {
        filters.hidden = !open;
        page.classList.toggle('has-filters', open);
        chip.setAttribute('aria-expanded', open ? 'true' : 'false');
        chip.classList.toggle('is-open', open);
        if (chipDefault && chipOpen) {
            chipDefault.hidden = open;
            chipOpen.hidden = !open;
        }
        // État ouvert de la maquette : « Gastronomies » devient la chip active.
        if (chipToutes && chipGastro) {
            chipGastro.hidden = !open;
            chipGastro.classList.toggle('is-active', open);
            chipToutes.classList.toggle('is-active', !open);
        }
    };

    chip.addEventListener('click', () => setOpen(filters.hidden));

    const reset = document.getElementById('of-filters-reset');
    if (reset) {
        reset.addEventListener('click', () => {
            filters.querySelectorAll('input[type="checkbox"]').forEach((c) => { c.checked = false; });
        });
    }

    // --- Slider double du budget (même mécanique que le flow Activités). ---
    const rMin = filters.querySelector('[data-budget-min]');
    const rMax = filters.querySelector('[data-budget-max]');
    const iMin = filters.querySelector('[data-budget-input-min]');
    const iMax = filters.querySelector('[data-budget-input-max]');
    const bar = filters.querySelector('[data-budget-range]');
    if (rMin && rMax && bar) {
        const MAX = Number(rMax.max);
        const sync = () => {
            let lo = Math.min(Number(rMin.value), Number(rMax.value));
            let hi = Math.max(Number(rMin.value), Number(rMax.value));
            bar.style.left = `${(lo / MAX) * 100}%`;
            bar.style.right = `${100 - (hi / MAX) * 100}%`;
            if (iMin) { iMin.value = `${lo}€`; }
            if (iMax) { iMax.value = hi >= MAX ? `${MAX}+€` : `${hi}€`; }
        };
        rMin.addEventListener('input', sync);
        rMax.addEventListener('input', sync);
        sync();
    }

    // Lien profond : /offres/toutes#filtres = écran 3 de la maquette.
    if ((document.offersInitialHash || window.location.hash) === '#filtres') {
        setOpen(true);
    }
}

function initNewsletter() {
    document.querySelectorAll('[data-newsletter]').forEach((form) => {
        const msg = form.querySelector('[data-newsletter-msg]');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const email = (form.querySelector('input[name="email"]')?.value || '').trim();
            const valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
            if (!msg) {
                return;
            }
            msg.hidden = false;
            msg.classList.toggle('is-error', !valid);
            msg.textContent = valid
                ? 'Merci, vous êtes inscrit !'
                : 'Adresse e-mail invalide';
        });
    });
}

function start() {
    document.offersInitialHash = document.offersInitialHash || window.location.hash;
    initOfferFilters();
    initNewsletter();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
