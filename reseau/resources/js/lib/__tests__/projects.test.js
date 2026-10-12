import { describe, expect, it } from 'vitest';
import { filterProjects, isStalled, progress, progressRatio, projectToNudge } from '../projects';

describe('progress', () => {
    it('renvoie null sans étape', () => {
        expect(progress({ steps: [] })).toBeNull();
        expect(progress({})).toBeNull();
    });
    it('compte les étapes faites', () => {
        expect(progress({ steps: [{ done: true }, { done: false }, { done: true }] })).toEqual({ done: 2, total: 3 });
    });
    it('progressRatio', () => {
        expect(progressRatio({ steps: [{ done: true }, { done: false }] })).toBe(0.5);
        expect(progressRatio({ steps: [] })).toBe(0);
    });
});

describe('isStalled', () => {
    it('vrai seulement pour un projet en cours sans mise à jour récente', () => {
        expect(isStalled({ status: 'in_progress', updated_at: '2026-09-01T00:00:00Z' }, '2026-10-12')).toBe(true);
        expect(isStalled({ status: 'in_progress', updated_at: '2026-10-10T00:00:00Z' }, '2026-10-12')).toBe(false);
        expect(isStalled({ status: 'idea', updated_at: '2026-09-01T00:00:00Z' }, '2026-10-12')).toBe(false);
        expect(isStalled({ status: 'done', updated_at: '2026-09-01T00:00:00Z' }, '2026-10-12')).toBe(false);
    });
});

describe('filterProjects', () => {
    const list = [
        { id: 1, title: 'Salon de coiffure', description: 'À Kinshasa', status: 'idea' },
        { id: 2, title: 'Application mobile', description: null, status: 'in_progress' },
        { id: 3, title: 'École privée', description: 'Projet terminé', status: 'done' },
    ];
    it('filtre par statut', () => {
        expect(filterProjects(list, { status: 'done' }).map((p) => p.id)).toEqual([3]);
    });
    it('filtre par texte, sans accents', () => {
        expect(filterProjects(list, { query: 'ecole' }).map((p) => p.id)).toEqual([3]);
        expect(filterProjects(list, { query: 'kinshasa' }).map((p) => p.id)).toEqual([1]);
    });
});

describe('projectToNudge', () => {
    it('ne propose rien sans projet actif', () => {
        expect(projectToNudge([{ status: 'done' }, { status: 'abandoned' }])).toBeNull();
        expect(projectToNudge([])).toBeNull();
    });

    it('priorise une échéance qui approche', () => {
        const projects = [
            { id: 1, status: 'in_progress', target_date: '2026-11-01', updated_at: '2026-10-01T00:00:00Z' },
            { id: 2, status: 'idea', target_date: '2026-10-15', updated_at: '2026-10-01T00:00:00Z' },
        ];
        expect(projectToNudge(projects, '2026-10-12').project.id).toBe(2);
        expect(projectToNudge(projects, '2026-10-12').reason).toBe('echeance');
    });

    it('signale un retard si l’échéance est dépassée', () => {
        const projects = [{ id: 1, status: 'in_progress', target_date: '2026-10-01', updated_at: '2026-10-01T00:00:00Z' }];
        const r = projectToNudge(projects, '2026-10-12');
        expect(r.project.id).toBe(1);
        expect(r.reason).toBe('retard');
    });

    it('sinon repère la stagnation', () => {
        const projects = [{ id: 1, status: 'in_progress', target_date: null, updated_at: '2026-09-01T00:00:00Z' }];
        expect(projectToNudge(projects, '2026-10-12')).toEqual({ project: projects[0], reason: 'stagnation' });
        expect(projectToNudge([{ status: 'idea', updated_at: '2026-09-01T00:00:00Z' }], '2026-10-12')).toBeNull();
    });
});
