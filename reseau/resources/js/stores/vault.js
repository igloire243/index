import { reactive } from 'vue';
import { api, hooks } from '../api';
import { clearSecrets } from './secrets';

// Le serveur reste maître du verrouillage ; le client suit l'échéance pour réagir sans attendre une erreur.
const AWAY_LOCK_MS = 60_000;

export const vault = reactive({
    loaded: false,
    unlocked: false,
    ttl: 900, // secondes d'inactivité avant verrouillage (renvoyé par le serveur au déverrouillage)
    lockAt: 0,
    used: 0,
    quota: 0,
    maxFile: 0,
    dossiers: [],
    listLoaded: false,
});

let timer;
function armTimer() {
    clearTimeout(timer);
    if (!vault.unlocked) return;
    timer = setTimeout(() => {
        if (Date.now() >= vault.lockAt) markLocked();
        else armTimer();
    }, Math.max(1000, vault.lockAt - Date.now() + 500));
}

export function touchVault() {
    if (vault.unlocked) {
        vault.lockAt = Date.now() + vault.ttl * 1000;
        armTimer();
    }
}

function markLocked() {
    clearTimeout(timer);
    vault.unlocked = false;
    vault.dossiers = [];
    vault.listLoaded = false;
    clearSecrets();
}

hooks.onVaultLocked = markLocked;

function apply(status) {
    vault.unlocked = status.unlocked;
    vault.used = status.used_bytes;
    vault.quota = status.quota_bytes;
    vault.maxFile = status.max_file_bytes;
    if (status.unlocked) {
        vault.ttl = Math.max(status.expires_in, 60);
        touchVault();
    } else markLocked();
    vault.loaded = true;
}

export async function loadVaultStatus() {
    apply(await api('vault'));
}

export async function unlockVault(password) {
    apply(await api('vault/unlock', { method: 'POST', body: { password } }));
}

export async function lockVault() {
    try {
        await api('vault/lock', { method: 'POST' });
    } finally {
        markLocked();
    }
}

// Application en arrière-plan plus d'une minute (téléphone rangé, autre application) : le coffre se referme.
let hiddenAt = 0;
if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) hiddenAt = Date.now();
        else if (hiddenAt && vault.unlocked && Date.now() - hiddenAt > AWAY_LOCK_MS) lockVault().catch(() => {});
    });
}

/* ----- Dossiers ----- */

export async function loadDossiers(force = false) {
    if (vault.listLoaded && !force) return;
    vault.dossiers = await api('dossiers');
    vault.listLoaded = true;
    touchVault();
}

export async function saveDossier(data, id = null) {
    const saved = await api(id ? `dossiers/${id}` : 'dossiers', { method: id ? 'PUT' : 'POST', body: data });
    touchVault();
    await loadDossiers(true);
    return saved;
}

export async function removeDossier(id) {
    await api(`dossiers/${id}`, { method: 'DELETE' });
    vault.dossiers = vault.dossiers.filter((d) => d.id !== id);
    await loadVaultStatus();
}

export async function loadDossier(id) {
    const d = await api(`dossiers/${id}`);
    touchVault();
    return d;
}

export async function updateFile(id, data) {
    const f = await api(`dossier-files/${id}`, { method: 'PUT', body: data });
    touchVault();
    return f;
}

export async function removeFile(id) {
    await api(`dossier-files/${id}`, { method: 'DELETE' });
    await loadVaultStatus();
}
