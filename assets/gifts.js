/*
 * « Offrir un bon cadeau » (04/10) : total en direct, champs selon le mode
 * d'envoi, compteur du message et contrôle des champs obligatoires avant
 * l'envoi (le serveur revalide tout : GiftCardService::validate()).
 */
function initGiftForm() {
    const form = document.querySelector('[data-gc-form]');
    if (!form || form.dataset.gcReady) return;
    form.dataset.gcReady = '1';

    const unit = parseFloat(form.dataset.unit || '0');
    const total = form.querySelector('[data-gc-total]');
    const people = form.querySelector('[data-gc-people]');
    const peopleOut = form.querySelector('[data-gc-people-out]');
    const amount = form.querySelector('[data-gc-amount]');
    const euro = (v) => `${v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`;

    const refresh = () => {
        if (people) {
            if (peopleOut) peopleOut.textContent = people.value;
            if (total) total.textContent = euro(unit * Number(people.value));
        } else if (amount && total) {
            total.textContent = euro(Math.max(0, Math.floor(Number(amount.value) || 0)));
        }
    };
    people?.addEventListener('change', refresh);
    amount?.addEventListener('input', refresh);
    form.querySelectorAll('[data-gc-preset]').forEach((btn) => btn.addEventListener('click', () => {
        if (!amount) return;
        amount.value = btn.dataset.gcPreset;
        refresh();
    }));

    // Champs propres au mode d'envoi : visibles et obligatoires seulement s'il est choisi.
    const deliveries = [...form.querySelectorAll('[data-gc-delivery]')];
    const syncDelivery = () => {
        const chosen = deliveries.find((r) => r.checked)?.value;
        form.querySelectorAll('[data-gc-show]').forEach((block) => {
            const on = block.dataset.gcShow === chosen;
            block.hidden = !on;
            block.querySelectorAll('input, textarea').forEach((f) => { f.required = on; });
        });
    };
    deliveries.forEach((r) => r.addEventListener('change', syncDelivery));
    syncDelivery();

    const message = form.querySelector('[data-gc-message]');
    const count = form.querySelector('[data-gc-count]');
    message?.addEventListener('input', () => { if (count) count.textContent = String(message.value.length); });

    form.addEventListener('submit', (event) => {
        let first = null;
        form.querySelectorAll('input, select, textarea').forEach((field) => {
            if (field.closest('[hidden]')) return;
            const ok = field.checkValidity();
            field.closest('.gc-field, .gc-check')?.classList.toggle('is-invalid', !ok);
            if (!ok && !first) first = field;
        });
        if (first) {
            event.preventDefault();
            first.reportValidity();
            first.focus();
        }
    });
    refresh();
}

document.addEventListener('turbo:load', initGiftForm);
if (document.readyState !== 'loading') initGiftForm(); else document.addEventListener('DOMContentLoaded', initGiftForm);
