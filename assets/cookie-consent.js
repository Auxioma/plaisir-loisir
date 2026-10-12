/*
 * Bandeau cookies (12/10) — voir templates/_partials/_cookie_banner.html.twig.
 * Enregistre le choix sans recharger la page, puis prévient analytics.js
 * (événement « tm:consent »). « Gérer mes cookies » (data-cookie-open) rouvre
 * le bandeau avec le détail des catégories.
 */
function init() {
    const banner = document.querySelector('[data-cookie-banner]');
    const form = banner?.querySelector('[data-cookie-form]');
    if (!banner || !form || form.dataset.ready) return;
    form.dataset.ready = '1';
    const prefs = form.querySelector('[data-cookie-prefs]');
    const save = form.querySelector('[data-cookie-save]');
    const customize = form.querySelector('[data-cookie-customize]');

    const openPrefs = () => { prefs.hidden = false; save.hidden = false; customize.hidden = true; };
    customize?.addEventListener('click', openPrefs);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        data.set('choice', event.submitter?.value || 'custom');
        try {
            const res = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(String(res.status));
            const json = await res.json();
            banner.hidden = true;
            window.tmConsentDone = true;
            form.querySelectorAll('input[name="categories[]"]').forEach((c) => { c.checked = (json.accepted || []).includes(c.value); });
            document.dispatchEvent(new CustomEvent('tm:consent', { detail: json }));
        } catch (e) {
            form.submit(); // repli : envoi classique
        }
    });

    document.querySelectorAll('[data-cookie-open]').forEach((link) => {
        if (link.dataset.ready) return;
        link.dataset.ready = '1';
        link.addEventListener('click', (e) => { e.preventDefault(); banner.hidden = false; openPrefs(); form.querySelector('button, input')?.focus(); });
    });

    // Page restaurée du cache de Turbo après un choix : le bandeau reste fermé.
    if (window.tmConsentDone) banner.hidden = true;
}

document.addEventListener('turbo:load', init);
if (window.Turbo === undefined) {
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
}
