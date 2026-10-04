/*
 * Assistant « Créer un événement » (04/10) : aperçu en direct, compteurs,
 * barre de mise en forme, synchronisation du type, date (mode, toute la
 * journée, pas d'heure de fin), lieu (Base Adresse Nationale + carte
 * Leaflet), images (aperçu, glisser-déposer), invitations (recherche de
 * membres), publication programmée, contrôle des champs requis avant envoi.
 * La validation qui fait foi reste côté serveur (EventDraftService).
 */

const qs = (root, s) => root.querySelector(s);
const qsa = (root, s) => [...root.querySelectorAll(s)];

function initPreview(form) {
    const set = (key, value) => qsa(document, `[data-ew-preview="${key}"]`).forEach((el) => { el.textContent = value; });
    qsa(form, '[data-ew-bind]').forEach((input) => {
        input.addEventListener('input', () => {
            const key = input.dataset.ewBind;
            const value = input.value.trim();
            if (key === 'date' && value) {
                const [y, m, d] = value.split('-');
                set('date', `${d}/${m}/${y}`);
            } else if (key === 'start_time' || key === 'end_time') {
                const start = qs(form, '[data-ew-bind="start_time"]')?.value || '';
                const end = qs(form, '[data-ew-bind="end_time"]')?.value || '';
                set('time', start ? (end ? `${start} - ${end}` : start) : 'Heure');
            } else {
                set(key, value || (key === 'title' ? 'Titre de votre événement' : ''));
            }
        });
    });
}

function initCounters(form) {
    qsa(form, '[data-ew-count]').forEach((field) => {
        const out = field.parentElement.querySelector('.ew-count b');
        const update = () => { if (out) out.textContent = String(field.value.length); };
        field.addEventListener('input', update);
        update();
    });
}

function initEditors(form) {
    qsa(form, '[data-ew-editor]').forEach((editor) => {
        const area = qs(editor, 'textarea');
        const replace = (before, inner, after, select = true) => {
            const { selectionStart: s, selectionEnd: e, value } = area;
            const text = value.slice(s, e) || inner;
            area.setRangeText(before + text + after, s, e, select ? 'select' : 'end');
            area.focus();
            area.dispatchEvent(new Event('input'));
        };
        qsa(editor, '[data-ew-wrap]').forEach((b) => b.addEventListener('click', () => replace(b.dataset.ewWrap, 'texte', b.dataset.ewWrap)));
        qsa(editor, '[data-ew-line]').forEach((b) => b.addEventListener('click', () => {
            const { selectionStart: s, value } = area;
            const lineStart = value.lastIndexOf('\n', s - 1) + 1;
            area.setRangeText(b.dataset.ewLine, lineStart, lineStart, 'end');
            area.focus();
            area.dispatchEvent(new Event('input'));
        }));
        qs(editor, '[data-ew-link]')?.addEventListener('click', () => {
            const url = window.prompt('Adresse du lien (https://…)', 'https://');
            if (url && /^https?:\/\//.test(url)) replace('[', 'texte du lien', `](${url})`);
        });
        qs(editor, '[data-ew-emoji]')?.addEventListener('click', (ev) => replace('', ev.currentTarget.dataset.ewEmoji, '', false));
    });
}

// Étape 1 : la liste « Type » et les cartes « Type d'événement » restent alignées.
function initTypeSync(form) {
    const select = qs(form, '[data-ew-sync="type"]');
    const cards = qsa(form, '[data-ew-sync-target="type"]');
    if (!select) return;
    cards.forEach((card) => card.addEventListener('change', () => { select.value = card.value; }));
    select.addEventListener('change', () => cards.forEach((c) => { c.checked = c.value === select.value; }));
}

function initDates(form) {
    const modes = qsa(form, 'input[name="date_mode"]');
    const recurrence = qs(form, '[data-ew-show="recurrent"]');
    const allDay = qs(form, 'input[name="all_day"]');
    const noEnd = qs(form, '[data-ew-noend]');
    const endTime = qs(form, '#ew-end-time');
    const startDate = qs(form, '#ew-start-date');
    const endDate = qs(form, '#ew-end-date');
    if (!modes.length) return;

    const apply = () => {
        const mode = modes.find((m) => m.checked)?.value;
        if (recurrence) recurrence.hidden = mode !== 'recurrent';
        const optional = qs(form, '[data-ew-optional]');
        if (optional) optional.hidden = mode === 'periode';
        endDate.required = mode === 'periode';
        const fullDay = allDay?.checked;
        qsa(form, '[data-ew-hours]').forEach((el) => { el.hidden = !!fullDay; });
        qs(form, '#ew-start-time').required = !fullDay;
        if (endTime) {
            endTime.disabled = !!noEnd?.checked;
            endTime.required = !fullDay && !noEnd?.checked;
        }
    };
    [...modes, allDay, noEnd].forEach((el) => el?.addEventListener('change', apply));
    startDate?.addEventListener('change', () => { if (endDate) endDate.min = startDate.value; });
    apply();
}

async function initLocation(form) {
    const types = qsa(form, 'input[name="location_type"]');
    if (!types.length) return;
    const address = qs(form, '[data-ew-address]');
    const list = qs(form, '[data-ew-suggest]');
    const mapEl = qs(form, '[data-ew-map]');
    const place = qs(form, '[data-ew-place]');
    const gmaps = qs(form, '[data-ew-gmaps]');
    const fields = { city: qs(form, '[data-ew-city]'), postcode: qs(form, '[data-ew-postcode]'), lat: qs(form, '[data-ew-lat]'), lng: qs(form, '[data-ew-lng]') };

    const applyType = () => {
        const type = types.find((t) => t.checked)?.value;
        qsa(form, '[data-ew-loc]').forEach((el) => { el.hidden = !el.dataset.ewLoc.split(' ').includes(type); });
        if (address) address.required = type === 'precise' || type === 'approx';
        const online = qs(form, '#ew-online');
        if (online) online.required = type === 'online';
        const label = qs(form, '[data-ew-loclabel]');
        if (label) label.textContent = type === 'approx' ? 'Zone ou quartier' : 'Lieu exact';
        if (map) setTimeout(() => map.invalidateSize(), 60);
    };

    let map = null;
    let marker = null;
    let L = null;
    if (mapEl) {
        try {
            const mod = await import('leaflet');
            L = mod.default ?? mod;
            const lat = parseFloat(mapEl.dataset.lat);
            const lng = parseFloat(mapEl.dataset.lng);
            const has = !Number.isNaN(lat) && !Number.isNaN(lng);
            map = L.map(mapEl, { scrollWheelZoom: false }).setView(has ? [lat, lng] : [46.6, 2.4], has ? 15 : 5);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(map);
            if (has) marker = L.marker([lat, lng]).addTo(map);
        } catch (e) { mapEl.hidden = true; }
    }

    const pick = (f) => {
        const [lng, lat] = f.geometry.coordinates;
        address.value = f.properties.label;
        fields.city.value = f.properties.city || '';
        fields.postcode.value = f.properties.postcode || '';
        fields.lat.value = lat.toFixed(7);
        fields.lng.value = lng.toFixed(7);
        list.hidden = true;
        if (place) {
            place.hidden = false;
            qs(place, '[data-ew-place-name]').textContent = f.properties.name || f.properties.label;
            qs(place, '[data-ew-place-city]').textContent = `${f.properties.postcode || ''} ${f.properties.city || ''}`;
        }
        if (gmaps) { gmaps.hidden = false; gmaps.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(f.properties.label)}`; }
        if (map) {
            map.setView([lat, lng], 15);
            if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng]).addTo(map);
        }
        address.dispatchEvent(new Event('input'));
    };

    let timer = null;
    address?.addEventListener('input', (ev) => {
        if (!ev.isTrusted) return;
        fields.lat.value = ''; fields.lng.value = '';
        clearTimeout(timer);
        const q = address.value.trim();
        if (q.length < 3) { list.hidden = true; return; }
        timer = setTimeout(async () => {
            try {
                const res = await fetch(`https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(q)}&limit=6`);
                const data = await res.json();
                list.innerHTML = '';
                data.features.forEach((f) => {
                    const li = document.createElement('li');
                    li.innerHTML = `${f.properties.name}<small>${f.properties.postcode || ''} ${f.properties.city || ''}</small>`;
                    li.addEventListener('mousedown', (e) => { e.preventDefault(); pick(f); });
                    list.appendChild(li);
                });
                list.hidden = data.features.length === 0;
            } catch (e) { list.hidden = true; }
        }, 250);
    });
    address?.addEventListener('blur', () => setTimeout(() => { list.hidden = true; }, 150));
    qs(form, '[data-ew-place-clear]')?.addEventListener('click', () => {
        address.value = ''; Object.values(fields).forEach((f) => { f.value = ''; });
        place.hidden = true;
        if (marker) { marker.remove(); marker = null; }
        address.focus();
    });

    types.forEach((t) => t.addEventListener('change', applyType));
    applyType();
}

function initImages(form) {
    const cover = qs(form, '[data-ew-cover]');
    const drop = qs(form, '[data-ew-drop]');
    if (cover && drop) {
        const show = (file) => {
            if (!file) return;
            const url = URL.createObjectURL(file);
            const img = qs(drop, '[data-ew-drop-img]');
            img.src = url; img.hidden = false;
            drop.classList.add('has-image');
            qsa(document, '[data-ew-preview="cover"]').forEach((p) => { p.src = url; });
        };
        cover.addEventListener('change', () => show(cover.files[0]));
        ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
        drop.addEventListener('drop', (e) => {
            e.preventDefault();
            if (e.dataTransfer.files.length) { cover.files = e.dataTransfer.files; show(cover.files[0]); }
        });
    }
    qsa(form, '[data-ew-gallery-slot] input').forEach((input) => input.addEventListener('change', () => {
        const slot = input.parentElement;
        qs(slot, 'img')?.remove();
        if (input.files[0]) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(input.files[0]);
            slot.prepend(img);
        }
    }));
}

function initInvites(form) {
    const search = qs(form, '[data-ew-people-search]');
    if (!search) return;
    const results = qs(form, '[data-ew-people-results]');
    const invited = qs(form, '[data-ew-invited]');
    const empty = qs(form, '[data-ew-invited-empty]');
    const count = qs(form, '[data-ew-invited-count]');
    const hint = qs(form, '[data-ew-people-hint]');
    const esc = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const initials = (name) => name.split(' ').map((p) => p[0] || '').join('').slice(0, 2).toUpperCase();
    const refresh = () => {
        const n = invited.children.length;
        count.textContent = String(n);
        empty.hidden = n > 0;
    };
    const isInvited = (id) => !!invited.querySelector(`li[data-id="${id}"]`);
    const add = (p) => {
        if (isInvited(p.id)) return;
        const li = document.createElement('li');
        li.dataset.id = p.id;
        li.innerHTML = `<span class="ew-avatar">${esc(initials(p.name))}</span><strong>${esc(p.name)}</strong><input type="hidden" name="invites[]" value="${esc(p.id)}"><button type="button" class="ew-link" data-ew-uninvite>Retirer</button>`;
        invited.appendChild(li);
        refresh();
    };
    invited.addEventListener('click', (e) => {
        if (e.target.closest('[data-ew-uninvite]')) { e.target.closest('li').remove(); refresh(); }
    });

    let timer = null;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        const q = search.value.trim();
        if (q.length < 2) { results.innerHTML = ''; hint.textContent = 'Tapez au moins 2 lettres pour rechercher un membre.'; return; }
        timer = setTimeout(async () => {
            const res = await fetch(`${search.dataset.url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
            const people = res.ok ? await res.json() : [];
            results.innerHTML = '';
            hint.textContent = people.length ? `${people.length} membre(s) trouvé(s)` : 'Aucun membre trouvé : invitez par e-mail ci-dessous.';
            people.forEach((p) => {
                const li = document.createElement('li');
                li.innerHTML = `${p.avatar ? `<img class="ew-avatar" src="/${esc(p.avatar)}" alt="">` : `<span class="ew-avatar">${esc(initials(p.name))}</span>`}<strong>${esc(p.name)}</strong><button type="button" class="ew-btn ew-btn--outline">${isInvited(p.id) ? 'Invité' : 'Inviter'}</button>`;
                const btn = li.querySelector('button');
                btn.disabled = isInvited(p.id);
                btn.addEventListener('click', () => { add(p); btn.textContent = 'Invité'; btn.disabled = true; });
                results.appendChild(li);
            });
        }, 250);
    });

    qsa(form, '[data-ew-tab]').forEach((tab) => tab.addEventListener('click', () => {
        qsa(form, '[data-ew-tab]').forEach((t) => { t.classList.toggle('is-active', t === tab); t.setAttribute('aria-selected', String(t === tab)); });
        qsa(form, '[data-ew-panel]').forEach((p) => { p.hidden = p.dataset.ewPanel !== tab.dataset.ewTab; });
    }));
    refresh();
}

function initPublish(form) {
    const modes = qsa(form, 'input[name="publish_mode"]');
    const schedule = qs(form, '[data-ew-schedule]');
    if (!modes.length || !schedule) return;
    const apply = () => {
        const scheduled = modes.find((m) => m.checked)?.value === 'scheduled';
        schedule.hidden = !scheduled;
        qs(schedule, 'input[type=date]').required = scheduled;
    };
    modes.forEach((m) => m.addEventListener('change', apply));
    apply();
}

// Contrôle navigateur des champs requis, seulement pour « Suivant / Publier ».
function initSubmit(form) {
    form.addEventListener('submit', (event) => {
        const action = event.submitter?.value;
        if (action !== 'next') return;
        if (!form.checkValidity()) {
            event.preventDefault();
            const invalid = form.querySelector(':invalid');
            invalid?.closest('.ew-field, .ew-choices, .ew-cats, .ew-visibility, .ew-publish')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            form.reportValidity();
        }
    });
}

const start = () => {
    const form = document.querySelector('[data-ew-form]');
    if (!form || form.dataset.ewReady) return;
    form.dataset.ewReady = '1';
    initPreview(form);
    initCounters(form);
    initEditors(form);
    initTypeSync(form);
    initDates(form);
    initLocation(form);
    initImages(form);
    initInvites(form);
    initPublish(form);
    initSubmit(form);
};

document.addEventListener('turbo:load', start);
if (document.readyState !== 'loading') start(); else document.addEventListener('DOMContentLoaded', start);

/*
 * Page « Événements » (maquette evenements.jpeg, 04/10) : carte Leaflet des
 * événements publics, marqueurs cliquables vers la fiche.
 */
async function initEventsMap() {
    const el = document.querySelector('[data-evl-map]');
    if (!el || el.dataset.ready) return;
    el.dataset.ready = '1';
    const markers = JSON.parse(el.dataset.markers || '[]');
    const mod = await import('leaflet');
    const L = mod.default ?? mod;
    const map = L.map(el, { scrollWheelZoom: false }).setView([46.6, 2.4], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 18 }).addTo(map);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Regroupe les événements d'une même position (même ville).
    const groups = new Map();
    markers.forEach((m) => {
        const key = `${m.lat.toFixed(3)},${m.lng.toFixed(3)}`;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(m);
    });
    const layers = [];
    groups.forEach((items) => {
        const icon = L.divIcon({ className: '', html: `<span class="evl-marker">${items.length}</span>`, iconSize: [30, 30], iconAnchor: [15, 15] });
        const marker = L.marker([items[0].lat, items[0].lng], { icon }).addTo(map);
        marker.bindPopup(items.map((m) => `<a href="${esc(m.url)}"><strong>${esc(m.title)}</strong></a><br><small>${esc(m.date)} · ${esc(m.where)}</small>`).join('<hr>'));
        layers.push(marker);
    });
    const fit = () => { if (layers.length) map.fitBounds(L.featureGroup(layers).getBounds().pad(0.3)); };
    fit();
    document.querySelector('[data-evl-map-fit]')?.addEventListener('click', (e) => { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); fit(); });
}
document.addEventListener('turbo:load', initEventsMap);
if (document.readyState !== 'loading') initEventsMap(); else document.addEventListener('DOMContentLoaded', initEventsMap);

// Liste des participants d'un événement : recherche et tri côté navigateur.
function initParticipants() {
    const list = document.querySelector('[data-evn-plist]');
    if (!list || list.dataset.ready) return;
    list.dataset.ready = '1';
    const rows = [...list.querySelectorAll('.evn-prow')];
    document.querySelector('[data-evn-filter]')?.addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        rows.forEach((r) => { r.hidden = q !== '' && !r.dataset.name.includes(q); });
    });
    document.querySelector('[data-evn-sort]')?.addEventListener('change', (e) => {
        rows.sort((a, b) => (e.target.value === 'name' ? a.dataset.name.localeCompare(b.dataset.name) : a.dataset.order - b.dataset.order)).forEach((r) => list.appendChild(r));
    });
}
document.addEventListener('turbo:load', initParticipants);
if (document.readyState !== 'loading') initParticipants(); else document.addEventListener('DOMContentLoaded', initParticipants);
