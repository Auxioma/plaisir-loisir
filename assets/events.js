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

/* --------------------------------------------------------------------------
 *  Flow NAVIGATION Événements (landing, listings, détail, groupes).
 * ------------------------------------------------------------------------ */

/* Bannières CTA dismissibles. */
function initEvnBanners() {
    document.querySelectorAll('[data-evn-banner]').forEach((banner) => {
        const close = banner.querySelector('[data-evn-banner-close]');
        if (close) close.addEventListener('click', () => banner.remove());
    });
}

/* Landing : dropdown mini-calendrier « Aujourd'hui ». */
function initEvnToday() {
    const toggle = document.getElementById('evn-today-toggle');
    const cal = document.getElementById('evn-today-cal');
    if (!toggle || !cal) return;

    toggle.addEventListener('click', () => {
        const open = cal.hidden;
        cal.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', (e) => {
        if (!cal.hidden && !cal.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) cal.hidden = true;
    });
}

/* Listing : sidebar de filtres (pattern du flow Offres) + chip Filtre. */
function initEvnFilters() {
    const page = document.getElementById('evn-page');
    const aside = document.getElementById('evn-filters');
    const chip = document.getElementById('evn-chip-filter');
    if (!page || !aside || !chip) return;

    const counter = document.getElementById('evn-filter-counter');
    const applied = document.getElementById('evn-chip-applied');
    const toggleBtn = document.getElementById('evn-filter-toggle');

    const setOpen = (open) => {
        aside.hidden = !open;
        page.classList.toggle('has-filters', open);
        chip.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (counter) counter.hidden = !open;
        if (applied) applied.hidden = !open;
    };
    chip.addEventListener('click', () => setOpen(aside.hidden));
    if (toggleBtn) toggleBtn.addEventListener('click', () => setOpen(aside.hidden));
    const reset = document.getElementById('evn-filters-reset');
    if (reset) reset.addEventListener('click', () => setOpen(false));
}

/* Listing : chip « De vos groupes » → état vide « Devenez un organisateur ». */
function initEvnMyGroups() {
    const chipMine = document.getElementById('evn-chip-mygroups');
    const chipAll = document.getElementById('evn-chip-all');
    const grid = document.getElementById('evn-events-grid');
    const more = document.getElementById('evn-events-more');
    const empty = document.getElementById('evn-empty-mygroups');
    if (!chipMine || !grid || !empty) return;

    const setMine = (mine) => {
        grid.hidden = mine;
        if (more) more.hidden = mine;
        empty.hidden = !mine;
        chipMine.classList.toggle('is-active', mine);
        if (chipAll) chipAll.classList.toggle('is-active', !mine);
    };
    chipMine.addEventListener('click', () => setMine(empty.hidden));
    if (chipAll) chipAll.addEventListener('click', () => setMine(false));
    const resetLink = document.getElementById('evn-reset-mygroups');
    if (resetLink) resetLink.addEventListener('click', () => setMine(false));
}

/* Détail : steppers voyageurs de la carte de réservation. */
function initEvnSteppers() {
    document.querySelectorAll('[data-evn-stepper]').forEach((stepper) => {
        const output = stepper.querySelector('output');
        const min = parseInt(stepper.dataset.min || '0', 10);
        stepper.querySelectorAll('[data-step]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const value = Math.max(min, parseInt(output.textContent, 10) + parseInt(btn.dataset.step, 10));
                output.textContent = String(value);
            });
        });
    });
}

/* Détail : modale de signalement (2 étapes). */
function initEvnReport() {
    const modal = document.getElementById('evn-report');
    const open = document.getElementById('evn-report-open');
    if (!modal || !open) return;

    const steps = modal.querySelectorAll('[data-evn-report-step]');
    const show = (n) => steps.forEach((s) => { s.hidden = s.dataset.evnReportStep !== String(n); });

    open.addEventListener('click', () => { modal.hidden = false; show(1); });
    modal.querySelectorAll('[data-evn-report-close]').forEach((b) => b.addEventListener('click', () => { modal.hidden = true; }));
    modal.querySelector('[data-evn-report-next]').addEventListener('click', () => show(2));
    modal.querySelector('[data-evn-report-back]').addEventListener('click', () => show(1));

    // Sélection unique surlignée en violet.
    modal.querySelectorAll('.evn-report-item input').forEach((input) => {
        input.addEventListener('change', () => {
            modal.querySelectorAll('.evn-report-item').forEach((item) => {
                item.classList.toggle('is-checked', item.querySelector('input').checked);
            });
        });
    });
}

/* Groupe : modale « Rejoindre ce groupe » (3 états). */
function initEvnJoin() {
    const modal = document.getElementById('evn-join');
    if (!modal) return;

    const steps = modal.querySelectorAll('[data-evn-join-step]');
    const show = (n) => steps.forEach((s) => { s.hidden = s.dataset.evnJoinStep !== String(n); });

    document.querySelectorAll('[data-evn-join-open]').forEach((b) => b.addEventListener('click', () => { modal.hidden = false; show(1); }));
    modal.querySelectorAll('[data-evn-join-close]').forEach((b) => b.addEventListener('click', () => { modal.hidden = true; }));
    const choose = modal.querySelector('[data-evn-join-choose]');
    if (choose) choose.addEventListener('click', () => show(2));
    const cancel = modal.querySelector('[data-evn-join-cancel]');
    if (cancel) cancel.addEventListener('click', () => show(1));
    const confirm = modal.querySelector('[data-evn-join-confirm]');
    if (confirm) confirm.addEventListener('click', () => show(3));
}

/* Groupe : sous-onglets Liste des événements / Calendrier. */
function initEvnSubtabs() {
    const tabs = [...document.querySelectorAll('[data-evn-subtab]')];
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

/* Groupe : tri des membres (dropdown) + filtre Administrateur. */
function initEvnMembers() {
    const sortToggle = document.getElementById('evn-sort-toggle');
    const sortMenu = document.getElementById('evn-sort-menu');
    if (sortToggle && sortMenu) {
        sortToggle.addEventListener('click', () => {
            sortMenu.hidden = !sortMenu.hidden;
            sortToggle.setAttribute('aria-expanded', sortMenu.hidden ? 'false' : 'true');
        });
        sortMenu.querySelectorAll('.dest-sort__option').forEach((opt) => {
            opt.addEventListener('click', () => {
                sortMenu.querySelectorAll('.dest-sort__option').forEach((o) => {
                    o.classList.toggle('is-checked', o === opt);
                    o.setAttribute('aria-checked', o === opt ? 'true' : 'false');
                });
                sortToggle.querySelector('strong').textContent = opt.textContent.trim();
                sortMenu.hidden = true;
            });
        });
    }

    const admin = document.getElementById('evn-admin-filter');
    const all = document.getElementById('evn-members-all');
    const filtered = document.getElementById('evn-members-admin');
    const more = document.getElementById('evn-members-more');
    if (admin && all && filtered) {
        admin.addEventListener('click', () => {
            const active = filtered.hidden;
            filtered.hidden = !active;
            all.hidden = active;
            if (more) more.hidden = active;
            admin.classList.toggle('is-active', active);
            admin.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }
}

/* Groupe : « En savoir moins / plus » de l'onglet À propos. */
function initEvnAbout() {
    const toggle = document.querySelector('[data-evn-about-toggle]');
    if (!toggle) return;
    const text = document.querySelector('.evn-about__text');
    toggle.addEventListener('click', () => {
        const collapsed = text.classList.toggle('is-collapsed');
        toggle.textContent = collapsed ? 'En savoir plus' : 'En savoir moins';
    });
}

initEvnBanners();
initEvnToday();
initEvnFilters();
initEvnMyGroups();
initEvnSteppers();
initEvnReport();
initEvnJoin();
initEvnSubtabs();
initEvnMembers();
initEvnAbout();
