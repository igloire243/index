// Toutes les dates de l'application sont des chaînes « AAAA-MM-JJ » dans le fuseau local de l'appareil.

const pad = (n) => String(n).padStart(2, '0');

export const isoDate = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export const parseISO = (iso) => {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d);
};

export const addDays = (iso, n) => {
    const d = parseISO(iso);
    d.setDate(d.getDate() + n);
    return isoDate(d);
};

/** Jour ISO : 1 = lundi … 7 = dimanche. */
export const isoWeekday = (iso) => parseISO(iso).getDay() || 7;

export const weekStart = (iso) => addDays(iso, 1 - isoWeekday(iso));

export const daysBetween = (a, b) => Math.round((parseISO(b) - parseISO(a)) / 86400000);

export const WEEKDAYS = [
    { n: 1, short: 'L', name: 'Lundi' },
    { n: 2, short: 'M', name: 'Mardi' },
    { n: 3, short: 'M', name: 'Mercredi' },
    { n: 4, short: 'J', name: 'Jeudi' },
    { n: 5, short: 'V', name: 'Vendredi' },
    { n: 6, short: 'S', name: 'Samedi' },
    { n: 7, short: 'D', name: 'Dimanche' },
];

export const fmtLong = (iso) =>
    parseISO(iso).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

export const fmtShort = (iso) => parseISO(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });

export const fmtFull = (iso) =>
    parseISO(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
