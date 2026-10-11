import { describe, expect, it } from 'vitest';
import { adherence, dayPart, dayProgress, dueHabits, isDue, logKey, recentAverages, streak, weightStats } from '../health';

const habit = (o = {}) => ({ id: 1, name: 'Marche', days: [1, 2, 3, 4, 5, 6, 7], time: '08:00', start_date: '2026-10-01', end_date: null, archived_at: null, ...o });
const logsOf = (habitId, dates) => new Set(dates.map((d) => logKey(habitId, d)));

describe('isDue', () => {
    it('respecte les jours de la semaine', () => {
        const h = habit({ days: [1, 3] });
        expect(isDue(h, '2026-10-12')).toBe(true); // lundi
        expect(isDue(h, '2026-10-13')).toBe(false); // mardi
    });

    it('respecte la période d’activité et l’archivage', () => {
        expect(isDue(habit({ start_date: '2026-10-10' }), '2026-10-09')).toBe(false);
        expect(isDue(habit({ end_date: '2026-10-10' }), '2026-10-11')).toBe(false);
        expect(isDue(habit({ archived_at: '2026-10-05T00:00:00Z' }), '2026-10-12')).toBe(false);
    });
});

describe('dueHabits et dayPart', () => {
    it('trie par heure, les habitudes sans heure à la fin', () => {
        const hs = [habit({ id: 1, name: 'B', time: null }), habit({ id: 2, name: 'A', time: '19:00' }), habit({ id: 3, name: 'C', time: '07:00' })];
        expect(dueHabits(hs, '2026-10-12').map((h) => h.id)).toEqual([3, 2, 1]);
    });

    it('classe les moments de la journée', () => {
        expect(dayPart({ time: '07:59' })).toBe('Matin');
        expect(dayPart({ time: '12:00' })).toBe('Après-midi');
        expect(dayPart({ time: '18:00' })).toBe('Soir');
        expect(dayPart({ time: null })).toBe('Dans la journée');
    });
});

describe('streak', () => {
    const today = '2026-10-12';

    it('compte les jours consécutifs réalisés', () => {
        const logs = logsOf(1, ['2026-10-10', '2026-10-11', '2026-10-12']);
        expect(streak(habit(), logs, today)).toBe(3);
    });

    it('ne casse pas la série si aujourd’hui n’est pas encore fait', () => {
        const logs = logsOf(1, ['2026-10-10', '2026-10-11']);
        expect(streak(habit(), logs, today)).toBe(2);
    });

    it('s’arrête au premier jour prévu manqué', () => {
        const logs = logsOf(1, ['2026-10-08', '2026-10-10', '2026-10-11', '2026-10-12']); // le 9 manque
        expect(streak(habit(), logs, today)).toBe(3);
    });

    it('saute les jours non prévus sans casser la série', () => {
        const h = habit({ days: [1, 3, 5] }); // lun, mer, ven
        const logs = logsOf(1, ['2026-10-07', '2026-10-09', '2026-10-12']); // mer 7, ven 9, lun 12
        expect(streak(h, logs, today)).toBe(3);
    });

    it('ne remonte pas avant le début de l’habitude', () => {
        const h = habit({ start_date: '2026-10-11' });
        const logs = logsOf(1, ['2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12']);
        expect(streak(h, logs, today)).toBe(2);
    });

    it('vaut zéro sans aucun enregistrement', () => {
        expect(streak(habit(), new Set(), today)).toBe(0);
    });
});

describe('adherence', () => {
    it('calcule le taux de réalisation sur une période', () => {
        const hs = [habit({ id: 1 }), habit({ id: 2, days: [1] })];
        const logs = new Set([logKey(1, '2026-10-12'), logKey(2, '2026-10-12'), logKey(1, '2026-10-13')]);
        const a = adherence(hs, logs, '2026-10-12', '2026-10-13');
        expect(a.days).toEqual([
            { date: '2026-10-12', due: 2, done: 2 },
            { date: '2026-10-13', due: 1, done: 1 },
        ]);
        expect(a.ratio).toBe(1);
    });

    it('renvoie null quand rien n’était prévu', () => {
        expect(adherence([], new Set(), '2026-10-12', '2026-10-14').ratio).toBeNull();
    });

    it('dayProgress compte les habitudes faites', () => {
        expect(dayProgress([habit({ id: 1 }), habit({ id: 2 })], logsOf(1, ['2026-10-12']), '2026-10-12')).toEqual({ due: 2, done: 1 });
    });
});

describe('weightStats', () => {
    const m = (date, weight_kg) => ({ date, weight_kg });

    it('renvoie null sans pesée', () => {
        expect(weightStats([], null)).toBeNull();
        expect(weightStats([{ date: '2026-10-01', weight_kg: null }], null)).toBeNull();
    });

    it('calcule évolution, rythme et avancement vers l’objectif (prise de poids)', () => {
        const s = weightStats([m('2026-09-14', 68), m('2026-09-28', 69), m('2026-10-12', 70)], { goal: 'gain', target_weight_kg: 72 });
        expect(s.change).toBeCloseTo(2);
        expect(s.weeklyRate).toBeCloseTo(0.5, 5); // 1 kg en 14 jours, depuis le relevé du 28/09
        expect(s.progress).toBeCloseTo(0.5);
        expect(s.remaining).toBeCloseTo(2);
        expect(s.tooFast).toBe(false);
    });

    it('calcule l’avancement pour une perte de poids', () => {
        const s = weightStats([m('2026-09-01', 80), m('2026-10-01', 77)], { goal: 'lose', target_weight_kg: 70 });
        expect(s.progress).toBeCloseTo(0.3);
        expect(s.remaining).toBeCloseTo(-7);
    });

    it('borne l’avancement entre 0 et 1', () => {
        expect(weightStats([m('2026-09-01', 80), m('2026-10-01', 82)], { goal: 'lose', target_weight_kg: 70 }).progress).toBe(0);
        expect(weightStats([m('2026-09-01', 80), m('2026-10-01', 65)], { goal: 'lose', target_weight_kg: 70 }).progress).toBe(1);
    });

    it('signale un rythme de plus de 1 kg par semaine, dans les deux sens', () => {
        expect(weightStats([m('2026-10-01', 70), m('2026-10-08', 72)], null).tooFast).toBe(true);
        expect(weightStats([m('2026-10-01', 70), m('2026-10-08', 68.5)], null).tooFast).toBe(true);
        expect(weightStats([m('2026-10-01', 70), m('2026-10-08', 70.5)], null).tooFast).toBe(false);
    });

    it('ne calcule pas de rythme avec moins de 7 jours d’écart', () => {
        expect(weightStats([m('2026-10-10', 70), m('2026-10-12', 72)], null).weeklyRate).toBeNull();
    });

    it('n’affiche pas d’avancement pour « maintenir »', () => {
        expect(weightStats([m('2026-09-01', 70), m('2026-10-01', 71)], { goal: 'maintain', target_weight_kg: 70 }).progress).toBeNull();
    });

    it('trie les mesures désordonnées', () => {
        const s = weightStats([m('2026-10-12', 70), m('2026-10-01', 68)], null);
        expect(s.first.date).toBe('2026-10-01');
        expect(s.last.date).toBe('2026-10-12');
    });
});

describe('recentAverages', () => {
    it('moyenne les 7 derniers jours seulement', () => {
        const ms = [
            { date: '2026-10-04', sleep_hours: 4, energy: 1, mood: 1 }, // hors fenêtre
            { date: '2026-10-10', sleep_hours: 6, energy: 2, mood: null },
            { date: '2026-10-12', sleep_hours: 8, energy: 4, mood: 5 },
        ];
        expect(recentAverages(ms, '2026-10-12')).toEqual({ sleep: 7, energy: 3, mood: 5 });
    });

    it('renvoie null sans donnée', () => {
        expect(recentAverages([], '2026-10-12')).toEqual({ sleep: null, energy: null, mood: null });
    });
});
