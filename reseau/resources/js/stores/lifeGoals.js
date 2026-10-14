import { reactive } from 'vue';
import * as api from '../lib/lifeGoals';

export const lifeGoals = reactive({ loaded: false, loading: false, list: [], shares: [] });

export async function loadLifeGoals(force = false) {
    if (lifeGoals.loading || (lifeGoals.loaded && !force)) return;
    lifeGoals.loading = true;
    try {
        lifeGoals.list = await api.loadLifeGoals();
        lifeGoals.loaded = true;
    } finally {
        lifeGoals.loading = false;
    }
}

export async function seedLifeGoals() {
    lifeGoals.list = await api.seedLifeGoals();
    lifeGoals.loaded = true;

    return lifeGoals.list;
}

const replace = (g) => {
    const i = lifeGoals.list.findIndex((x) => x.id === g.id);
    if (i >= 0) lifeGoals.list[i] = g;
    else lifeGoals.list.push(g);

    return g;
};

export async function saveLifeGoal(data, id = null) {
    return replace(await api.saveLifeGoal(data, id));
}

export async function removeLifeGoal(id) {
    await api.removeLifeGoal(id);
    lifeGoals.list = lifeGoals.list.filter((g) => g.id !== id);
}

export async function loadLifeGoalShares() {
    lifeGoals.shares = await api.listLifeGoalShares();

    return lifeGoals.shares;
}

export async function createLifeGoalShare(label) {
    const created = await api.createLifeGoalShare(label);
    await loadLifeGoalShares();

    return created;
}

export async function revokeLifeGoalShare(id) {
    await api.revokeLifeGoalShare(id);
    lifeGoals.shares = lifeGoals.shares.filter((s) => s.id !== id);
}
