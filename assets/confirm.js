/*
 * Confirmation des actions sensibles dans une vraie modale (20/10), à la
 * place de window.confirm() : voir templates/_partials/_confirm_modal.html.twig.
 *
 *  - <button data-confirm="…"> ou <a data-confirm="…"> : le clic ouvre la
 *    modale ; « Confirmer » soumet le formulaire du bouton (ou suit le lien).
 *  - <form data-confirm="…"> : la soumission ouvre la modale.
 *  - data-confirm-title / data-confirm-ok : titre et libellé du bouton.
 *
 * Écouteurs en phase de capture sur document : passent avant Turbo, un seul
 * abonnement pour toutes les pages.
 */
import { Modal } from 'bootstrap';

function ask(source, onAccept) {
    const element = document.getElementById('confirm-action');
    if (!element) {
        // Page sans la modale (ne devrait pas arriver) : repli natif.
        if (window.confirm(source.dataset.confirm)) {
            onAccept();
        }
        return;
    }

    const title = element.querySelector('[data-confirm-title]');
    title.dataset.defaultLabel ??= title.textContent;
    title.textContent = source.dataset.confirmTitle || title.dataset.defaultLabel;
    element.querySelector('[data-confirm-message]').textContent = source.dataset.confirm;

    const accept = element.querySelector('[data-confirm-accept]');
    accept.textContent = source.dataset.confirmOk || accept.dataset.defaultLabel;

    const modal = Modal.getOrCreateInstance(element);
    const onClick = () => {
        accept.removeEventListener('click', onClick);
        element.addEventListener('hidden.bs.modal', onAccept, { once: true });
        modal.hide();
    };
    // Une seule action par ouverture, même si la modale est refermée sans valider.
    accept.addEventListener('click', onClick);
    element.addEventListener('hidden.bs.modal', () => accept.removeEventListener('click', onClick), { once: true });
    modal.show();
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('button[data-confirm], a[data-confirm], input[type=submit][data-confirm]');
    if (!trigger) {
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();

    ask(trigger, () => {
        if ('A' === trigger.tagName) {
            window.location.assign(trigger.href);
        } else if (trigger.form) {
            // Soumission par ce bouton (garde son name/value) : l'événement
            // submit qui suit n'est plus intercepté ici.
            trigger.form.requestSubmit(trigger);
        }
    });
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
        return;
    }
    if ('1' === form.dataset.confirmed) {
        delete form.dataset.confirmed;
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();

    const submitter = event.submitter;
    ask(form, () => {
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
    });
}, true);
