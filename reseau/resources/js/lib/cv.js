/** Référentiels et petites fonctions pures pour le CV (compétences, langues, expériences, réalisations). */

export const SKILL_TYPES = {
    competence: { label: 'Compétence' },
    connaissance: { label: 'Connaissance' },
    talent: { label: 'Talent' },
};

export const LANGUAGE_LEVELS = {
    notions: 'Notions',
    intermediaire: 'Intermédiaire',
    courant: 'Courant',
    natif: 'Natif / bilingue',
};

/** Regroupe les compétences par type, dans l'ordre de SKILL_TYPES. */
export function groupSkills(skills) {
    return Object.keys(SKILL_TYPES).map((type) => ({ type, label: SKILL_TYPES[type].label, items: skills.filter((s) => s.type === type) }));
}

/** Période affichée pour une expérience : « 2022 — en cours », « janv. 2020 — déc. 2021 », etc. */
export function experiencePeriod(exp) {
    const fmt = (d) => new Date(d).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
    const start = exp.start_date ? fmt(exp.start_date) : null;
    const end = exp.current ? 'en cours' : exp.end_date ? fmt(exp.end_date) : null;
    if (start && end) return `${start} — ${end}`;

    return start || end || '';
}

/** Un résumé pour le tableau de bord « Comment ça va » : de quoi donner une idée du CV sans tout afficher. */
export function cvStats(cv) {
    return {
        skills: cv.skills.length,
        languages: cv.languages.length,
        experiences: cv.experiences.length,
    };
}
