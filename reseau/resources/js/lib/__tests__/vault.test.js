import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { chunkPlan, extensionFor, fmtClock, fmtSize, precheck, uploadFile } from '../vault';

describe('fmtSize', () => {
    it('formate en unités lisibles', () => {
        expect(fmtSize(0)).toBe('0 o');
        expect(fmtSize(900)).toBe('900 o');
        expect(fmtSize(1536)).toBe('1,5 Ko');
        expect(fmtSize(1024 * 1024)).toBe('1 Mo');
        expect(fmtSize(250 * 1024 * 1024)).toBe('250 Mo');
        expect(fmtSize(3.5 * 1024 ** 3)).toBe('3,5 Go');
        expect(fmtSize(-1)).toBe('—');
        expect(fmtSize(NaN)).toBe('—');
    });
});

describe('fmtClock', () => {
    it('affiche minutes et secondes', () => {
        expect(fmtClock(0)).toBe('0:00');
        expect(fmtClock(65.9)).toBe('1:05');
        expect(fmtClock(-4)).toBe('0:00');
    });
});

describe('chunkPlan', () => {
    it('découpe comme le serveur', () => {
        expect(chunkPlan(10, 4)).toEqual([
            { index: 0, start: 0, end: 4 },
            { index: 1, start: 4, end: 8 },
            { index: 2, start: 8, end: 10 },
        ]);
        expect(chunkPlan(8, 4)).toHaveLength(2);
        expect(chunkPlan(1, 4)).toEqual([{ index: 0, start: 0, end: 1 }]);
    });
});

describe('precheck', () => {
    const limits = { maxFile: 100, quota: 1000, used: 950 };
    it('refuse vide, trop gros ou hors quota', () => {
        expect(precheck({ size: 0 }, limits)).toMatch(/vide/);
        expect(precheck({ size: 101 }, limits)).toMatch(/taille maximale/);
        expect(precheck({ size: 60 }, limits)).toMatch(/espace/);
        expect(precheck({ size: 40 }, limits)).toBeNull();
    });
});

describe('extensionFor', () => {
    it('devine une extension', () => {
        expect(extensionFor('audio/webm;codecs=opus')).toBe('webm');
        expect(extensionFor('audio/mp4')).toBe('m4a');
        expect(extensionFor('audio/ogg')).toBe('ogg');
    });
});

describe('uploadFile', () => {
    const calls = [];
    let failChunk;

    const json = (status, body) => ({ ok: status < 400, status, json: async () => body });

    beforeEach(() => {
        calls.length = 0;
        failChunk = null;
        vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=abc' });
        vi.stubGlobal('fetch', async (url, opts) => {
            calls.push({ url, method: opts.method, body: opts.body, headers: opts.headers });
            if (url.endsWith('/files')) return json(201, { id: 7, chunk_size: 4, total_chunks: 3 });
            if (/chunks\/(\d+)$/.test(url)) {
                const i = Number(url.match(/chunks\/(\d+)$/)[1]);
                if (failChunk && failChunk.index === i && failChunk.times-- > 0) return json(failChunk.status, { message: 'boom' });
                return json(200, { received: i + 1, total: 3 });
            }
            if (url.endsWith('/complete')) return json(200, { id: 7, status: 'ready' });
            return json(200, { ok: true });
        });
        vi.useFakeTimers();
    });
    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    const file = () => new Blob([new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])], { type: 'image/png' });
    const run = async (p) => {
        const done = p.then((v) => v, (e) => e);
        await vi.runAllTimersAsync();
        return done;
    };

    it('réserve, envoie chaque morceau puis termine', async () => {
        const f = file();
        f.name = 'a.png';
        const progress = [];
        const res = await run(uploadFile(3, f, { onProgress: (p) => progress.push(p) }));
        expect(res).toEqual({ id: 7, status: 'ready' });
        expect(calls.map((c) => `${c.method} ${c.url}`)).toEqual([
            'POST /api/dossiers/3/files',
            'PUT /api/dossier-files/7/chunks/0',
            'PUT /api/dossier-files/7/chunks/1',
            'PUT /api/dossier-files/7/chunks/2',
            'POST /api/dossier-files/7/complete',
        ]);
        expect(JSON.parse(calls[0].body)).toEqual({ name: 'a.png', size: 10, mime: 'image/png' });
        expect(calls[1].headers['Content-Type']).toBe('application/octet-stream');
        expect(calls[1].headers['X-XSRF-TOKEN']).toBe('abc');
        expect(calls[3].body.size).toBe(2);
        expect(progress).toEqual([0.4, 0.8, 1]);
    });

    it('réessaie un morceau après une panne passagère', async () => {
        failChunk = { index: 1, status: 503, times: 2 };
        const res = await run(uploadFile(3, file()));
        expect(res.status).toBe('ready');
        expect(calls.filter((c) => c.url.endsWith('chunks/1'))).toHaveLength(3);
    });

    it('abandonne sur une erreur définitive et supprime la réservation', async () => {
        failChunk = { index: 0, status: 422, times: 99 };
        const err = await run(uploadFile(3, file()));
        expect(err.status).toBe(422);
        expect(calls.filter((c) => c.url.endsWith('chunks/0'))).toHaveLength(1);
        expect(calls.at(-1)).toMatchObject({ method: 'DELETE', url: '/api/dossier-files/7' });
    });

    it('abandonne après trop de pannes', async () => {
        failChunk = { index: 2, status: 500, times: 99 };
        const err = await run(uploadFile(3, file()));
        expect(err.status).toBe(500);
        expect(calls.filter((c) => c.url.endsWith('chunks/2'))).toHaveLength(4);
    });
});
