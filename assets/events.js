/*
 * Comportements du wizard « Créer un événement ».
 *
 * 1. Compteurs de caractères (0/120, 0/200, 0/3000) — tout textarea
 *    portant data-ev-counter="idDuCompteur".
 * 2. Étape 7 : bascule des onglets « Inviter des personnes » /
 *    « Inviter via un lien » (aria-selected + panneaux masqués).
 * 3. Étape 7 : le bouton d'une ligne de contact suit sa case à cocher
 *    (Inviter ↔ Annuler l'invitation).
 */

function initEventCounters() {
    document.querySelectorAll('[data-ev-counter]').forEach((area) => {
        const counter = document.getElementById(area.dataset.evCounter);
        if (!counter) return;
        area.addEventListener('input', () => { counter.textContent = area.value.length; });
    });
}

function initEventTabs() {
    const tabs = [...document.querySelectorAll('.ev-tab')];
    if (!tabs.length) return;

    tabs.forEach((tab) => tab.addEventListener('click', () => {
        tabs.forEach((other) => {
            const selected = other === tab;
            other.classList.toggle('is-active', selected);
            other.setAttribute('aria-selected', selected ? 'true' : 'false');
            const panel = document.getElementById(other.getAttribute('aria-controls'));
            if (panel) panel.hidden = !selected;
        });
    }));
}

function initEventInvites() {
    document.querySelectorAll('.ev-contact').forEach((row) => {
        const check = row.querySelector('input[type="checkbox"]');
        const btn = row.querySelector('.ev-contact__btn');
        if (!check || !btn || btn.textContent.includes('lien')) return;

        const refresh = () => {
            const invited = check.checked;
            btn.textContent = invited ? "Annuler l'invitation" : 'Inviter';
            btn.classList.toggle('pl-btn', invited);
            btn.classList.toggle('pl-btn--primary', invited);
            btn.classList.toggle('ev-contact__btn--soft', !invited);
        };
        check.addEventListener('change', refresh);
        btn.addEventListener('click', () => { check.checked = !check.checked; refresh(); });
    });
}

initEventCounters();
initEventTabs();
initEventInvites();
