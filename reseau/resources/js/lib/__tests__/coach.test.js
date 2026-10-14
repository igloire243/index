import { describe, expect, it } from 'vitest';
import { buildSuggestions, contactMeters, monthlyNetworkProgress, seededRandom } from '../coach';
import { parseDomains, parseVcf } from '../contacts';

const NOW = Date.now();
const ago = (days) => new Date(NOW - days * 86400000).toISOString().slice(0, 10);
const contact = (o = {}) => ({
    id: o.id ?? 1, name: 'Aline', phone: '', email: '', domains: ['santé'], role: '', place: '', lat: null, lng: null,
    how: '', anecdote: '', notes: '', favor: null, last_contact: null, created_at: new Date(NOW - 60 * 86400000).toISOString(), ...o,
});
const ids = (list) => list.map((s) => s.id);

describe('buildSuggestions', () => {
    it('propose de démarrer quand le carnet est vide', () => {
        const [s] = buildSuggestions([]);
        expect(s.id).toBe('start');
        expect(s.action.type).toBe('new');
    });

    it('est stable pour une même date et change d’un jour à l’autre', () => {
        const cs = [contact({ id: 1 }), contact({ id: 2, name: 'Paul', domains: ['droit'] }), contact({ id: 3, name: 'Eve', domains: ['finance'] })];
        const a = ids(buildSuggestions(cs, { today: '2026-10-12' }));
        expect(ids(buildSuggestions(cs, { today: '2026-10-12' }))).toEqual(a);
        expect(ids(buildSuggestions(cs, { today: '2026-10-13' }))).not.toEqual(a);
    });

    it('relance un contact sans nouvelles depuis plus de 90 jours', () => {
        const s = buildSuggestions([contact({ id: 7, last_contact: ago(120) })]).find((x) => x.id === 'relance:7');
        expect(s).toBeTruthy();
        expect(s.touch).toBe(true);
        expect(s.text).toContain('120 jours');
    });

    it('ne relance pas un contact récent', () => {
        expect(ids(buildSuggestions([contact({ id: 7, last_contact: ago(10) })]))).not.toContain('relance:7');
    });

    it('met en tête un service à rendre', () => {
        const list = buildSuggestions([contact({ id: 3, favor: 'owe' }), contact({ id: 4, name: 'Paul', last_contact: ago(100) })], { today: '2026-10-12' });
        expect(list[0].id).toBe('owe:3');
    });

    it('signale un contact proche seulement si la position est connue', () => {
        const c = contact({ id: 5, lat: -4.33, lng: 15.31, last_contact: ago(40) });
        expect(ids(buildSuggestions([c]))).not.toContain('proche:5');
        expect(ids(buildSuggestions([c], { pos: { lat: -4.32, lng: 15.31 } }))).toContain('proche:5');
        expect(ids(buildSuggestions([c], { pos: { lat: 48.85, lng: 2.35 } }))).not.toContain('proche:5');
    });

    it('propose les domaines manquants et les domaines à contact unique', () => {
        const list = buildSuggestions([contact({ id: 1, domains: ['santé'] })]);
        expect(list.filter((s) => s.id.startsWith('gap:')).length).toBeGreaterThan(0);
        expect(ids(list)).not.toContain('gap:sante');
        expect(ids(list)).toContain('thin:sante');
    });

    it('propose de présenter deux contacts du même lieu mais de domaines différents', () => {
        const cs = [contact({ id: 1, domains: ['santé'], place: 'Kinshasa' }), contact({ id: 2, name: 'Paul', domains: ['droit'], place: 'kinshasa' })];
        expect(buildSuggestions(cs).some((s) => s.id === 'intro:1:2')).toBe(true);
        const same = [contact({ id: 1, place: 'Kinshasa' }), contact({ id: 2, name: 'Paul', place: 'Kinshasa' })];
        expect(buildSuggestions(same).some((s) => s.id.startsWith('intro:'))).toBe(false);
    });

    it('limite à deux suggestions par type', () => {
        const cs = Array.from({ length: 8 }, (_, i) => contact({ id: i + 1, name: 'C' + i, last_contact: ago(200 + i) }));
        expect(buildSuggestions(cs).filter((s) => s.id.startsWith('relance:')).length).toBe(2);
    });

    it('masque les suggestions mises de côté jusqu’à leur échéance', () => {
        const c = contact({ id: 7, last_contact: ago(120) });
        const hidden = { 'relance:7': NOW + 86400000 };
        expect(ids(buildSuggestions([c], { hidden }))).not.toContain('relance:7');
        expect(ids(buildSuggestions([c], { hidden: { 'relance:7': NOW - 1000 } }))).toContain('relance:7');
    });

    it('n’utilise aucun texte genré pour parler d’un contact', () => {
        const cs = [contact({ id: 1, favor: 'owe', last_contact: ago(200) }), contact({ id: 2, name: 'Paul', favor: 'owed', domains: [], place: 'X' })];
        for (const s of buildSuggestions(cs)) expect(s.text).not.toMatch(/\bil\b(?! y a)|\belle\b|\b(lui|elle)-même\b/i);
    });
});

describe('suggestions financières', () => {
    const today = '2026-10-12';
    const debt = (o) => ({ id: 1, direction: 'owed_to_me', counterparty: 'Paul', amount: 100, remaining: 100, currency: 'USD', due_on: '2026-10-01', ...o });
    const run = (debts) => buildSuggestions([contact({ id: 1, last_contact: ago(5), anecdote: 'x', lat: 1, lng: 1 })], { today, debts });

    it('relance une créance dont l’échéance est dépassée', () => {
        const s = run([debt()]).find((x) => x.id === 'creance:1');
        expect(s.trait).toBe('finance');
        expect(s.title).toBe('Relance Paul');
        expect(s.text).toContain('11 jours');
        expect(s.action).toEqual({ label: 'Voir la créance', type: 'route', to: '/finances/creances' });
    });

    it('signale une créance proche de son échéance, mais pas une lointaine ni sans échéance', () => {
        expect(run([debt({ due_on: '2026-10-14' })]).some((x) => x.id === 'creance:1')).toBe(true);
        expect(run([debt({ due_on: '2026-12-01' })]).some((x) => x.id === 'creance:1')).toBe(false);
        expect(run([debt({ due_on: null })]).some((x) => x.id === 'creance:1')).toBe(false);
    });

    it('place une dette en retard au-dessus du reste', () => {
        const list = run([debt({ id: 2, direction: 'i_owe', counterparty: 'Banque' })]);
        expect(list[0].id).toBe('dette:2');
        expect(list[0].title).toBe('Rembourse Banque');
    });

    it('prévient d’une dette à rembourser dans la semaine', () => {
        expect(run([debt({ id: 3, direction: 'i_owe', due_on: '2026-10-16' })]).some((x) => x.id === 'dette:3')).toBe(true);
        expect(run([debt({ id: 3, direction: 'i_owe', due_on: '2026-10-30' })]).some((x) => x.id === 'dette:3')).toBe(false);
    });

    it('ignore les dettes soldées', () => {
        expect(run([debt({ remaining: 0 }), debt({ id: 2, direction: 'i_owe', remaining: 0 })]).some((x) => x.trait === 'finance')).toBe(false);
    });

    it('n’exige aucune donnée financière', () => {
        expect(() => buildSuggestions([contact()], { today })).not.toThrow();
    });
});

describe('contactMeters', () => {
    it('calcule les jauges', () => {
        const cs = [contact({ id: 1, anecdote: 'x', lat: 1, lng: 1, last_contact: ago(5) }), contact({ id: 2, domains: ['droit'] })];
        const m = Object.fromEntries(contactMeters(cs).map((x) => [x.label, x]));
        expect(m['Réseau'].value).toBe('2 / 50 contacts');
        expect(m['Anecdotes'].ratio).toBeCloseTo(0.1);
        expect(m['Contacts localisés'].ratio).toBe(0.5);
        expect(m['Liens entretenus (moins de 90 j)'].value).toBe('1 / 2');
    });

    it('évite les divisions par zéro', () => {
        for (const m of contactMeters([])) expect(Number.isFinite(m.ratio)).toBe(true);
    });
});

describe('monthlyNetworkProgress', () => {
    it('compte les contacts distincts relancés ce mois-ci', () => {
        const contacts = [
            { id: 1, last_contact: '2026-10-05' },
            { id: 2, last_contact: '2026-10-28' },
            { id: 3, last_contact: '2026-09-30' },
            { id: 4, last_contact: null },
            { id: 5 },
        ];
        expect(monthlyNetworkProgress(contacts, '2026-10-15')).toEqual({ count: 2, month: '2026-10' });
        expect(monthlyNetworkProgress(contacts, '2026-09-15')).toEqual({ count: 1, month: '2026-09' });
        expect(monthlyNetworkProgress([], '2026-10-15')).toEqual({ count: 0, month: '2026-10' });
    });
});

describe('seededRandom', () => {
    it('produit la même suite pour la même graine', () => {
        const a = seededRandom('x');
        const b = seededRandom('x');
        expect([a(), a(), a()]).toEqual([b(), b(), b()]);
        const v = seededRandom('y')();
        expect(v).toBeGreaterThanOrEqual(0);
        expect(v).toBeLessThan(1);
    });
});

describe('parseDomains', () => {
    it('dédoublonne sans tenir compte de la casse ni des accents', () => {
        expect(parseDomains(' Santé, santé ; Droit,, ')).toEqual(['Santé', 'Droit']);
    });
});

describe('parseVcf', () => {
    it('lit un export Google Contacts', () => {
        const vcf = [
            'BEGIN:VCARD', 'VERSION:3.0', 'FN:Jean Test', 'TEL;TYPE=CELL:+33 6 11 22 33 44', 'EMAIL:jean@example.com',
            'ORG:Acme;', 'TITLE:CTO', 'ADR;TYPE=HOME:;;1 rue X;Lyon;;69001;France', 'NOTE:Rencontré à Lyon', 'END:VCARD',
            'BEGIN:VCARD', 'VERSION:3.0', 'N:Doe;Jane;;;', 'END:VCARD',
            'BEGIN:VCARD', 'VERSION:3.0', 'TEL:123', 'END:VCARD',
        ].join('\r\n');
        const list = parseVcf(vcf);
        expect(list).toHaveLength(2); // la fiche sans nom est ignorée
        expect(list[0]).toMatchObject({ name: 'Jean Test', phone: '+33 6 11 22 33 44', email: 'jean@example.com', role: 'CTO — Acme', place: '1 rue X, Lyon, France', notes: 'Rencontré à Lyon' });
        expect(list[1].name).toBe('Jane Doe');
    });

    it('gère les lignes repliées', () => {
        const vcf = 'BEGIN:VCARD\nFN:Nom très\n  long\nEND:VCARD';
        expect(parseVcf(vcf)[0].name).toBe('Nom très long');
    });
});
