/*
 * Google Analytics 4 (12/10) — chargé seulement si un identifiant est
 * configuré ET si le visiteur a accepté la mesure d'audience (bandeau
 * cookies). Configuration posée par templates/_partials/_analytics.html.twig.
 *
 * Turbo ne recharge pas la page : la page vue est envoyée à chaque
 * turbo:load. Les pages déclarent leurs événements e-commerce dans le HTML :
 *   <span hidden data-ga-event="purchase" data-ga-params='{…}' data-ga-once="booking-…"></span>
 * data-ga-once empêche qu'un achat soit compté deux fois (rechargement de la
 * page de confirmation, retour arrière).
 */
let loaded = false;

const cfg = () => window.tmAnalytics;
const send = (...args) => { if (typeof window.gtag === 'function') window.gtag(...args); };

function load() {
    const c = cfg();
    if (loaded || !c?.id) return;
    loaded = true;
    const s = document.createElement('script');
    s.async = true;
    s.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(c.id)}`;
    document.head.appendChild(s);
    send('js', new Date());
    send('config', c.id, { send_page_view: false });
}

function pageView() {
    send('event', 'page_view', {
        page_location: window.location.href,
        page_title: document.title,
        language: document.documentElement.lang || 'fr',
    });
}

function flushEvents(root = document) {
    root.querySelectorAll('[data-ga-event]').forEach((el) => {
        if (el.dataset.gaDone) return;
        el.dataset.gaDone = '1';
        const once = el.dataset.gaOnce;
        if (once) {
            try {
                if (localStorage.getItem(`ga:${once}`)) return;
                localStorage.setItem(`ga:${once}`, '1');
            } catch (e) { /* stockage indisponible : on envoie quand même */ }
        }
        let params = {};
        try { params = JSON.parse(el.dataset.gaParams || '{}'); } catch (e) { params = {}; }
        send('event', el.dataset.gaEvent, params);
    });
}

function run() {
    const c = cfg();
    if (!c?.id || !c.allowed) return;
    load();
    pageView();
    flushEvents();
}

// Accord donné à l'instant sur le bandeau (assets/cookie-consent.js).
document.addEventListener('tm:consent', (e) => {
    const c = cfg();
    if (!c) return;
    c.allowed = (e.detail?.accepted || []).includes('statistics');
    run();
});

document.addEventListener('turbo:load', run);
if (window.Turbo === undefined) {
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', run) : run();
}

/** Événement ponctuel déclenché par un script de page (ex. clic « Réserver »). */
export function track(name, params = {}) {
    if (cfg()?.allowed) send('event', name, params);
}
