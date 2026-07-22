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

const start = () => initOfferCountdowns();

// Turbo recharge le <body> sans recharger la page : on ré-initialise à
// chaque rendu (turbo:load couvre aussi le premier chargement).
document.addEventListener('turbo:load', start);
if (window.Turbo === undefined) {
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', start)
        : start();
}
