import { reactive } from 'vue';
import { api } from '../api';
import { distKm, hasCoords } from '../lib/contacts';
import { describeGeoError } from '../lib/geo';

const POS_KEY = 'reseau.pos';

function readPos() {
    try {
        const p = JSON.parse(localStorage.getItem(POS_KEY) || 'null');
        return p && Number.isFinite(p.lat) && Number.isFinite(p.lng) ? p : null;
    } catch {
        return null;
    }
}

export const contacts = reactive({ list: [], loaded: false, loading: false, pos: readPos(), posError: null });

/** Fenêtres ouvertes depuis n'importe quelle vue (fiche détail et formulaire). */
export const ui = reactive({ detailId: null, form: { open: false, id: null, prefill: null } });
export const openDetail = (id) => (ui.detailId = id);
export const openForm = (id = null, prefill = null) => {
    ui.detailId = null;
    ui.form = { open: true, id, prefill };
};

export async function loadContacts(force = false) {
    if (contacts.loading || (contacts.loaded && !force)) return;
    contacts.loading = true;
    try {
        contacts.list = await api('contacts');
        contacts.loaded = true;
    } finally {
        contacts.loading = false;
    }
}

export async function saveContact(data, id = null) {
    const saved = await api(id ? `contacts/${id}` : 'contacts', { method: id ? 'PUT' : 'POST', body: data });
    const i = contacts.list.findIndex((c) => c.id === saved.id);
    if (i >= 0) contacts.list[i] = saved;
    else contacts.list.push(saved);
    return saved;
}

export async function deleteContact(id) {
    await api(`contacts/${id}`, { method: 'DELETE' });
    contacts.list = contacts.list.filter((c) => c.id !== id);
}

export async function importContacts(list) {
    const res = await api('contacts/import', { method: 'POST', body: { contacts: list } });
    await loadContacts(true);
    return res;
}

export async function deleteAllContacts() {
    await api('contacts', { method: 'DELETE' });
    contacts.list = [];
}

export const distTo = (c) => (contacts.pos && hasCoords(c) ? distKm(contacts.pos, c) : null);

/** Demande la position. Renvoie null en cas d'échec, et la raison (avec le geste de réparation) est dans contacts.posError. */
export function locate() {
    return new Promise((resolve) => {
        contacts.posError = null;
        const secure = window.isSecureContext !== false;
        if (!secure || !navigator.geolocation) {
            contacts.posError = describeGeoError(null, { secure, supported: !!navigator.geolocation });
            return resolve(null);
        }
        navigator.geolocation.getCurrentPosition(
            (p) => {
                contacts.pos = { lat: p.coords.latitude, lng: p.coords.longitude, t: Date.now() };
                try {
                    localStorage.setItem(POS_KEY, JSON.stringify(contacts.pos));
                } catch {
                    /* ignoré */
                }
                resolve(contacts.pos);
            },
            (err) => {
                contacts.posError = describeGeoError(err);
                resolve(null);
            },
            { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 },
        );
    });
}

export async function geocode(q) {
    const res = await fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=fr&q=' + encodeURIComponent(q));
    if (!res.ok) throw new Error('geocode');
    const [hit] = await res.json();
    return hit ? { lat: parseFloat(hit.lat), lng: parseFloat(hit.lon) } : null;
}

export async function reverseGeocode(lat, lng) {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=14&accept-language=fr&lat=${lat}&lon=${lng}`);
    if (!res.ok) throw new Error('reverse');
    const a = (await res.json()).address || {};
    return [a.suburb || a.neighbourhood, a.city || a.town || a.village || a.municipality, a.country].filter(Boolean).join(', ');
}
