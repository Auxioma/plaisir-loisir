import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['submit', 'giftDetails', 'giftCode', 'giftMethod'];
    static values = { onlineLabel: String, onsiteLabel: String, giftLabel: String };

    connect() {
        this.reset();
    }

    update() {
        const method = this.element.querySelector('input[name="moyen"]:checked')?.value;
        const usingGift = method === 'gift';
        this.giftDetailsTarget.hidden = !usingGift;
        this.giftCodeTarget.required = usingGift;
        this.giftMethodTarget.setAttribute('aria-expanded', String(usingGift));
        this.submitTarget.textContent = method === 'onsite' ? this.onsiteLabelValue : (method === 'gift' ? this.giftLabelValue : this.onlineLabelValue);
    }

    submit(event) {
        if (this.submitting) {
            event.preventDefault();
            return;
        }
        this.submitting = true;
        this.submitTarget.disabled = true;
        this.element.setAttribute('aria-busy', 'true');
    }

    reset() {
        this.submitting = false;
        this.submitTarget.disabled = false;
        this.element.removeAttribute('aria-busy');
        this.update();
    }
}
