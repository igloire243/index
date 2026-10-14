import { api } from '../api';

export const DOMAINS = {
    education: 'Éducation',
    carriere: 'Carrière',
    finances: 'Finances',
    famille: 'Famille',
    spiritualite: 'Spiritualité',
    sante: 'Santé',
    voyage: 'Voyage',
    communaute: 'Communauté',
    developpement_personnel: 'Développement personnel',
    loisirs: 'Loisirs',
};

export const AGE_BRACKETS = {
    avant_12: 'Avant 12 ans',
    avant_18: 'Avant 18 ans',
    avant_25: 'Avant 25 ans',
    avant_30: 'Avant 30 ans',
    avant_40: 'Avant 40 ans',
    avant_50: 'Avant 50 ans',
    avant_60: 'Avant 60 ans',
    un_jour: 'Un jour, sans âge précis',
};

export const STATUSES = {
    none: 'Jamais pensé',
    planned: 'Je compte le faire',
    done: 'Déjà fait',
};

/** Prochain statut dans le cycle none → planned → done → none, pour un simple clic qui fait avancer. */
export const nextStatus = (status) => ({ none: 'planned', planned: 'done', done: 'none' })[status] ?? 'none';

/** Regroupe une liste d'accomplissements par tranche d'âge, dans l'ordre d'AGE_BRACKETS, en gardant leur position. */
export function groupByBracket(goals) {
    return Object.keys(AGE_BRACKETS).map((bracket) => ({ bracket, label: AGE_BRACKETS[bracket], items: goals.filter((g) => g.age_bracket === bracket) }));
}

export function filterGoals(list, { domain = '', status = '' } = {}) {
    return list.filter((g) => (!domain || g.domain === domain) && (!status || g.status === status));
}

/** Un résumé pour le tableau de bord « Comment ça va » : combien d'accomplissements faits, prévus, au total. */
export function lifeGoalStats(list) {
    return {
        done: list.filter((g) => g.status === 'done').length,
        planned: list.filter((g) => g.status === 'planned').length,
        total: list.length,
    };
}

/* ----- Côté propriétaire (connecté) ----- */

export const loadLifeGoals = () => api('life-goals');
export const seedLifeGoals = () => api('life-goals/seed', { method: 'POST' });
export const saveLifeGoal = (data, id = null) => api(id ? `life-goals/${id}` : 'life-goals', { method: id ? 'PUT' : 'POST', body: data });
export const removeLifeGoal = (id) => api(`life-goals/${id}`, { method: 'DELETE' });

export const listLifeGoalShares = () => api('life-goal-shares');
export const createLifeGoalShare = (label) => api('life-goal-shares', { method: 'POST', body: { label } });
export const showLifeGoalShare = (id) => api(`life-goal-shares/${id}`);
export const revokeLifeGoalShare = (id) => api(`life-goal-shares/${id}`, { method: 'DELETE' });

export const lifeGoalShareUrl = (token) => `${typeof location !== 'undefined' ? location.origin : ''}/accomplissements/${token}`;

/* ----- Côté public (aucun compte) ----- */

export const fetchPublicLifeGoals = (token) => api(`partage-vie/${token}`);
export const setRespondentName = (token, name) => api(`partage-vie/${token}/nom`, { method: 'PUT', body: { respondent_name: name } });
export const answerLifeGoal = (token, goalId, data) => api(`partage-vie/${token}/accomplissements/${goalId}`, { method: 'PUT', body: data });
