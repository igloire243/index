import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { exportVault } from '../export';

describe('exportVault', () => {
    beforeEach(() => {
        vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=abc' });
    });
    afterEach(() => vi.unstubAllGlobals());

    it('envoie le mot de passe et renvoie le blob avec le nom du fichier', async () => {
        const blob = new Blob(['chiffré']);
        vi.stubGlobal('fetch', async (url, opts) => {
            expect(url).toBe('/api/export');
            expect(opts.method).toBe('POST');
            expect(JSON.parse(opts.body)).toEqual({ password: 'un-mot-de-passe-long' });
            expect(opts.headers['X-XSRF-TOKEN']).toBe('abc');
            return { ok: true, headers: new Map([['Content-Disposition', 'attachment; filename="reseau-export-2026-10-11.enc"']]), blob: async () => blob };
        });
        const res = await exportVault('un-mot-de-passe-long');
        expect(res.blob).toBe(blob);
        expect(res.name).toBe('reseau-export-2026-10-11.enc');
    });

    it('utilise un nom par défaut si l’en-tête est absent', async () => {
        vi.stubGlobal('fetch', async () => ({ ok: true, headers: new Map(), blob: async () => new Blob() }));
        const res = await exportVault('x');
        expect(res.name).toMatch(/^reseau-export-\d{4}-\d{2}-\d{2}\.enc$/);
    });

    it('relaie le message d’erreur du serveur', async () => {
        vi.stubGlobal('fetch', async () => ({ ok: false, status: 422, headers: new Map(), json: async () => ({ message: 'Le Coffre est trop volumineux.' }) }));
        await expect(exportVault('x')).rejects.toThrow('Le Coffre est trop volumineux.');
    });
});
