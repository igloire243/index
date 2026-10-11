import { api, ApiError } from '../api';

export const KINDS = {
    image: { label: 'Photo', icon: 'image' },
    video: { label: 'Vidéo', icon: 'film' },
    audio: { label: 'Audio', icon: 'music' },
    document: { label: 'Document', icon: 'file' },
};

export function fmtSize(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) return '—';
    if (bytes < 1024) return `${bytes} o`;
    const units = ['Ko', 'Mo', 'Go', 'To'];
    let v = bytes / 1024;
    let i = 0;
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v >= 100 ? Math.round(v) : v.toFixed(1).replace('.', ',').replace(',0', '')} ${units[i]}`;
}

export function fmtClock(seconds) {
    const s = Math.max(0, Math.floor(seconds));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** Nombre de morceaux et longueur de chacun, exactement comme le serveur les attend. */
export function chunkPlan(size, chunkSize) {
    const total = Math.max(1, Math.ceil(size / chunkSize));
    return Array.from({ length: total }, (_, i) => ({ index: i, start: i * chunkSize, end: Math.min(size, (i + 1) * chunkSize) }));
}

/** Contrôles faits avant d'envoyer quoi que ce soit, pour échouer vite avec un message clair. */
export function precheck(file, { maxFile, quota, used }) {
    if (!file.size) return 'Ce fichier est vide.';
    if (maxFile && file.size > maxFile) return `Ce fichier dépasse la taille maximale (${fmtSize(maxFile)}).`;
    if (quota && used + file.size > quota) return 'Il ne reste pas assez d’espace dans le coffre.';
    return null;
}

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

/** Un morceau : on réessaie quand le réseau coupe ou que le serveur bute (mauvaise couverture sur téléphone). */
async function putChunk(fileId, index, blob, signal, attempts = 4) {
    for (let n = 1; ; n++) {
        try {
            return await api(`dossier-files/${fileId}/chunks/${index}`, { method: 'PUT', raw: blob, signal });
        } catch (e) {
            const transient = e instanceof ApiError && (e.status === 0 || e.status >= 500) && e.code !== 'aborted';
            if (!transient || n >= attempts) throw e;
            await wait(600 * 2 ** (n - 1));
        }
    }
}

/**
 * Envoie $file dans le dossier, morceau par morceau (aucune limite de taille PHP à régler). `onProgress` reçoit 0 à 1.
 * Si l'envoi échoue ou est annulé, la réservation est supprimée côté serveur.
 */
export async function uploadFile(dossierId, file, { onProgress = () => {}, signal } = {}) {
    const init = await api(`dossiers/${dossierId}/files`, { method: 'POST', body: { name: file.name || 'fichier', size: file.size, mime: file.type || null } });
    try {
        const plan = chunkPlan(file.size, init.chunk_size);
        for (const part of plan) {
            await putChunk(init.id, part.index, file.slice(part.start, part.end), signal);
            onProgress(part.end / file.size);
        }
        return await api(`dossier-files/${init.id}/complete`, { method: 'POST' });
    } catch (e) {
        api(`dossier-files/${init.id}`, { method: 'DELETE' }).catch(() => {});
        throw e;
    }
}

export const contentUrl = (fileId) => `/api/dossier-files/${fileId}/content`;

/** Format d'enregistrement vocal accepté par ce navigateur (Chrome : webm/opus, Safari : mp4/aac). */
export function recorderMime() {
    if (typeof MediaRecorder === 'undefined') return null;
    return ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'].find((m) => MediaRecorder.isTypeSupported?.(m)) ?? '';
}

export const extensionFor = (mime) => (mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm');
