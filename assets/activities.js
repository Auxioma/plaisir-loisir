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

/*
 * 5. Listing : bascules écran C ⇄ D (sidebar de filtres, grille 3 col.)
 *    et C ⇄ E (vue carte) sans rechargement (spec §0). Le compteur du
 *    bouton « Filtre » et le nombre d'activités trouvées suivent les
 *    filtres cochés ; « Réinitialiser » remet tout à zéro.
 */
function initListingToggles(root = document) {
    const page = root.querySelector('.act-page');
    const body = root.querySelector('#act-body');
    const filters = root.querySelector('#act-filters');
    const filterBtn = root.querySelector('#act-filter-toggle');
    const mapview = root.querySelector('#act-mapview');
    const mapBtn = root.querySelector('#act-map-toggle');
    const mapClose = root.querySelector('#act-map-close');
    if (!page || !filters || !mapview) return;

    // --- Compteurs (chip « Filtre ① » + « N activités trouvées »). ---
    const foundEl = root.querySelector('#act-found-count');
    // Le chip « Sports & Aventures ✕ » et la case « Sport & Aventure »
    // reflètent le même filtre : seules les cases comptent (① au départ).
    const activeFilters = () => filters.querySelectorAll('[data-filter-check]:checked').length;
    const refreshCounters = () => {
        const n = activeFilters();
        let badge = filterBtn.querySelector('.act-chip__counter');
        if (n > 0 && !filters.hidden) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'act-chip__counter';
                filterBtn.appendChild(badge);
            }
            badge.textContent = n;
        } else {
            badge?.remove();
        }
        // Décompte factice le temps du câblage : 412 avec le filtre de la
        // maquette, puis chaque filtre supplémentaire resserre le résultat.
        if (foundEl) foundEl.textContent = n <= 1 ? 412 : Math.max(12, Math.round(412 / (1 + (n - 1) * 0.6)));
    };

    const openFilters = (on) => {
        filters.hidden = !on;
        page.classList.toggle('has-filters', on);
        refreshCounters();
    };

    filterBtn?.addEventListener('click', () => {
        if (!mapview.hidden) closeMap();          // exclusif avec la vue carte
        openFilters(filters.hidden);
    });

    filters.querySelectorAll('[data-filter-check]').forEach((cb) => cb.addEventListener('change', refreshCounters));
    filters.querySelectorAll('[data-applied-chip]').forEach((chip) =>
        chip.addEventListener('click', () => {
            chip.remove();
            const twin = filters.querySelector('[data-filter-check]');   // 1re case = même filtre
            if (twin) twin.checked = false;
            refreshCounters();
        }));

    filters.querySelectorAll('[data-dest-type]').forEach((btn) =>
        btn.addEventListener('click', () => {
            filters.querySelectorAll('[data-dest-type]').forEach((b) => b.classList.toggle('is-active', b === btn));
        }));

    root.querySelector('#act-filters-reset')?.addEventListener('click', () => {
        filters.querySelectorAll('[data-filter-check]').forEach((cb) => { cb.checked = false; });
        filters.querySelectorAll('[data-applied-chip]').forEach((chip) => chip.remove());
        filters.querySelectorAll('[data-dest-type]').forEach((b, i) => b.classList.toggle('is-active', i === 0));
        resetBudget();
        refreshCounters();
    });

    // --- Slider double du budget (min/max synchronisés aux champs). ---
    const rMin = filters.querySelector('[data-budget-min]');
    const rMax = filters.querySelector('[data-budget-max]');
    const iMin = filters.querySelector('[data-budget-input-min]');
    const iMax = filters.querySelector('[data-budget-input-max]');
    const bar = filters.querySelector('[data-budget-range]');
    const MAX = 1050;
    const renderBudget = () => {
        let lo = Math.min(+rMin.value, +rMax.value);
        let hi = Math.max(+rMin.value, +rMax.value);
        iMin.value = `${lo}€`;
        iMax.value = hi >= MAX ? `${MAX}+€` : `${hi}€`;
        bar.style.left = `${(lo / MAX) * 100}%`;
        bar.style.width = `${((hi - lo) / MAX) * 100}%`;
    };
    const resetBudget = () => { if (rMin) { rMin.value = 0; rMax.value = MAX; renderBudget(); } };
    if (rMin && rMax) {
        [rMin, rMax].forEach((r) => r.addEventListener('input', renderBudget));
        const readInput = (input, range) => {
            const v = parseInt(input.value.replace(/[^0-9]/g, ''), 10);
            if (!Number.isNaN(v)) { range.value = Math.min(MAX, Math.max(0, v)); renderBudget(); }
        };
        iMin.addEventListener('change', () => readInput(iMin, rMin));
        iMax.addEventListener('change', () => readInput(iMax, rMax));
        renderBudget();
    }

    // --- Vue carte (écran E). ---
    const openMap = () => {
        if (!filters.hidden) openFilters(false);
        body.hidden = true;
        mapview.hidden = false;
        mapBtn?.classList.add('is-active');
        initLeafletMap(mapview);
    };
    const closeMap = () => {
        mapview.hidden = true;
        body.hidden = false;
        mapBtn?.classList.remove('is-active');
    };
    mapBtn?.addEventListener('click', () => (mapview.hidden ? openMap() : closeMap()));
    mapClose?.addEventListener('click', closeMap);

    // Deep-links (spec §10 : état partageable via l'URL) :
    // /activites#filtres ouvre la sidebar, /activites#carte ouvre la carte.
    if (window.location.hash === '#filtres') openFilters(true);
    if (window.location.hash === '#carte') openMap();
    const syncHash = (hash) => history.replaceState(null, '', hash || window.location.pathname);
    filterBtn?.addEventListener('click', () => syncHash(filters.hidden ? '' : '#filtres'));
    mapBtn?.addEventListener('click', () => syncHash(mapview.hidden ? '' : '#carte'));
    mapClose?.addEventListener('click', () => syncHash(''));
}

/*
 * 6. Carte Leaflet (écran E) : marqueurs « note » blancs, clusters
 *    violets avec tooltip, popup mini-carte d'activité, synchronisation
 *    survol liste ⇄ marqueur. Chargée paresseusement au premier
 *    affichage (import dynamique : Leaflet ne pèse pas sur les autres
 *    pages).
 */
let leafletMap = null;
async function initLeafletMap(mapview) {
    const el = mapview.querySelector('#act-map');
    if (!el) return;
    // Turbo peut remplacer la page en conservant ce module : on jette la
    // carte devenue orpheline avant d'en recréer une.
    if (leafletMap && leafletMap.getContainer() !== el) { leafletMap.remove(); leafletMap = null; }
    if (leafletMap) { leafletMap.invalidateSize(); return; }

    const mod = await import('leaflet');
    const L = mod.default ?? mod;

    const markersData = JSON.parse(el.dataset.markers ?? '[]');
    const clustersData = JSON.parse(el.dataset.clusters ?? '[]');

    // Vue France entière d'emblée (tous les marqueurs sont visibles même si
    // le recadrage différé ci-dessous n'a pas encore tourné).
    leafletMap = L.map(el, { zoomControl: false, center: [46.2, 3.4], zoom: 6 });
    L.control.zoom({ position: 'bottomright' }).addTo(leafletMap);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(leafletMap);

    const cards = [...mapview.querySelectorAll('.act-card--horizontal')];
    const starSvg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="pl-icon"><path d="M12 2l2.9 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21 7 14.14l-5-4.87 7.1-1.01L12 2z"/></svg>';

    const markers = markersData.map((m, i) => {
        const icon = L.divIcon({ className: '', html: `<span class="act-marker" data-marker-index="${i}">${starSvg} ${m.rating}</span>`, iconSize: null });
        const marker = L.marker([m.lat, m.lng], { icon }).addTo(leafletMap);
        marker.bindPopup(`
            <div class="act-popup">
                <div class="act-popup__media"><img src="${m.image}" alt=""></div>
                <div class="act-popup__body">
                    <span class="act-popup__place">${m.place}</span>
                    <span class="act-popup__title">${m.title}</span>
                    <span class="act-popup__meta">${starSvg} ${m.rating} (${m.reviews} reviews) · ${m.duration}</span>
                    <span class="act-popup__foot"><span class="act-popup__from">À partir de</span><span class="act-popup__price">${m.price}€</span></span>
                </div>
            </div>`, { className: 'act-map-popup', closeButton: true, offset: [0, -8] });
        marker.on('click', () => {
            cards.forEach((c, j) => c.classList.toggle('is-hot', j === i));
            cards[i]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
        return marker;
    });

    clustersData.forEach((c) => {
        const icon = L.divIcon({ className: '', html: `<span class="act-cluster">${c.count}</span>`, iconSize: [40, 40], iconAnchor: [20, 20] });
        L.marker([c.lat, c.lng], { icon })
            .bindTooltip(`${c.count} activités disponibles`, { className: 'act-cluster-tip', direction: 'top', offset: [0, -14] })
            .addTo(leafletMap);
    });

    // Survol d'une carte de la liste → marqueur mis en évidence (et retour).
    cards.forEach((card, i) => {
        card.addEventListener('mouseenter', () => markers[i]?.getElement()?.querySelector('.act-marker')?.classList.add('is-hot'));
        card.addEventListener('mouseleave', () => markers[i]?.getElement()?.querySelector('.act-marker')?.classList.remove('is-hot'));
    });

    // Le conteneur vient d'être dévoilé : on laisse le navigateur poser sa
    // taille réelle puis on cadre précisément tous les marqueurs.
    const bounds = L.latLngBounds([...markersData, ...clustersData].map((m) => [m.lat, m.lng]));
    setTimeout(() => {
        leafletMap.invalidateSize();
        leafletMap.fitBounds(bounds, { padding: [48, 48] });
    }, 80);
}

const start = () => {
    initOfferCountdowns();
    initBookingPanel();
    initReviewFormStars();
    initReviewsModal();
    initListingToggles();
};

// Turbo recharge le <body> sans recharger la page : on ré-initialise à
// chaque rendu (turbo:load couvre aussi le premier chargement).
document.addEventListener('turbo:load', start);
if (window.Turbo === undefined) {
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', start)
        : start();
}
