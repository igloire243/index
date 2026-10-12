import { reactive } from 'vue';
import { api } from '../api';

export const cv = reactive({ loaded: false, loading: false, skills: [], languages: [], experiences: [], achievements: [] });

export async function loadCv(force = false) {
    if (cv.loading || (cv.loaded && !force)) return;
    cv.loading = true;
    try {
        const data = await api('cv');
        cv.skills = data.skills;
        cv.languages = data.languages;
        cv.experiences = data.experiences;
        cv.achievements = data.achievements;
        cv.loaded = true;
    } finally {
        cv.loading = false;
    }
}

const replaceIn = (list, item) => {
    const i = list.findIndex((x) => x.id === item.id);
    if (i >= 0) list[i] = item;
    else list.unshift(item);
};

export async function saveSkill(data, id = null) {
    const saved = await api(id ? `skills/${id}` : 'skills', { method: id ? 'PUT' : 'POST', body: data });
    replaceIn(cv.skills, saved);

    return saved;
}

export async function removeSkill(id) {
    await api(`skills/${id}`, { method: 'DELETE' });
    cv.skills = cv.skills.filter((s) => s.id !== id);
}

export async function saveLanguage(data, id = null) {
    const saved = await api(id ? `languages/${id}` : 'languages', { method: id ? 'PUT' : 'POST', body: data });
    replaceIn(cv.languages, saved);

    return saved;
}

export async function removeLanguage(id) {
    await api(`languages/${id}`, { method: 'DELETE' });
    cv.languages = cv.languages.filter((l) => l.id !== id);
}

export async function saveExperience(data, id = null) {
    const saved = await api(id ? `experiences/${id}` : 'experiences', { method: id ? 'PUT' : 'POST', body: data });
    replaceIn(cv.experiences, saved);

    return saved;
}

export async function removeExperience(id) {
    await api(`experiences/${id}`, { method: 'DELETE' });
    cv.experiences = cv.experiences.filter((e) => e.id !== id);
}

export async function saveAchievement(data, id = null) {
    const saved = await api(id ? `achievements/${id}` : 'achievements', { method: id ? 'PUT' : 'POST', body: data });
    if (saved.experience_id) {
        const exp = cv.experiences.find((e) => e.id === saved.experience_id);
        if (exp) replaceIn(exp.achievements, saved);
    } else {
        replaceIn(cv.achievements, saved);
    }

    return saved;
}

export async function removeAchievement(id, experienceId = null) {
    await api(`achievements/${id}`, { method: 'DELETE' });
    if (experienceId) {
        const exp = cv.experiences.find((e) => e.id === experienceId);
        if (exp) exp.achievements = exp.achievements.filter((a) => a.id !== id);
    } else {
        cv.achievements = cv.achievements.filter((a) => a.id !== id);
    }
}
