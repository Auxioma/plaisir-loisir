/*
 * Comportements du parcours « Bon cadeaux » (8 écrans).
 *
 * 1. Landing : sélecteur de montant du hero (pills exclusives + montant
 *    personnalisé au pas de 10€).
 * 2. Listing : ouverture/fermeture de la sidebar de filtres (écran 4),
 *    même mécanique que le flow Destinations — bascule des grilles
 *    8 ateliers ↔ 12 activités filtrées, chips de villes, ancre #filtres.
 * 3. Tunnel « Offrir un cadeau » : validation des champs obligatoires
 *    (au blur et à la soumission), compteur 0/150 du message, et
 *    synchronisation du récapitulatif (accordéons « + / × »).
 * 4. Paiement : sélection exclusive (comportement radio, visuel checkbox).
 */

/* 1. Sélecteur de montant du hero. */
function initGiftAmounts() {
    const pills = [...document.querySelectorAll('[data-gift-amount]')];
    if (!pills.length) return;

    pills.forEach((pill) => pill.addEventListener('click', () => {
        pills.forEach((p) => p.classList.toggle('is-active', p === pill));
    }));

    const output = document.querySelector('[data-gift-custom]');
    document.querySelectorAll('[data-gift-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const current = parseInt(output.textContent, 10) || 0;
            const next = Math.max(10, current + parseInt(btn.dataset.giftStep, 10));
            output.textContent = `${next}€`;
            pills.forEach((p) => p.classList.remove('is-active'));
        });
    });
}

/* 2. Sidebar de filtres du listing (écran 4). */
function initGiftFilters() {
    const page = document.getElementById('gift-page');
    const sidebar = document.getElementById('gift-filters');
    if (!page || !sidebar) return;

    const chipFilter = page.querySelector('#gift-chip-filter');
    const back = chipFilter.querySelector('.gift-chip-filter__back');
    const counter = page.querySelector('#gift-filter-counter');
    const cityChips = [...page.querySelectorAll('[data-gift-city]')];
    const gridDefault = page.querySelector('#gift-grid-default');
    const gridFiltered = page.querySelector('#gift-grid-filtered');
    const topcats = page.querySelector('#gift-topcats');
    const selection = page.querySelector('#gift-selection');

    const setHash = (h) => history.replaceState(null, '', h ? `#${h}` : window.location.pathname + window.location.search);

    const open = () => {
        page.classList.add('has-filters');
        sidebar.hidden = false;
        gridDefault.hidden = true;
        topcats.hidden = true;
        gridFiltered.hidden = false;
        selection.hidden = false;
        chipFilter.classList.add('is-open');
        chipFilter.setAttribute('aria-expanded', 'true');
        back.hidden = false;
        counter.hidden = false;
        cityChips[0]?.classList.add('is-active');
        setHash('filtres');
    };
    const close = () => {
        page.classList.remove('has-filters');
        sidebar.hidden = true;
        gridDefault.hidden = false;
        topcats.hidden = false;
        gridFiltered.hidden = true;
        selection.hidden = true;
        chipFilter.classList.remove('is-open');
        chipFilter.setAttribute('aria-expanded', 'false');
        back.hidden = true;
        counter.hidden = true;
        cityChips[0]?.classList.remove('is-active');
        setHash('');
    };

    chipFilter.addEventListener('click', () => (sidebar.hidden ? open() : close()));
    page.querySelector('#gift-filter-toggle')?.addEventListener('click', () => (sidebar.hidden ? open() : close()));
    if (window.location.hash === '#filtres') open();
}

/* 3. Tunnel : validation + compteur + synchronisation du récapitulatif. */
function initGiftTunnel() {
    const form = document.getElementById('gift-form');
    if (!form) return;

    const requiredFields = [...form.querySelectorAll('[data-gift-required]')];

    const validate = (input) => {
        const field = input.closest('.gift-field');
        if (!field) return input.type !== 'checkbox' || input.checked;
        const empty = !input.value.trim();
        field.classList.toggle('has-error', empty);
        const message = field.querySelector('.gift-field__error');
        if (message) message.hidden = !empty;
        return !empty;
    };

    requiredFields.forEach((input) => {
        input.addEventListener('blur', () => validate(input));
        input.addEventListener('input', () => { if (input.value.trim()) validate(input); });
    });

    form.addEventListener('submit', (event) => {
        const allValid = requiredFields.map((input) => validate(input)).every(Boolean);
        if (!allValid) {
            event.preventDefault();
            form.querySelector('.gift-field.has-error input')?.focus();
        }
    });

    /* Compteur 0/150 du message. */
    const message = form.querySelector('#giftMessage');
    const messageCounter = document.querySelector('[data-gift-counter]');
    message?.addEventListener('input', () => { messageCounter.textContent = message.value.length; });

    /* Récapitulatif : les accordéons se remplissent et s'ouvrent dès que
       l'utilisateur saisit le formulaire (spec C3). */
    const accBuyer = document.getElementById('gift-acc-buyer');
    const accGift = document.getElementById('gift-acc-gift');
    const sums = {
        name: document.querySelector('[data-sum-name]'),
        email: document.querySelector('[data-sum-email]'),
        sender: document.querySelector('[data-sum-sender]'),
        recipient: document.querySelector('[data-sum-recipient]'),
        message: document.querySelector('[data-sum-message]'),
    };

    form.querySelectorAll('[data-sync]').forEach((input) => {
        input.addEventListener('input', () => {
            const target = sums[input.dataset.sync];
            target.textContent = input.value.trim() || ' ';
            const acc = ['name', 'email'].includes(input.dataset.sync) ? accBuyer : accGift;
            if (input.value.trim()) acc.open = true;
        });
    });
}

/* 4. Paiement : un seul mode sélectionné à la fois. */
function initGiftPayment() {
    const checks = [...document.querySelectorAll('[data-gift-pay]')];
    checks.forEach((check) => check.addEventListener('change', () => {
        if (check.checked) checks.forEach((other) => { if (other !== check) other.checked = false; });
    }));
}

initGiftAmounts();
initGiftFilters();
initGiftTunnel();
initGiftPayment();
