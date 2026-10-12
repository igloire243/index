import { describe, expect, it } from 'vitest';
import { cvStats, experiencePeriod, groupSkills } from '../cv';

describe('groupSkills', () => {
    it('regroupe par type, dans l’ordre compétence/connaissance/talent', () => {
        const skills = [
            { id: 1, type: 'talent', name: 'Chant' },
            { id: 2, type: 'competence', name: 'Plomberie' },
            { id: 3, type: 'competence', name: 'Soudure' },
        ];
        const grouped = groupSkills(skills);
        expect(grouped.map((g) => g.type)).toEqual(['competence', 'connaissance', 'talent']);
        expect(grouped[0].items.map((s) => s.name)).toEqual(['Plomberie', 'Soudure']);
        expect(grouped[1].items).toEqual([]);
        expect(grouped[2].items.map((s) => s.name)).toEqual(['Chant']);
    });
});

describe('experiencePeriod', () => {
    it('affiche une période en cours', () => {
        expect(experiencePeriod({ start_date: '2022-03-01', current: true })).toMatch(/2022 — en cours/);
    });
    it('affiche une période terminée', () => {
        expect(experiencePeriod({ start_date: '2020-01-01', end_date: '2021-06-01', current: false })).toMatch(/2020 — .*2021/);
    });
    it('gère une date manquante', () => {
        expect(experiencePeriod({ start_date: null, end_date: null, current: false })).toBe('');
    });
});

describe('cvStats', () => {
    it('compte chaque catégorie', () => {
        const cv = { skills: [{}, {}], languages: [{}], experiences: [{}, {}, {}] };
        expect(cvStats(cv)).toEqual({ skills: 2, languages: 1, experiences: 3 });
    });
});
