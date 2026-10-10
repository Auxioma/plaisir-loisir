/*
 * Activités gratuites entre particuliers — retours client du 07/10.
 *
 * 1. Compte à rebours en direct : tout élément [data-starts-at="<ISO 8601>"]
 *    affiche le temps restant avant le début (« 2 j • 14 h • 32 min • 05 s »).
 *    Les unités et le texte « commencée » viennent du gabarit (traduits) :
 *    data-units='{"d":"j","h":"h","m":"min","s":"s"}', data-started="…".
 *    data-precision="min" masque les secondes (cartes).
 * 2. Nombre de participants libre : un <select data-participants-free>
 *    dont l'option « + » laisse la place à un champ numérique.
 */

const pad = (n) => String(n).padStart(2, '0');

function renderCountdown(el, now) {
    const target = Date.parse(el.dataset.startsAt);
    if (Number.isNaN(target)) return;

    let rest = Math.floor((target - now) / 1000);
    const value = el.querySelector('[data-countdown-value]') ?? el;
    if (rest <= 0) {
        value.textContent = el.dataset.started ?? '';
        el.classList.add('is-started');
        return;
    }

    const units = JSON.parse(el.dataset.units ?? '{"d":"j","h":"h","m":"min","s":"s"}');
    const days = Math.floor(rest / 86400); rest -= days * 86400;
    const hours = Math.floor(rest / 3600); rest -= hours * 3600;
    const minutes = Math.floor(rest / 60);
    const seconds = rest - minutes * 60;

    const parts = [];
    if (days > 0) parts.push(`${days} ${days === 1 && units.d1 ? units.d1 : units.d}`);
    if (days > 0 || hours > 0) parts.push(`${hours} ${units.h}`);
    parts.push(`${pad(minutes)} ${units.m}`);
    if (el.dataset.precision !== 'min') parts.push(`${pad(seconds)} ${units.s}`);

    value.textContent = parts.join(' • ');
    // Moins de 24 h : mise en avant (couleur d'urgence côté CSS).
    el.classList.toggle('is-soon', days === 0);
}

let ticker = null;
function initCountdowns() {
    const tick = () => {
        const items = document.querySelectorAll('[data-starts-at]');
        if (items.length === 0) {
            clearInterval(ticker);
            ticker = null;
            return;
        }
        const now = Date.now();
        items.forEach((el) => renderCountdown(el, now));
    };
    tick();
    if (ticker === null && document.querySelector('[data-starts-at]')) {
        ticker = setInterval(tick, 1000);
    }
}

function initFreeParticipants() {
    document.querySelectorAll('select[data-participants-free]').forEach((select) => {
        if (select.dataset.ready) return;
        select.dataset.ready = '1';
        const input = select.parentElement.querySelector('input[data-participants-input]');
        if (!input) return;

        select.addEventListener('change', () => {
            if (select.value !== 'plus') return;
            // Le champ libre prend le nom du paramètre envoyé.
            input.name = select.name;
            select.removeAttribute('name');
            select.hidden = true;
            input.hidden = false;
            input.value = input.value || String(Number(select.dataset.max ?? 10) + 1);
            input.focus();
        });
    });
}

/* Copier un lien d'invitation (bouton [data-copy="<texte>"]). */
function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
        if (button.dataset.ready) return;
        button.dataset.ready = '1';
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
            } catch {
                window.prompt('', button.dataset.copy);
                return;
            }
            const label = button.querySelector('[data-copy-label]') ?? button;
            const initial = label.textContent;
            label.textContent = button.dataset.copyDone ?? initial;
            setTimeout(() => { label.textContent = initial; }, 2000);
        });
    });
}

const start = () => {
    initCountdowns();
    initFreeParticipants();
    initCopyButtons();
};

document.addEventListener('turbo:load', start);
if (window.Turbo === undefined) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}
