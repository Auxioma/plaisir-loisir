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

/*
 * 2. Écran 5 : bascule de la sidebar de filtres. La page passe en
 *    .has-filters (layout 2 colonnes), la grille des destinations laisse
 *    place aux activités Gastronomie, et les chips changent d'état
 *    (« ‹ Filtre » violet + « Gastronomies » en violet clair).
 */
function initDestFilters(root = document) {
    const page = root.querySelector('#dest-page');
    const sidebar = root.querySelector('#dest-filters');
    if (!page || !sidebar || sidebar.dataset.bound) return;
    sidebar.dataset.bound = '1';

    const chipFilter = page.querySelector('#dest-chip-filter');
    const back = chipFilter.querySelector('.dest-chip-filter__back');
    const chipGastro = page.querySelector('#dest-chip-gastro');
    const chipToutes = page.querySelector('.act-chipbar__scroll .act-chip.is-active');
    const gridDefault = page.querySelector('#dest-grid-default');
    const gridFiltered = page.querySelector('#dest-grid-filtered');
    const appliedChip = page.querySelector('#dest-applied-gastro');
    const checks = [...sidebar.querySelectorAll('[data-filter-check]')];
    const gastroCheck = checks[5];
    const foundEl = page.querySelector('#dest-found-count');
    const counter = page.querySelector('#dest-filter-counter');

    const setHash = (h) => history.replaceState(null, '', h ? `#${h}` : window.location.pathname + window.location.search);

    const refreshCounter = () => {
        /* Compteur = groupes date + participants renseignés (spec §7.C). */
        const n = (page.querySelector('#dest-date-input').value ? 1 : 0)
            + (sidebar.dataset.travApplied ? 1 : 0);
        counter.hidden = n === 0 || sidebar.hidden;
        counter.textContent = n;
    };

    const refreshFound = () => {
        const n = checks.filter((c) => c.checked).length;
        foundEl.textContent = n <= 1 ? 126 : Math.max(12, Math.round(126 / (1 + (n - 1) * 0.5)));
    };

    const open = () => {
        page.classList.add('has-filters');
        sidebar.hidden = false;
        gridDefault.hidden = true;
        gridFiltered.hidden = false;
        chipFilter.classList.add('is-open');
        chipFilter.setAttribute('aria-expanded', 'true');
        back.hidden = false;
        chipGastro.hidden = !gastroCheck.checked;
        chipToutes?.classList.remove('is-active');
        refreshCounter();
        setHash('filtres');
    };
    const close = () => {
        page.classList.remove('has-filters');
        sidebar.hidden = true;
        gridDefault.hidden = false;
        gridFiltered.hidden = true;
        chipFilter.classList.remove('is-open');
        chipFilter.setAttribute('aria-expanded', 'false');
        back.hidden = true;
        chipGastro.hidden = true;
        chipToutes?.classList.add('is-active');
        counter.hidden = true;
        setHash('');
    };
    const toggle = () => (sidebar.hidden ? open() : close());

    chipFilter.addEventListener('click', toggle);
    page.querySelector('#dest-filter-toggle')?.addEventListener('click', toggle);

    /* Retrait du filtre appliqué : décoche « Gastronomie » et masque les chips. */
    appliedChip?.addEventListener('click', () => {
        gastroCheck.checked = false;
        appliedChip.hidden = true;
        chipGastro.hidden = true;
        refreshFound();
    });
    checks.forEach((c) => c.addEventListener('change', () => {
        if (c === gastroCheck) {
            appliedChip.hidden = !c.checked;
            chipGastro.hidden = !c.checked || sidebar.hidden;
        }
        refreshFound();
    }));

    /* Réinitialiser : retour à l'état d'ouverture de l'écran 5. */
    page.querySelector('#dest-filters-reset')?.addEventListener('click', () => {
        checks.forEach((c, i) => { c.checked = i === 5; });
        appliedChip.hidden = false;
        chipGastro.hidden = sidebar.hidden;
        page.querySelector('#dest-date-input').value = '';
        delete sidebar.dataset.travApplied;
        const min = sidebar.querySelector('[data-budget-min]');
        const max = sidebar.querySelector('[data-budget-max]');
        if (min && max) { min.value = 154; max.value = 1050; min.dispatchEvent(new Event('input')); }
        refreshFound();
        refreshCounter();
    });

    /* Double curseur du budget, synchronisé avec les deux champs. */
    const budget = sidebar.querySelector('#dest-budget');
    if (budget) {
        const min = budget.querySelector('[data-budget-min]');
        const max = budget.querySelector('[data-budget-max]');
        const range = budget.querySelector('[data-budget-range]');
        const inMin = budget.querySelector('[data-budget-input-min]');
        const inMax = budget.querySelector('[data-budget-input-max]');
        const render = () => {
            let a = parseInt(min.value, 10);
            let b = parseInt(max.value, 10);
            if (a > b) [a, b] = [b, a];
            range.style.left = `${(a / 1050) * 100}%`;
            range.style.right = `${100 - (b / 1050) * 100}%`;
            inMin.value = `${a}€`;
            inMax.value = b >= 1050 ? '1050+€' : `${b}€`;
        };
        [min, max].forEach((r) => r.addEventListener('input', render));
        [inMin, inMax].forEach((inp, i) => inp.addEventListener('change', () => {
            const v = parseInt(inp.value.replace(/\D/g, ''), 10);
            if (!Number.isNaN(v)) (i === 0 ? min : max).value = Math.min(Math.max(v, 0), 1050);
            render();
        }));
        render();
    }

    page.dataset.refreshCounter = '1';
    sidebar.refreshCounter = refreshCounter;

    /* Deep-links de dev. */
    const hash = document.destInitialHash || window.location.hash;
    if (['#filtres', '#dates', '#voyageurs'].includes(hash)) open();
    return { open };
}

/*
 * 3. Écran 6 : calendrier « Choisir vos dates » — sélection d'une plage
 *    (1er clic = début, 2e = fin, aperçu au survol), Réinitialiser /
 *    Appliquer, écrit « 16 – 17 juil. 2026 » dans le champ Date.
 */
function initRangeCalendar(root = document) {
    const pop = root.querySelector('#dest-cal');
    const input = root.querySelector('#dest-date-input');
    if (!pop || !input || pop.dataset.bound) return;
    pop.dataset.bound = '1';

    const MONTHS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    const SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    const title = pop.querySelector('[data-cal-title]');
    const grid = pop.querySelector('[data-cal-grid]');
    let year = parseInt(pop.dataset.year, 10);
    let month = parseInt(pop.dataset.month, 10);
    let start = parseInt(pop.dataset.start, 10) || null;   // jours du mois affiché (état maquette)
    let end = parseInt(pop.dataset.end, 10) || null;
    let hover = null;

    const render = () => {
        title.textContent = `${MONTHS[month - 1]} ${year}`;
        grid.innerHTML = '';
        const lead = (new Date(year, month - 1, 1).getDay() + 6) % 7;
        const days = new Date(year, month, 0).getDate();
        const prevDays = new Date(year, month - 1, 0).getDate();
        for (let i = lead; i > 0; i -= 1) {
            const b = document.createElement('button');
            b.type = 'button'; b.disabled = true; b.className = 'is-muted'; b.textContent = prevDays - i + 1;
            grid.appendChild(b);
        }
        const to = end ?? hover;
        for (let d = 1; d <= days; d += 1) {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = d;
            if (d === start) b.classList.add('is-start');
            else if (to && d === to && start && to > start) b.classList.add('is-end');
            else if (start && to && d > start && d < to) b.classList.add('is-between');
            b.addEventListener('click', () => {
                if (!start || (start && end) || d < start) { start = d; end = null; }
                else if (d === start) { end = null; }
                else { end = d; }
                hover = null;
                render();
            });
            b.addEventListener('mouseenter', () => {
                if (start && !end) { hover = d; render(); }
            });
            grid.appendChild(b);
        }
        const total = lead + days;
        for (let d = 1; d <= (7 - (total % 7)) % 7; d += 1) {
            const b = document.createElement('button');
            b.type = 'button'; b.disabled = true; b.className = 'is-muted'; b.textContent = d;
            grid.appendChild(b);
        }
    };

    const place = () => {
        const r = input.getBoundingClientRect();
        pop.style.top = `${r.bottom + 8}px`;
        pop.style.left = `${r.left}px`;
    };
    const open = () => { place(); pop.hidden = false; render(); };
    const close = () => { pop.hidden = true; };
    input.addEventListener('click', open);
    pop.querySelector('[data-cal-close]').addEventListener('click', close);
    pop.querySelector('[data-cal-reset]').addEventListener('click', () => { start = null; end = null; hover = null; render(); });
    pop.querySelector('[data-cal-apply]').addEventListener('click', () => {
        if (start) {
            input.value = end
                ? `${start} – ${end} ${SHORT[month - 1]} ${year}`
                : `${start} ${SHORT[month - 1]} ${year}`;
        }
        close();
        root.querySelector('#dest-filters')?.refreshCounter?.();
    });
    pop.querySelector('[data-cal-prev]').addEventListener('click', () => {
        month -= 1; if (month < 1) { month = 12; year -= 1; } start = null; end = null; render();
    });
    pop.querySelector('[data-cal-next]').addEventListener('click', () => {
        month += 1; if (month > 12) { month = 1; year += 1; } start = null; end = null; render();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    document.addEventListener('click', (e) => {
        if (!pop.hidden && !pop.contains(e.target) && !e.target.closest('#dest-date-input')) close();
    });

    if (document.destInitialHash === '#dates') open();
}

/*
 * 4. Écran 7.A : popover « Nombre de voyageurs » — Adultes ≥ 1,
 *    Enfants ≥ 0, total plafonné à 10, Appliquer écrit « 02 participants ».
 */
function initTravellersPopover(root = document) {
    const pop = root.querySelector('#dest-trav');
    const input = root.querySelector('#dest-trav-input');
    if (!pop || !input || pop.dataset.bound) return;
    pop.dataset.bound = '1';

    const MAXTOTAL = 10;
    const steppers = [...pop.querySelectorAll('[data-stepper]')];
    const note = pop.querySelector('[data-trav-note]');
    const total = () => steppers.reduce((s, x) => s + parseInt(x.dataset.count, 10), 0);

    const refresh = () => {
        steppers.forEach((s) => {
            const min = parseInt(s.dataset.min, 10);
            const count = parseInt(s.dataset.count, 10);
            s.querySelector('[data-step="-1"]').disabled = count <= min;
            s.querySelector('[data-step="1"]').disabled = total() >= MAXTOTAL;
            s.querySelector('[data-stepper-value]').textContent = count;
        });
        note.classList.toggle('is-danger', total() >= MAXTOTAL);
    };
    steppers.forEach((s) => s.querySelectorAll('[data-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = parseInt(s.dataset.count, 10) + parseInt(btn.dataset.step, 10);
            const bounded = Math.max(next, parseInt(s.dataset.min, 10));
            if (bounded > parseInt(s.dataset.count, 10) && total() >= MAXTOTAL) return;
            s.dataset.count = bounded;
            refresh();
        });
    }));
    refresh();

    const open = () => {
        const r = input.getBoundingClientRect();
        pop.style.top = `${r.bottom + 8}px`;
        pop.style.left = `${r.left}px`;
        pop.hidden = false;
    };
    const close = () => { pop.hidden = true; };
    input.addEventListener('click', open);
    pop.querySelector('[data-trav-close]').addEventListener('click', close);
    pop.querySelector('[data-trav-reset]').addEventListener('click', () => {
        steppers[0].dataset.count = 2;
        steppers[1].dataset.count = 0;
        refresh();
    });
    pop.querySelector('[data-trav-apply]').addEventListener('click', () => {
        const n = total();
        input.value = `${String(n).padStart(2, '0')} participant${n > 1 ? 's' : ''}`;
        const sidebar = root.querySelector('#dest-filters');
        if (sidebar) { sidebar.dataset.travApplied = '1'; sidebar.refreshCounter?.(); }
        close();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    document.addEventListener('click', (e) => {
        if (!pop.hidden && !pop.contains(e.target) && !e.target.closest('#dest-trav-input')) close();
    });

    if (document.destInitialHash === '#voyageurs') open();
}

/*
 * 5. Écran 7.B : dropdown de tri — visuel « case à cocher » mais
 *    comportement exclusif (radiogroup), met à jour le libellé.
 */
function initSortDropdown(root = document) {
    const wrap = root.querySelector('.dest-sort');
    if (!wrap || wrap.dataset.bound) return;
    wrap.dataset.bound = '1';

    const toggle = wrap.querySelector('#dest-sort-toggle');
    const menu = wrap.querySelector('#dest-sort-menu');
    const label = wrap.querySelector('#dest-sort-label');

    const close = () => { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', () => {
        menu.hidden = !menu.hidden;
        toggle.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
    });
    menu.querySelectorAll('[data-sort-option]').forEach((opt) => {
        opt.addEventListener('click', () => {
            menu.querySelectorAll('[data-sort-option]').forEach((o) => {
                o.classList.toggle('is-checked', o === opt);
                o.setAttribute('aria-checked', o === opt ? 'true' : 'false');
            });
            label.textContent = opt.textContent.trim();
            close();
        });
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) close(); });

    if (document.destInitialHash === '#tri') { menu.hidden = false; toggle.setAttribute('aria-expanded', 'true'); }
}

/*
 * 6. Écran 1 : carrousel des catégories populaires de la landing.
 */
function initDestCatsCarousel(root = document) {
    const scroll = root.querySelector('[data-dest-cats-scroll]');
    if (!scroll || scroll.dataset.bound) return;
    scroll.dataset.bound = '1';
    root.querySelector('[data-dest-cats-prev]')?.addEventListener('click', () => scroll.scrollBy({ left: -454, behavior: 'smooth' }));
    root.querySelector('[data-dest-cats-next]')?.addEventListener('click', () => scroll.scrollBy({ left: 454, behavior: 'smooth' }));
}

const start = () => {
    /* Hash d'origine, mémorisé avant que la bascule de la sidebar ne le
       réécrive (deep-links #dates / #voyageurs / #tri). */
    document.destInitialHash = window.location.hash;
    initDestCatsCarousel();
    initLocationDropdown();
    initDestFilters();
    initRangeCalendar();
    initTravellersPopover();
    initSortDropdown();
};
document.addEventListener('turbo:load', start);
if (document.readyState !== 'loading') start();
else document.addEventListener('DOMContentLoaded', start);
