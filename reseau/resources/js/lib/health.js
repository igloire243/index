import { addDays, daysBetween, isoWeekday } from './dates';

export const CATEGORIES = {
    nutrition: 'Nutrition',
    hydratation: 'Hydratation',
    activite: 'Activité',
    sommeil: 'Sommeil',
    soins: 'Soins du corps',
    'bien-etre': 'Bien-être',
    suivi: 'Suivi',
    autre: 'Autre',
};

export const SLOTS = { breakfast: 'Petit-déjeuner', lunch: 'Déjeuner', snack: 'Collation', dinner: 'Dîner' };

export const GOALS = { lose: 'Perdre du poids', gain: 'Reprendre du poids', maintain: 'Maintenir / retrouver la forme' };

export const logKey = (habitId, date) => `${habitId}|${date}`;

/** Vrai si l'habitude est au programme ce jour-là (jour de la semaine + période d'activité). */
export function isDue(habit, date) {
    if (habit.archived_at) return false;
    if (habit.start_date && date < habit.start_date) return false;
    if (habit.end_date && date > habit.end_date) return false;
    return habit.days.includes(isoWeekday(date));
}

export function dueHabits(habits, date) {
    const withTime = (h) => h.time || '99:99';
    return habits.filter((h) => isDue(h, date)).sort((a, b) => withTime(a).localeCompare(withTime(b)) || a.name.localeCompare(b.name, 'fr'));
}

/** Moment de la journée d'une habitude, pour regrouper la liste du jour. */
export function dayPart(habit) {
    if (!habit.time) return 'Dans la journée';
    const hour = Number(habit.time.slice(0, 2));
    if (hour < 12) return 'Matin';
    if (hour < 18) return 'Après-midi';
    return 'Soir';
}

export const DAY_PARTS = ['Matin', 'Après-midi', 'Soir', 'Dans la journée'];

export function dayProgress(habits, logs, date) {
    const due = dueHabits(habits, date);
    return { due: due.length, done: due.filter((h) => logs.has(logKey(h.id, date))).length };
}

/**
 * Série en cours : nombre de jours prévus consécutifs réalisés, en remontant depuis aujourd'hui.
 * Un jour d'aujourd'hui pas encore fait ne casse pas la série ; un jour passé manqué, si.
 */
export function streak(habit, logs, today) {
    let n = 0;
    let d = today;
    for (let i = 0; i < 400; i++, d = addDays(d, -1)) {
        if (habit.start_date && d < habit.start_date) break;
        if (!isDue(habit, d)) continue;
        if (logs.has(logKey(habit.id, d))) n++;
        else if (d !== today) break;
    }
    return n;
}

/** Réalisation jour par jour sur une période (bornes incluses). */
export function adherence(habits, logs, from, to) {
    const days = [];
    let due = 0;
    let done = 0;
    for (let d = from; d <= to; d = addDays(d, 1)) {
        const p = dayProgress(habits, logs, d);
        days.push({ date: d, ...p });
        due += p.due;
        done += p.done;
    }
    return { days, due, done, ratio: due ? done / due : null };
}

/** Statistiques de poids : évolution, rythme sur 4 semaines, avancement vers l'objectif. */
export function weightStats(measurements, goal) {
    const points = measurements.filter((m) => m.weight_kg != null).sort((a, b) => a.date.localeCompare(b.date));
    if (!points.length) return null;

    const first = points[0];
    const last = points[points.length - 1];
    const change = last.weight_kg - first.weight_kg;

    // Rythme : on compare le dernier poids au plus ancien relevé des 28 derniers jours (au moins 7 jours d'écart).
    const ref = points.find((p) => p !== last && daysBetween(p.date, last.date) <= 28);
    let weeklyRate = null;
    if (ref) {
        const days = daysBetween(ref.date, last.date);
        if (days >= 7) weeklyRate = ((last.weight_kg - ref.weight_kg) / days) * 7;
    }

    const target = goal?.target_weight_kg ?? null;
    let progress = null;
    if (target != null && goal.goal !== 'maintain' && target !== first.weight_kg) {
        progress = Math.max(0, Math.min(1, (last.weight_kg - first.weight_kg) / (target - first.weight_kg)));
    }

    return {
        points, first, last, change, weeklyRate, progress,
        remaining: target != null ? target - last.weight_kg : null,
        // Plus de 1 kg par semaine, dans un sens ou dans l'autre : à signaler.
        tooFast: weeklyRate != null && Math.abs(weeklyRate) > 1,
    };
}

/** Moyennes sur les 7 derniers jours (sommeil, énergie, humeur). */
export function recentAverages(measurements, today) {
    const from = addDays(today, -6);
    const recent = measurements.filter((m) => m.date >= from && m.date <= today);
    const avg = (key) => {
        const v = recent.map((m) => m[key]).filter((x) => x != null);
        return v.length ? v.reduce((a, b) => a + b, 0) / v.length : null;
    };
    return { sleep: avg('sleep_hours'), energy: avg('energy'), mood: avg('mood') };
}
