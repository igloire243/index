import { reactive } from 'vue';
import { api } from '../api';

export const projects = reactive({ list: [], loaded: false, loading: false });

export async function loadProjects(force = false) {
    if (projects.loading || (projects.loaded && !force)) return;
    projects.loading = true;
    try {
        projects.list = await api('projects');
        projects.loaded = true;
    } finally {
        projects.loading = false;
    }
}

const replace = (p) => {
    const i = projects.list.findIndex((x) => x.id === p.id);
    if (i >= 0) projects.list[i] = p;
    else projects.list.unshift(p);

    return p;
};

export async function saveProject(data, id = null) {
    return replace(await api(id ? `projects/${id}` : 'projects', { method: id ? 'PUT' : 'POST', body: data }));
}

export async function removeProject(id) {
    await api(`projects/${id}`, { method: 'DELETE' });
    projects.list = projects.list.filter((p) => p.id !== id);
}

export const loadProject = (id) => api(`projects/${id}`);

export const addStep = (projectId, title) => replace2(api(`projects/${projectId}/steps`, { method: 'POST', body: { title } }));
export const toggleStep = (stepId, done) => replace2(api(`project-steps/${stepId}`, { method: 'PUT', body: { done } }));
export const renameStep = (stepId, title) => replace2(api(`project-steps/${stepId}`, { method: 'PUT', body: { title } }));
export const removeStep = (stepId) => replace2(api(`project-steps/${stepId}`, { method: 'DELETE' }));
export const reorderSteps = (projectId, order) => replace2(api(`projects/${projectId}/steps/reorder`, { method: 'POST', body: { order } }));

async function replace2(promise) {
    return replace(await promise);
}
