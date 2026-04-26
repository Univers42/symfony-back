import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['date', 'guests', 'service', 'slots', 'message'];
    static values = { endpoint: String };

    connect() {
        if (this.hasDateTarget && !this.dateTarget.value) {
            this.dateTarget.value = new Date().toISOString().slice(0, 10);
        }
        this.fetch();
    }

    async fetch() {
        const date = this.dateTarget.value;
        const guests = this.guestsTarget.value || '2';
        const service = this.serviceTarget.value || '';

        if (!date) {
            return;
        }

        this.messageTarget.textContent = 'Chargement des disponibilités…';
        const url = `${this.endpointValue}?date=${encodeURIComponent(date)}&guests=${encodeURIComponent(guests)}&service=${encodeURIComponent(service)}`;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Erreur de disponibilité');
            }
            this.render(data);
        } catch (error) {
            this.slotsTarget.innerHTML = '';
            this.messageTarget.textContent = error.message;
        }
    }

    render(data) {
        this.messageTarget.textContent = data.message || `${data.slots.length} créneaux trouvés`;
        this.slotsTarget.innerHTML = data.slots.map((slot) => `
            <button type="button" class="slot ${slot.available ? 'ok' : 'ko'}" ${slot.available ? '' : 'disabled'}>
                <strong>${slot.time}</strong>
                <span>${slot.remaining}/${slot.capacity} places</span>
            </button>
        `).join('');
    }
}
