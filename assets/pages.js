/*
 * Pages institutionnelles et marketing (maquettes du 04/10) :
 * contact, bons cadeaux, offres du moment, explorer.
 */

/* Formulaires : nom du fichier joint + validation native avant envoi. */
function initForms() {
    document.querySelectorAll('[data-pg-attach]').forEach((input) => {
        if (input.dataset.pgReady) return;
        input.dataset.pgReady = '1';
        const label = input.closest('label')?.querySelector('[data-pg-attach-label]');
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!label) return;
            if (!label.dataset.initial) label.dataset.initial = label.textContent;
            if (file && file.size > 5 * 1024 * 1024) {
                input.value = '';
                label.textContent = 'Fichier trop lourd (5 Mo maximum)';
                return;
            }
            label.textContent = file ? file.name : label.dataset.initial;
        });
    });

    document.querySelectorAll('[data-pg-form]').forEach((form) => {
        if (form.dataset.pgReady) return;
        form.dataset.pgReady = '1';
        form.addEventListener('submit', (event) => {
            let firstInvalid = null;
            form.querySelectorAll('input, select, textarea').forEach((field) => {
                const ok = field.checkValidity();
                field.closest('.pg-field')?.classList.toggle('is-invalid', !ok);
                if (!ok && !firstInvalid) firstInvalid = field;
            });
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.reportValidity();
                firstInvalid.focus();
            }
        });
    });
}

/* Comptes à rebours : <span data-pg-countdown="ISO"> (format JJ:HH:MM:SS ou blocs). */
function initCountdowns() {
    const nodes = [...document.querySelectorAll('[data-pg-countdown]')];
    if (!nodes.length) return;
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
        const now = Date.now();
        nodes.forEach((node) => {
            const left = Math.max(0, new Date(node.dataset.pgCountdown).getTime() - now);
            const s = Math.floor(left / 1000);
            const parts = { j: Math.floor(s / 86400), h: Math.floor((s % 86400) / 3600), m: Math.floor((s % 3600) / 60), s: s % 60 };
            const blocks = node.querySelectorAll('[data-unit]');
            if (blocks.length) {
                blocks.forEach((b) => { b.textContent = pad(parts[b.dataset.unit]); });
            } else {
                node.textContent = `${parts.j ? parts.j + 'j ' : ''}${pad(parts.h)}:${pad(parts.m)}:${pad(parts.s)}`;
            }
        });
    };
    tick();
    clearInterval(window.__pgCountdown);
    window.__pgCountdown = setInterval(tick, 1000);
}

/* Carrousels horizontaux : boutons précédent / suivant. */
function initCarousels() {
    document.querySelectorAll('[data-pg-carousel]').forEach((wrap) => {
        if (wrap.dataset.pgReady) return;
        wrap.dataset.pgReady = '1';
        const track = wrap.querySelector('[data-pg-track]');
        if (!track) return;
        wrap.querySelectorAll('[data-pg-dir]').forEach((btn) => {
            btn.addEventListener('click', () => {
                track.scrollBy({ left: Number(btn.dataset.pgDir) * track.clientWidth * 0.8, behavior: 'smooth' });
            });
        });
    });
}

/* Curseur de budget : affiche la valeur courante. */
function initRanges() {
    document.querySelectorAll('[data-pg-range]').forEach((input) => {
        const out = document.getElementById(input.dataset.pgRange);
        const sync = () => { if (out) out.textContent = `${input.value} €${input.value === input.max ? '+' : ''}`; };
        input.addEventListener('input', sync);
        sync();
    });
}

/* Carte « Autour de vous » (Leaflet, chargé à la demande). */
async function initMaps() {
    const maps = document.querySelectorAll('[data-pg-map]');
    if (!maps.length) return;
    const mod = await import('leaflet');
    const L = mod.default ?? mod;
    maps.forEach((el) => {
        if (el.dataset.pgReady) return;
        el.dataset.pgReady = '1';
        const markers = JSON.parse(el.dataset.markers || '[]').filter((m) => m.lat && m.lng);
        const map = L.map(el, { scrollWheelZoom: false }).setView([46.6, 2.4], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 18 }).addTo(map);
        const icon = L.divIcon({ className: 'pg-pin', html: '<span></span>', iconSize: [22, 22], iconAnchor: [11, 22] });
        const layer = markers.map((m) => L.marker([m.lat, m.lng], { icon }).addTo(map).bindPopup(`<a href="${m.url}"><strong>${m.title.replace(/</g, '&lt;')}</strong></a><br>${(m.where || '').replace(/</g, '&lt;')}`));
        if (layer.length) map.fitBounds(L.featureGroup(layer).getBounds().pad(0.2), { maxZoom: 11 });
        if (el.dataset.geolocate !== undefined) {
            const button = document.querySelector('[data-pg-locate]');
            if (!navigator.geolocation) { button?.remove(); return; }
            button?.addEventListener('click', (e) => {
                e.preventDefault();
                navigator.geolocation.getCurrentPosition((p) => {
                    const here = [p.coords.latitude, p.coords.longitude];
                    map.setView(here, 9);
                    L.circleMarker(here, { radius: 7, color: '#3b19e6', fillOpacity: 0.9 }).addTo(map);
                    sortNearby(here);
                }, () => { if (button) button.textContent = 'Position indisponible'; });
            });
        }
    });
}

/* Newsletter : envoi réel (POST /newsletter), réponse JSON affichée sous le champ. */
function initNewsletter() {
    document.querySelectorAll('form[data-newsletter]').forEach((form) => {
        if (form.dataset.pgReady) return;
        form.dataset.pgReady = '1';
        const msg = form.parentElement.querySelector('[data-newsletter-msg]');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const email = form.querySelector('input[type="email"]');
            if (email && !email.checkValidity()) { email.reportValidity(); return; }
            const button = form.querySelector('button');
            if (button) button.disabled = true;
            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (msg) { msg.hidden = false; msg.textContent = data.message; msg.classList.toggle('is-error', !data.ok); }
                if (data.ok) form.reset();
            } catch (e) {
                form.submit();
            } finally {
                if (button) button.disabled = false;
            }
        });
    });
}

/* « Autour de vous » : tri par distance (haversine) et affichage en km. */
function sortNearby([lat, lng]) {
    const list = document.querySelector('[data-ex2-near]');
    if (!list) return;
    const rad = (d) => (d * Math.PI) / 180;
    const km = (la, ln) => {
        const a = Math.sin(rad(la - lat) / 2) ** 2 + Math.cos(rad(lat)) * Math.cos(rad(la)) * Math.sin(rad(ln - lng) / 2) ** 2;
        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };
    const items = [...list.children].map((li) => ({ li, d: km(Number(li.dataset.lat), Number(li.dataset.lng)) }));
    items.sort((a, b) => a.d - b.d).forEach(({ li, d }, i) => {
        li.hidden = i >= 3;
        const out = li.querySelector('[data-ex2-km]');
        if (out) out.textContent = `à ${Math.round(d)} km`;
        list.appendChild(li);
    });
}

/* Explorer : un budget au maximum signifie « sans limite » (paramètre non envoyé). */
function initBudget() {
    document.querySelectorAll('[data-ex2-budget]').forEach((input) => {
        input.form?.addEventListener('submit', () => { input.disabled = input.value === input.max; });
    });
}

/*
 * Recherche de l'accueil (05/10) : le mode choisi décide de la page de
 * résultats — activités des prestataires (/activites) ou activités gratuites
 * (/activites-privees, sans le champ « participants »). « Toutes » part vers
 * /activites, qui signale aussi les activités gratuites correspondantes.
 */
function initLandingSearch() {
    const form = document.querySelector('[data-ld-search]');
    if (!form || form.dataset.pgReady) return;
    form.dataset.pgReady = '1';
    const participants = form.querySelector('[data-ld-participants]');
    const sync = () => {
        const mode = form.querySelector('input[name="type"]:checked')?.value;
        const free = mode === 'gratuites';
        form.action = free ? form.dataset.urlFree : form.dataset.urlPro;
        participants?.classList.toggle('is-disabled', free);
        const select = participants?.querySelector('select');
        if (select) select.disabled = free;
    };
    form.querySelectorAll('input[name="type"]').forEach((r) => r.addEventListener('change', sync));
    // Champs vides : pas de paramètres inutiles dans l'adresse de résultats.
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[name="lieu"], input[name="q"], input[name="date"]').forEach((i) => { if (!i.value) i.disabled = true; });
    });
    document.querySelectorAll('[data-ld-focus-search]').forEach((a) => a.addEventListener('click', (e) => {
        e.preventDefault();
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        form.querySelector('input[name="q"]')?.focus({ preventScroll: true });
    }));
    sync();
}

function start() {
    initLandingSearch();
    initBudget();
    initNewsletter();
    initForms();
    initCountdowns();
    initCarousels();
    initRanges();
    initMaps().catch(() => {});
}

document.addEventListener('turbo:load', start);
if (document.readyState !== 'loading') start();
