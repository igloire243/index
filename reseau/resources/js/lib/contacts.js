import { parseISO } from './dates';

export const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

export const hasCoords = (c) => Number.isFinite(c.lat) && Number.isFinite(c.lng);

export function distKm(a, b) {
    const R = 6371;
    const r = Math.PI / 180;
    const dLat = (b.lat - a.lat) * r;
    const dLng = (b.lng - a.lng) * r;
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(x));
}

export function fmtDist(km) {
    if (km < 0.1) return '< 100 m';
    if (km < 1) return `${Math.round(km * 100) * 10} m`;
    if (km < 10) return `${km.toFixed(1).replace('.', ',')} km`;
    return `${Math.round(km).toLocaleString('fr-FR')} km`;
}

export const daysSince = (iso) => (iso ? Math.floor((Date.now() - parseISO(iso).getTime()) / 86400000) : null);

export const phoneHref = (p) => 'tel:' + String(p).replace(/[^\d+]/g, '');
export const smsHref = (p) => 'sms:' + String(p).replace(/[^\d+]/g, '');
export const waHref = (p) => 'https://wa.me/' + String(p).replace(/\D/g, '');

export function initials(name) {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    return ((parts[0]?.[0] || '?') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

export function hue(name) {
    let h = 0;
    for (const ch of name) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
    return h % 360;
}

export const inDomain = (c, key) => c.domains.some((d) => norm(d) === key);

export function parseDomains(input) {
    const seen = new Set();
    const out = [];
    for (const raw of Array.isArray(input) ? input : String(input || '').split(/[,;]/)) {
        const label = String(raw).trim().slice(0, 40);
        const key = norm(label);
        if (key && !seen.has(key)) {
            seen.add(key);
            out.push(label);
        }
    }
    return out;
}

export function allDomains(contacts) {
    const map = new Map();
    for (const c of contacts) {
        for (const d of c.domains) {
            const key = norm(d);
            const e = map.get(key) || { key, label: d, count: 0 };
            e.count++;
            map.set(key, e);
        }
    }
    return [...map.values()].sort((a, b) => a.label.localeCompare(b.label, 'fr'));
}

export function matches(c, query) {
    if (!query) return true;
    const hay = norm([c.name, c.role, c.place, c.how, c.anecdote, c.notes, c.phone, c.email, c.domains.join(' ')].join(' '));
    return query.split(/\s+/).every((w) => hay.includes(w));
}

/** Lit un fichier .vcf (export Google Contacts, iPhone…) et renvoie une liste de contacts. */
export function parseVcf(text) {
    const lines = text.replace(/\r\n?/g, '\n').replace(/\n[ \t]/g, '').split('\n');
    const unesc = (s) => s.replace(/\\n/gi, '\n').replace(/\\([,;\\])/g, '$1').trim();
    const out = [];
    let cur = null;
    for (const line of lines) {
        if (/^BEGIN:VCARD/i.test(line)) {
            cur = {};
            continue;
        }
        if (/^END:VCARD/i.test(line)) {
            if (cur && cur.name) out.push(cur);
            cur = null;
            continue;
        }
        if (!cur) continue;
        const i = line.indexOf(':');
        if (i < 0) continue;
        const key = line.slice(0, i).split(';')[0].toUpperCase().replace(/^ITEM\d+\./, '');
        const raw = line.slice(i + 1);
        const val = unesc(raw);
        if (key === 'FN' && val) cur.name = val;
        else if (key === 'N' && !cur.name) {
            const p = raw.split(';');
            cur.name = unesc([p[1], p[0]].filter(Boolean).join(' '));
        } else if (key === 'TEL' && !cur.phone) cur.phone = val;
        else if (key === 'EMAIL' && !cur.email) cur.email = val;
        else if (key === 'ORG') cur.org = unesc(raw.split(';').filter(Boolean).join(' '));
        else if (key === 'TITLE') cur.title = val;
        else if (key === 'NOTE') cur.notes = val;
        else if (key === 'ADR' && !cur.place) {
            const p = raw.split(';').map(unesc);
            cur.place = [p[2], p[3], p[6]].filter(Boolean).join(', ');
        }
    }
    return out.map(({ title, org, ...c }) => ({ ...c, role: [title, org].filter(Boolean).join(' — ') || undefined }));
}
