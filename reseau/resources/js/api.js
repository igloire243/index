export class ApiError extends Error {
    constructor(status, message, errors = {}, code = null) {
        super(message);
        this.status = status;
        this.errors = errors;
        this.code = code;
    }
}

export const hooks = { onUnauthorized: () => {}, onVaultLocked: () => {} };

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
};

/**
 * Appel JSON vers /api/<path>. Lève une ApiError (avec les erreurs de validation de Laravel).
 * Une session expirée (401/419) déclenche hooks.onUnauthorized(), un coffre verrouillé hooks.onVaultLocked().
 * `raw` (Blob ou texte) est envoyé tel quel, sans JSON : c'est ainsi que partent les morceaux de fichier du coffre.
 */
export async function api(path, { method = 'GET', body, query, raw, signal } = {}) {
    const qs = query ? '?' + new URLSearchParams(query) : '';
    let res;
    try {
        res = await fetch(`/api/${path}${qs}`, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                ...(raw !== undefined ? { 'Content-Type': 'application/octet-stream' } : body !== undefined ? { 'Content-Type': 'application/json' } : {}),
            },
            body: raw !== undefined ? raw : body !== undefined ? JSON.stringify(body) : undefined,
            signal,
        });
    } catch (e) {
        if (e?.name === 'AbortError') throw new ApiError(0, 'Envoi annulé.', {}, 'aborted');
        throw new ApiError(0, 'Pas de connexion au serveur.');
    }

    let data = null;
    try {
        data = await res.json();
    } catch {
        /* réponse sans corps JSON */
    }

    if (!res.ok) {
        if ((res.status === 401 && path !== 'login') || res.status === 419) hooks.onUnauthorized();
        if (res.status === 403 && data?.code === 'vault_locked') hooks.onVaultLocked();
        const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new ApiError(res.status, firstError || data?.message || `Erreur ${res.status}`, data?.errors, data?.code ?? null);
    }
    return data;
}
