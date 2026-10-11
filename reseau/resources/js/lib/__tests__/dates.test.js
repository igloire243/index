import { describe, expect, it } from 'vitest';
import { addDays, daysBetween, isoDate, isoWeekday, weekStart } from '../dates';

describe('dates', () => {
    it('formate une date locale en AAAA-MM-JJ', () => {
        expect(isoDate(new Date(2026, 9, 5))).toBe('2026-10-05');
    });

    it('ajoute des jours en traversant les mois et les années', () => {
        expect(addDays('2026-10-31', 1)).toBe('2026-11-01');
        expect(addDays('2026-01-01', -1)).toBe('2025-12-31');
        expect(addDays('2024-02-28', 2)).toBe('2024-03-01'); // année bissextile
    });

    it('donne le jour ISO (1 = lundi … 7 = dimanche)', () => {
        expect(isoWeekday('2026-10-12')).toBe(1);
        expect(isoWeekday('2026-10-11')).toBe(7);
    });

    it('trouve le lundi de la semaine', () => {
        expect(weekStart('2026-10-11')).toBe('2026-10-05'); // un dimanche
        expect(weekStart('2026-10-12')).toBe('2026-10-12'); // un lundi
        expect(weekStart('2026-10-14')).toBe('2026-10-12');
    });

    it('compte les jours entre deux dates', () => {
        expect(daysBetween('2026-10-01', '2026-10-12')).toBe(11);
        expect(daysBetween('2026-10-12', '2026-10-01')).toBe(-11);
    });
});
