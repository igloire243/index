import { reactive } from 'vue';
import { api } from '../api';

export const secrets = reactive({ list: [], loaded: false });

export const clearSecrets = () => {
    secrets.list = [];
    secrets.loaded = false;
};

const summary = (s) => ({ id: s.id, type: s.type, favorite: s.favorite, title: s.title, username: s.username, url: s.url, updated_at: s.updated_at });

export async function loadSecrets(force = false) {
    if (secrets.loaded && !force) return;
    secrets.list = await api('secrets');
    secrets.loaded = true;
}

/** La fiche complète (avec le secret) : demandée seulement à l'ouverture d'une fiche. */
export const getSecret = (id) => api(`secrets/${id}`);

export async function saveSecret(data, id = null) {
    const saved = await api(id ? `secrets/${id}` : 'secrets', { method: id ? 'PUT' : 'POST', body: data });
    const i = secrets.list.findIndex((s) => s.id === saved.id);
    if (i >= 0) secrets.list[i] = summary(saved);
    else secrets.list.push(summary(saved));
    return saved;
}

export async function removeSecret(id) {
    await api(`secrets/${id}`, { method: 'DELETE' });
    secrets.list = secrets.list.filter((s) => s.id !== id);
}

export async function setFavorite(id, favorite) {
    const full = await getSecret(id);
    const { id: _id, updated_at: _u, ...rest } = full;
    return saveSecret({ ...rest, favorite }, id);
}
