import { describe, expect, it } from 'vitest';
import { categoryLabel, DOCUMENT_CATEGORIES, expiryStatus, filterDocuments } from '../documents';

describe('categoryLabel', () => {
    it('renvoie le libellé connu, sinon la clé telle quelle', () => {
        expect(categoryLabel('passeport')).toBe('Passeport');
        expect(categoryLabel('mystere')).toBe('mystere');
    });
    it('couvre toutes les catégories attendues', () => {
        expect(Object.keys(DOCUMENT_CATEGORIES)).toEqual([
            'acte_naissance', 'diplome', 'certificat', 'carte_identite', 'passeport',
            'bulletin', 'document_parcellaire', 'document_vehicule', 'preuve_paiement', 'autre',
        ]);
    });
});

describe('expiryStatus', () => {
    const today = '2026-10-12';
    it('rien à signaler sans échéance', () => {
        expect(expiryStatus({}, today)).toBeNull();
    });
    it('expiré si l’échéance est passée', () => {
        expect(expiryStatus({ expiry_date: '2026-10-01' }, today)).toBe('expired');
    });
    it('bientôt si dans les 30 jours', () => {
        expect(expiryStatus({ expiry_date: '2026-10-25' }, today)).toBe('soon');
        expect(expiryStatus({ expiry_date: '2026-11-11' }, today)).toBe('soon');
    });
    it('rien à signaler au-delà de 30 jours', () => {
        expect(expiryStatus({ expiry_date: '2026-12-01' }, today)).toBeNull();
    });
});

describe('filterDocuments', () => {
    const list = [
        { id: 1, title: 'Passeport', category: 'passeport' },
        { id: 2, title: 'Diplôme ingénieur', category: 'diplome' },
        { id: 3, title: 'École primaire', subject: 'Bulletin', category: 'bulletin' },
    ];
    it('filtre par catégorie', () => {
        expect(filterDocuments(list, { category: 'diplome' }).map((d) => d.id)).toEqual([2]);
    });
    it('filtre par texte, sans accents', () => {
        expect(filterDocuments(list, { query: 'ecole' }).map((d) => d.id)).toEqual([3]);
    });
});
