import { describe, expect, it } from 'vitest';
import { findTemplate, PROJECT_TEMPLATES } from '../projectTemplates';

describe('PROJECT_TEMPLATES', () => {
    it('a des clés uniques et au moins deux étapes chacun', () => {
        const keys = PROJECT_TEMPLATES.map((t) => t.key);
        expect(new Set(keys).size).toBe(keys.length);
        for (const t of PROJECT_TEMPLATES) {
            expect(t.title.length).toBeGreaterThan(0);
            expect(t.steps.length).toBeGreaterThanOrEqual(2);
        }
    });

    it('findTemplate retrouve par clé, ou renvoie null', () => {
        expect(findTemplate('commerce')?.title).toBe('Ouvrir un commerce');
        expect(findTemplate('inconnu')).toBeNull();
    });
});
