import { Controller } from '@hotwired/stimulus';

/**
 * Reservation availability widget — calls the public availability probe and
 * renders 15-minute slots with remaining capacity colored by availability.
 */
export default class extends Controller {
    static targets = ['date', 'guests', 'service', 'slots', 'message'];
    static values = { endpoint: String };

    connect() {
        // Default the date to today.
        if (this.hasDateTarget && !this.dateTarget.value) {
            const t = new Date();
            this.dateTarget.value = t.toISOString().slice(0, 10);
        }
    }

    async fetch() {
        const date = this.dateTarget.value;
        const guests = this.guestsTarget.value;
        const service = this.serviceTarget.value;
        if (!date) {
            this.messageTarget.textContent = 'Sélectionnez une date.';
            return;
        }

        const url = `${this.endpointValue}?date=${encodeURIComponent(date)}&guests=${encodeURIComponent(guests)}&service=${encodeURIComponent(service)}`;
        this.messageTarget.textContent = 'Recherche…';
        this.slotsTarget.innerHTML = '';

        try {
            const r = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!r.ok) {
                this.messageTarget.textContent = `Erreur ${r.status}`;
                return;
            }
            const data = await r.json();
            this.render(data);
        } catch (err) {
            this.messageTarget.textContent = `Erreur réseau : ${err.message}`;
        }
    }

    render(data) {
        if (!data.slots || data.slots.length === 0) {
            this.messageTarget.textContent = data.message || 'Aucun créneau disponible.';
            return;
        }
        this.messageTarget.textContent = `${data.slots.filter(s => s.available).length} créneau(x) disponible(s)`;
        this.slotsTarget.innerHTML = data.slots.map((s) => `
            <div class="slot ${s.available ? 'available' : 'unavailable'}" data-time="${s.time}">
                ${s.time}
                <small>${s.remaining}/${s.capacity}</small>
            </div>
        `).join('');
    }
}
