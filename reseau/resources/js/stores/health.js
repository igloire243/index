import { reactive } from 'vue';
import { api } from '../api';
import { addDays, isoDate } from '../lib/dates';
import { logKey } from '../lib/health';

export const health = reactive({
    loaded: false,
    loading: false,
    habits: [],
    logs: new Set(),
    meals: [],
    measurements: [],
    goal: null,
    programs: [],
});

export async function loadHealth(force = false) {
    if (health.loading || (health.loaded && !force)) return;
    health.loading = true;
    try {
        const today = isoDate();
        const [habits, logs, measurements, goal] = await Promise.all([
            api('habits'),
            api('habit-logs', { query: { from: addDays(today, -120), to: addDays(today, 7) } }),
            api('measurements'),
            api('goal'),
        ]);
        health.habits = habits;
        health.logs = new Set(logs.map((l) => logKey(l.habit_id, l.date)));
        health.measurements = measurements;
        health.goal = goal;
        health.loaded = true;
    } finally {
        health.loading = false;
    }
}

export async function loadPrograms() {
    if (!health.programs.length) health.programs = await api('programs');
}

/** Coche ou décoche une habitude ; l'affichage change tout de suite et revient en arrière si le serveur refuse. */
export async function toggleHabit(habit, date) {
    const key = logKey(habit.id, date);
    const wasDone = health.logs.has(key);
    if (wasDone) health.logs.delete(key);
    else health.logs.add(key);
    try {
        await api(`habits/${habit.id}/logs/${date}`, { method: 'PUT', body: { done: !wasDone } });
    } catch (e) {
        if (wasDone) health.logs.add(key);
        else health.logs.delete(key);
        throw e;
    }
}

export async function saveHabit(data, id = null) {
    const saved = await api(id ? `habits/${id}` : 'habits', { method: id ? 'PUT' : 'POST', body: data });
    const i = health.habits.findIndex((h) => h.id === saved.id);
    if (i >= 0) health.habits[i] = saved;
    else health.habits.push(saved);
    return saved;
}

export async function archiveHabit(id) {
    await api(`habits/${id}`, { method: 'DELETE' });
    health.habits = health.habits.filter((h) => h.id !== id);
}

export async function adoptProgram(key) {
    const res = await api(`programs/${key}/adopt`, { method: 'POST' });
    health.habits = await api('habits');
    return res;
}

/* ----- Repas ----- */

export async function loadMeals(from, to) {
    const fetched = await api('meals', { query: { from, to } });
    health.meals = [...health.meals.filter((m) => m.date < from || m.date > to), ...fetched];
}

export async function addMeal(data) {
    const meal = await api('meals', { method: 'POST', body: data });
    health.meals.push(meal);
    return meal;
}

export async function setMealDone(meal, done) {
    const previous = meal.done_at;
    meal.done_at = done ? new Date().toISOString() : null;
    try {
        const saved = await api(`meals/${meal.id}`, { method: 'PUT', body: { done } });
        meal.done_at = saved.done_at;
    } catch (e) {
        meal.done_at = previous;
        throw e;
    }
}

export async function removeMeal(id) {
    await api(`meals/${id}`, { method: 'DELETE' });
    health.meals = health.meals.filter((m) => m.id !== id);
}

export async function copyWeek(from, to) {
    const res = await api('meals/copy-week', { method: 'POST', body: { from, to } });
    await loadMeals(to, addDays(to, 6));
    return res;
}

/* ----- Suivi ----- */

export async function saveMeasurement(date, data) {
    const saved = await api(`measurements/${date}`, { method: 'PUT', body: data });
    const i = health.measurements.findIndex((m) => m.date === saved.date);
    if (i >= 0) health.measurements[i] = saved;
    else health.measurements.push(saved);
    health.measurements.sort((a, b) => a.date.localeCompare(b.date));
    return saved;
}

export async function removeMeasurement(date) {
    await api(`measurements/${date}`, { method: 'DELETE' });
    health.measurements = health.measurements.filter((m) => m.date !== date);
}

export async function saveGoal(data) {
    health.goal = await api('goal', { method: 'PUT', body: data });
}
