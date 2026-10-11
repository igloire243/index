import { describe, expect, it } from 'vitest';
import { describeGeoError } from '../geo';

describe('describeGeoError', () => {
    it('explique que HTTPS est nécessaire hors contexte sécurisé, quelle que soit l’erreur', () => {
        expect(describeGeoError({ code: 1 }, { secure: false })).toMatch(/HTTPS/);
        expect(describeGeoError(null, { secure: false })).toMatch(/HTTPS/);
    });

    it('distingue refus, position introuvable et délai dépassé', () => {
        expect(describeGeoError({ code: 1 })).toMatch(/refusée.*réglages/);
        expect(describeGeoError({ code: 2 })).toMatch(/GPS/);
        expect(describeGeoError({ code: 3 })).toMatch(/trop de temps/);
    });

    it('signale un navigateur sans géolocalisation', () => {
        expect(describeGeoError(null, { supported: false })).toMatch(/ne gère pas/);
    });

    it('a un message par défaut', () => {
        expect(describeGeoError({ code: 99 })).toBe('Position indisponible.');
        expect(describeGeoError(undefined)).toBe('Position indisponible.');
    });
});
