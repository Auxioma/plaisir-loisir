/*
 * Espace professionnel (maquettes profil_professionnel, 02/10) :
 *  - confirmations (`data-confirm` sur un bouton de formulaire) ;
 *  - dialogues (`data-pp-open="id"` ouvre <dialog id>, `data-pp-close` ferme,
 *    `data-pp-autoopen` ouvre au chargement) ;
 *  - actions groupées de « Mes activités » (cases à cocher) ;
 *  - messagerie : défilement en bas du fil, Entrée pour envoyer ;
 *  - avis : bouton « Répondre » qui déplie le formulaire.
 * Délégation sur document : un seul abonnement, compatible Turbo.
 */

document.addEventListener('click', (event) => {
    const confirmBtn = event.target.closest('[data-confirm]');
    if (confirmBtn && !window.confirm(confirmBtn.dataset.confirm)) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
    }

    const opener = event.target.closest('[data-pp-open]');
    if (opener) {
        const dialog = document.getElementById(opener.dataset.ppOpen);
        if (dialog && typeof dialog.showModal === 'function') {
            event.preventDefault();
            opener.closest('details')?.removeAttribute('open');
            dialog.showModal();
            dialog.querySelector('input:not([type=hidden]), select, textarea')?.focus();
        }
        return;
    }

    if (event.target.closest('[data-pp-close]')) {
        event.target.closest('dialog')?.close();
        return;
    }

    const replyToggle = event.target.closest('[data-pp-toggle-reply]');
    if (replyToggle) {
        const details = replyToggle.closest('.pp-review')?.querySelector('.pp-review__form');
        if (details) {
            details.open = !details.open;
            if (details.open) details.querySelector('textarea')?.focus();
        }
    }
});

// Clic sur le fond d'un <dialog> : fermeture.
document.addEventListener('click', (event) => {
    if (event.target instanceof HTMLDialogElement && event.target.classList.contains('pp-dialog')) {
        const r = event.target.getBoundingClientRect();
        if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) {
            event.target.close();
        }
    }
});

document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-pp-bulk-item]')) return;
    const checked = document.querySelectorAll('[data-pp-bulk-item]:checked').length;
    const bar = document.querySelector('[data-pp-bulk-bar]');
    if (bar) {
        bar.hidden = checked === 0;
        bar.querySelector('[data-pp-bulk-count]').textContent = String(checked);
    }
});

// Entrée envoie, Maj+Entrée va à la ligne.
document.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey && event.target.matches('[data-pp-autosend]')) {
        event.preventDefault();
        if (event.target.value.trim() !== '') event.target.form.requestSubmit();
    }
});

const ppStart = () => {
    document.querySelectorAll('.pp-chat').forEach((chat) => { chat.scrollTop = chat.scrollHeight; });
    document.querySelectorAll('dialog[data-pp-autoopen]').forEach((d) => { if (!d.open) d.showModal(); });
    const ticket = document.getElementById('pp-ticket');
    if (window.location.hash === '#ticket' && ticket && !ticket.open) ticket.showModal();
};

document.addEventListener('turbo:load', ppStart);
if (document.readyState !== 'loading') ppStart(); else document.addEventListener('DOMContentLoaded', ppStart);
