import { describe, expect, it } from 'vitest';
import { AGE_BRACKETS, filterGoals, groupByBracket, lifeGoalStats, nextStatus } from '../lifeGoals';

describe('nextStatus', () => {
    it('fait tourner none -> planned -> done -> none', () => {
        expect(nextStatus('none')).toBe('planned');
        expect(nextStatus('planned')).toBe('done');
        expect(nextStatus('done')).toBe('none');
    });
    it('revient à none pour une valeur inconnue', () => {
        expect(nextStatus(undefined)).toBe('none');
    });
});

describe('groupByBracket', () => {
    it('regroupe dans l’ordre des tranches d’âge, même désordonné en entrée', () => {
        const goals = [
            { id: 1, age_bracket: 'un_jour' },
            { id: 2, age_bracket: 'avant_12' },
            { id: 3, age_bracket: 'avant_12' },
        ];
        const groups = groupByBracket(goals);
        expect(groups.map((g) => g.bracket)).toEqual(Object.keys(AGE_BRACKETS));
        expect(groups[0].items.map((g) => g.id)).toEqual([2, 3]);
        expect(groups.at(-1).items.map((g) => g.id)).toEqual([1]);
        expect(groups[1].items).toEqual([]); // avant_18 : rien
    });
});

describe('filterGoals', () => {
    const list = [
        { id: 1, domain: 'voyage', status: 'done' },
        { id: 2, domain: 'voyage', status: 'planned' },
        { id: 3, domain: 'finances', status: 'done' },
    ];
    it('filtre par domaine', () => {
        expect(filterGoals(list, { domain: 'finances' }).map((g) => g.id)).toEqual([3]);
    });
    it('filtre par statut', () => {
        expect(filterGoals(list, { status: 'done' }).map((g) => g.id)).toEqual([1, 3]);
    });
    it('sans filtre, renvoie tout', () => {
        expect(filterGoals(list)).toEqual(list);
    });
});

describe('lifeGoalStats', () => {
    it('compte fait / prévu / total', () => {
        const list = [{ status: 'done' }, { status: 'done' }, { status: 'planned' }, { status: 'none' }];
        expect(lifeGoalStats(list)).toEqual({ done: 2, planned: 1, total: 4 });
    });
    it('gère une liste vide', () => {
        expect(lifeGoalStats([])).toEqual({ done: 0, planned: 0, total: 0 });
    });
});
