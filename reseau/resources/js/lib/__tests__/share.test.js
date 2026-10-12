import { describe, expect, it } from 'vitest';
import { DURATIONS, shareContentUrl, shareUrl } from '../share';

describe('share', () => {
    it('construit les URL publiques', () => {
        expect(shareUrl('abc123')).toBe('/partage/abc123'); // pas de `location` en environnement de test (node)
        expect(shareContentUrl('abc123')).toBe('/api/partage/abc123/contenu');
    });

    it('propose les trois durées attendues par le serveur', () => {
        expect(DURATIONS.map((d) => d.minutes)).toEqual([60, 1440, 10080]);
    });
});
