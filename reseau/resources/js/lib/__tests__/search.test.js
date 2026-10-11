import { describe, expect, it } from 'vitest';
import { hasResults, search } from '../search';

const data = {
    contacts: [
        { id: 1, name: 'Paul Mbala', domains: ['droit'], place: 'Kinshasa' },
        { id: 2, name: 'Épicerie du coin', domains: ['commerce'], role: 'Fournisseur' },
    ],
    dossiers: [{ id: 10, title: 'Vérifications', subject: 'Paul Mbala' }],
    secrets: [{ id: 20, title: 'Facebook', type: 'site', username: 'moi@example.com' }],
    accounts: [{ id: 30, name: 'Rawbank', institution: 'Rawbank', balance: 100, currency: 'USD' }],
    debts: [{ id: 40, counterparty: 'Paul Mbala', amount: 200, currency: 'USD', direction: 'owed_to_me' }],
    assets: [{ id: 50, name: 'Terrain Kingasani', status: 'wanted' }],
};

describe('search', () => {
    it('cherche sans tenir compte des accents ni de la casse', () => {
        const r = search(data, 'epicerie');
        expect(r.contacts.map((c) => c.id)).toEqual([2]);
    });

    it('cherche dans chaque catégorie', () => {
        const r = search(data, 'paul');
        expect(r.contacts.map((c) => c.id)).toEqual([1]);
        expect(r.dossiers.map((d) => d.id)).toEqual([10]);
        expect(r.finances.map((f) => f.id)).toEqual([40]);
    });

    it('cherche aussi par domaine, lieu, institution, intitulé', () => {
        expect(search(data, 'droit').contacts.map((c) => c.id)).toEqual([1]);
        expect(search(data, 'rawbank').finances.map((f) => f.id)).toEqual([30]);
        expect(search(data, 'facebook').secrets.map((s) => s.id)).toEqual([20]);
        expect(search(data, 'kingasani').finances.map((f) => f.id)).toEqual([50]);
    });

    it('ne renvoie rien pour une requête vide', () => {
        const r = search(data, '  ');
        expect(hasResults(r)).toBe(false);
    });

    it('limite chaque groupe', () => {
        const many = Array.from({ length: 10 }, (_, i) => ({ id: i, name: 'Jean ' + i }));
        expect(search({ ...data, contacts: many }, 'jean', 3).contacts).toHaveLength(3);
    });

    it('ne renvoie jamais le contenu sensible, seulement le titre et le type', () => {
        const r = search(data, 'facebook');
        expect(r.secrets).toEqual([{ kind: 'secret', id: 20, title: 'Facebook', subtitle: 'Compte en ligne' }]);
        expect(JSON.stringify(r)).not.toContain('moi@example.com');
    });
});
