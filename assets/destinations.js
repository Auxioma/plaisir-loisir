/*
 * Comportements du parcours « Destinations ».
 *
 * 1. Dropdown de localisation (écran 4) : le champ « lieu » de la barre
 *    de recherche ouvre un panneau « N'importe où / Autour de moi » +
 *    suggestions de villes. Deep-link de dev : « #localisation ».
 */

function initLocationDropdown(root = document) {
    const input = root.querySelector('#dest-loc-input');
    const panel = root.querySelector('#dest-locpanel');
    if (!input || !panel || panel.dataset.bound) return;
    panel.dataset.bound = '1';

    const open = () => { panel.hidden = false; input.setAttribute('aria-expanded', 'true'); };
    const close = () => { panel.hidden = true; input.setAttribute('aria-expanded', 'false'); };

    input.addEventListener('focus', open);
    input.addEventListener('click', open);
    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && e.target !== input) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
        /* Navigation clavier ↑/↓ entre les options, Enter = sélection. */
        if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && !panel.hidden) {
            e.preventDefault();
            const items = [...panel.querySelectorAll('.dest-locpanel__item')];
            const idx = items.indexOf(document.activeElement);
            const next = e.key === 'ArrowDown' ? Math.min(idx + 1, items.length - 1) : Math.max(idx - 1, 0);
            items[next]?.focus();
        }
    });

    /* « N'importe où » : efface le filtre de localisation. */
    panel.querySelector('[data-loc-anywhere]')?.addEventListener('click', () => {
        input.value = '';
        input.placeholder = "N'importe où";
        close();
    });

    /* « Autour de moi » : géolocalisation navigateur, avec état de
       chargement et message discret en cas de refus. */
    panel.querySelector('[data-loc-nearby]')?.addEventListener('click', (e) => {
        const item = e.currentTarget;
        panel.querySelector('.dest-locpanel__error')?.remove();
        if (!navigator.geolocation) return;
        item.classList.add('is-loading');
        navigator.geolocation.getCurrentPosition(
            () => {
                item.classList.remove('is-loading');
                input.value = 'Autour de moi';
                close();
            },
            () => {
                item.classList.remove('is-loading');
                const err = document.createElement('p');
                err.className = 'dest-locpanel__error';
                err.textContent = 'Active la localisation pour utiliser cette option';
                item.after(err);
            },
            { timeout: 8000 },
        );
    });

    /* Suggestions de villes : sélection simple. */
    panel.querySelectorAll('[data-loc-city]').forEach((btn) => {
        btn.addEventListener('click', () => {
            input.value = btn.textContent.trim();
            panel.querySelectorAll('[data-loc-city]').forEach((b) => b.setAttribute('aria-selected', b === btn ? 'true' : 'false'));
            close();
        });
    });

    if (window.location.hash === '#localisation') open();
}

const start = () => {
    initLocationDropdown();
};
document.addEventListener('turbo:load', start);
if (document.readyState !== 'loading') start();
else document.addEventListener('DOMContentLoaded', start);
