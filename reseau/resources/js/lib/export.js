import { ApiError } from '../api';

const xsrfToken = () => decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1] || '');

/**
 * Télécharge la sauvegarde chiffrée du coffre (réponse binaire, pas JSON : ne passe pas par `api()`).
 * Renvoie un Blob ; c'est l'appelant qui déclenche le téléchargement dans le navigateur.
 */
export async function exportVault(password) {
    const res = await fetch('/api/export', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify({ password }),
    });
    if (!res.ok) {
        const data = await res.json().catch(() => null);
        const first = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new ApiError(res.status, first || data?.message || `Erreur ${res.status}`, data?.errors, data?.code ?? null);
    }

    const name = /filename="([^"]+)"/.exec(res.headers.get('Content-Disposition') ?? '')?.[1] ?? `reseau-export-${new Date().toISOString().slice(0, 10)}.enc`;

    return { blob: await res.blob(), name };
}

export function downloadBlob(blob, name) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
}
