import { daysSince, norm } from './contacts';
import { isoDate } from './dates';

export const STATUSES = {
    idea: { label: 'Idée', icon: 'star' },
    in_progress: { label: 'En cours', icon: 'target' },
    done: { label: 'Terminé', icon: 'check' },
    abandoned: { label: 'Abandonné', icon: 'x' },
};

export const ACTIVE_STATUSES = ['idea', 'in_progress'];

export function progress(project) {
    const steps = project.steps ?? [];
    if (!steps.length) return null;

    return { done: steps.filter((s) => s.done).length, total: steps.length };
}

export function progressRatio(project) {
    const p = progress(project);

    return p ? p.done / p.total : 0;
}

export const isStalled = (project, today = isoDate(), days = 21) => project.status === 'in_progress' && daysSince(project.updated_at?.slice(0, 10) ?? today) >= days;

export function filterProjects(list, { query = '', status = '' } = {}) {
    const q = norm(query).trim();

    return list.filter((p) => (!status || p.status === status) && (!q || norm(`${p.title} ${p.description ?? ''}`).includes(q)));
}

/**
 * Un projet à mettre en avant dans le Coach : d'abord celui dont l'échéance approche, sinon le plus stagnant
 * (en cours, sans la moindre mise à jour depuis longtemps), sinon rien. Fonction pure, testable sans réseau.
 */
export function projectToNudge(projects, today = isoDate()) {
    const active = projects.filter((p) => ACTIVE_STATUSES.includes(p.status));
    if (!active.length) return null;

    const dueSoon = active
        .filter((p) => p.target_date && p.target_date >= today)
        .sort((a, b) => a.target_date.localeCompare(b.target_date))[0];
    if (dueSoon) return { project: dueSoon, reason: 'echeance' };

    const overdue = active.filter((p) => p.target_date && p.target_date < today).sort((a, b) => a.target_date.localeCompare(b.target_date))[0];
    if (overdue) return { project: overdue, reason: 'retard' };

    const stalled = active.filter((p) => isStalled(p, today)).sort((a, b) => (a.updated_at ?? '').localeCompare(b.updated_at ?? ''))[0];
    if (stalled) return { project: stalled, reason: 'stagnation' };

    return null;
}
