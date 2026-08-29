/*
 * Barre de recherche de l'accueil connecté (écrans A/B du flow Activités).
 *
 * Quatre champs cliquables ouvrent chacun leur panneau (destination,
 * activité, calendrier, participants). Un seul panneau ouvert à la fois ;
 * clic à l'extérieur ou Échap referme. Le choix fait dans un panneau met
 * à jour la valeur affichée sous le libellé du champ.
 *
 * Deep-link de dev : « #recherche-date » (ou -destination, -activite,
 * -participants) ouvre le panneau correspondant au chargement — pratique
 * pour vérifier l'écran B sans interaction.
 */

const MONTHS_FR = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
];

function initHomeSearch(root = document) {
    const search = root.querySelector('.home-search');
    if (!search || search.dataset.bound) return;
    search.dataset.bound = '1';

    const fields = [...search.querySelectorAll('[data-search-field]')];
    const panels = [...search.querySelectorAll('[data-search-panel]')];
    const fieldOf = (name) => fields.find((f) => f.dataset.searchField === name);
    const valueOf = (name) => fieldOf(name)?.querySelector('[data-field-value]');

    /* --- Ouverture / fermeture exclusive des panneaux. --- */
    const close = () => {
        panels.forEach((p) => { p.hidden = true; });
        fields.forEach((f) => f.classList.remove('is-open'));
    };
    const open = (name) => {
        const panel = panels.find((p) => p.dataset.searchPanel === name);
        if (!panel) return;
        const wasOpen = !panel.hidden;
        close();
        if (!wasOpen) {
            panel.hidden = false;
            fieldOf(name)?.classList.add('is-open');
        }
    };

    fields.forEach((f) => f.addEventListener('click', () => open(f.dataset.searchField)));
    document.addEventListener('click', (e) => {
        if (!search.contains(e.target)) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });

    /* --- Panneaux « listes » (destination, activité) : sélection simple. --- */
    panels.filter((p) => ['destination', 'activite'].includes(p.dataset.searchPanel)).forEach((panel) => {
        panel.addEventListener('click', (e) => {
            const option = e.target.closest('[data-option]');
            if (!option) return;
            panel.querySelectorAll('[data-option]').forEach((o) => o.classList.toggle('is-active', o === option));
            const value = valueOf(panel.dataset.searchPanel);
            if (value) value.textContent = option.textContent.trim();
            close();
        });
    });

    /* --- Panneau « date » : calendrier généré mois par mois. --- */
    const cal = search.querySelector('[data-calendar]');
    if (cal) {
        const title = cal.querySelector('[data-cal-title]');
        const grid = cal.querySelector('[data-cal-grid]');
        let year = parseInt(cal.dataset.year, 10);
        let month = parseInt(cal.dataset.month, 10);       // 1–12
        let selected = { y: year, m: month, d: parseInt(cal.dataset.selected, 10) || 0 };

        const render = () => {
            title.textContent = `${MONTHS_FR[month - 1]} ${year}`;
            grid.innerHTML = '';
            /* Semaine qui commence le lundi : getDay() renvoie 0 = dimanche. */
            const lead = (new Date(year, month - 1, 1).getDay() + 6) % 7;
            const days = new Date(year, month, 0).getDate();
            for (let i = 0; i < lead; i += 1) grid.appendChild(document.createElement('span'));
            for (let d = 1; d <= days; d += 1) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = d;
                if (selected.d === d && selected.m === month && selected.y === year) {
                    btn.classList.add('is-selected');
                }
                btn.addEventListener('click', () => {
                    selected = { y: year, m: month, d };
                    const value = valueOf('date');
                    if (value) value.textContent = `${d} ${MONTHS_FR[month - 1].toLowerCase()} ${year}`;
                    close();
                });
                grid.appendChild(btn);
            }
        };
        cal.querySelector('[data-cal-prev]').addEventListener('click', () => {
            month -= 1;
            if (month < 1) { month = 12; year -= 1; }
            render();
        });
        cal.querySelector('[data-cal-next]').addEventListener('click', () => {
            month += 1;
            if (month > 12) { month = 1; year += 1; }
            render();
        });
        render();
    }

    /* --- Panneau « participants » : steppers + Valider. --- */
    const travellers = panels.find((p) => p.dataset.searchPanel === 'participants');
    if (travellers) {
        const steppers = [...travellers.querySelectorAll('[data-stepper]')];
        const refresh = () => steppers.forEach((s) => {
            const min = parseInt(s.dataset.min, 10);
            const count = parseInt(s.dataset.count, 10);
            s.querySelector('[data-step="-1"]').disabled = count <= min;
            s.querySelector('[data-stepper-value]').textContent = count;
        });
        steppers.forEach((s) => s.querySelectorAll('[data-step]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = parseInt(s.dataset.count, 10) + parseInt(btn.dataset.step, 10);
                s.dataset.count = Math.max(next, parseInt(s.dataset.min, 10));
                refresh();
            });
        }));
        refresh();

        travellers.querySelector('[data-travellers-validate]')?.addEventListener('click', () => {
            const adults = parseInt(steppers[0].dataset.count, 10);
            const kids = parseInt(steppers[1]?.dataset.count ?? '0', 10);
            const parts = [`${adults} adulte${adults > 1 ? 's' : ''}`];
            if (kids > 0) parts.push(`${kids} enfant${kids > 1 ? 's' : ''}`);
            const value = valueOf('participants');
            if (value) value.textContent = parts.join(', ');
            close();
        });
    }

    /* --- Le bouton « Recherche » emporte ce qui a été choisi. ---
     *
     * Sans ceci, les quatre panneaux s'ouvraient, on y choisissait, la valeur
     * s'affichait sous le libellé… et le bouton menait au catalogue COMPLET.
     * Le choix ne quittait jamais la page d'accueil.
     *
     * Ce qui part, et ce qui ne part pas :
     *  - « destination » devient `lieu` et « activité » devient `q`. Ce sont
     *    exactement les deux paramètres que /activites filtre déjà.
     *  - la DATE et les PARTICIPANTS ne partent pas. Le catalogue ne porte ni
     *    disponibilités ni capacité : les envoyer donnerait une adresse qui
     *    promet un filtre inexistant, et des résultats identiques feraient
     *    croire à une panne. Les deux panneaux restent utilisables, leur choix
     *    s'affiche ; le jour où le filtre existera, deux lignes suffiront.
     *
     * Le lien garde son href vers le catalogue complet : sans JavaScript, le
     * bouton mène quelque part plutôt que nulle part.
     */
    const chosen = (name) => {
        const panel = panels.find((p) => p.dataset.searchPanel === name);
        return panel?.querySelector('[data-option].is-active')?.textContent.trim() ?? '';
    };

    const submit = search.querySelector('[data-search-submit]');
    if (submit) {
        submit.addEventListener('click', (e) => {
            const base = submit.getAttribute('href') || '/';
            const url = new URL(base, window.location.origin);
            const lieu = chosen('destination');
            const activite = chosen('activite');

            if (lieu) url.searchParams.set('lieu', lieu);
            if (activite) url.searchParams.set('q', activite);

            /* Aucun critère choisi : on laisse le lien faire son travail. */
            if (!lieu && !activite) return;

            e.preventDefault();
            window.location.assign(url.pathname + url.search);
        });
    }

    /* --- Deep-link « #recherche-<champ> » : panneau ouvert au chargement. --- */
    const match = window.location.hash.match(/^#recherche-(destination|activite|date|participants)$/);
    if (match) open(match[1]);
}

const start = () => initHomeSearch();
document.addEventListener('turbo:load', start);
if (document.readyState !== 'loading') start();
else document.addEventListener('DOMContentLoaded', start);
