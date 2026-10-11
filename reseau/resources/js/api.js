export class ApiError extends Error {
    constructor(status, message, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export const hooks = { onUnauthorized: () => {} };

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
};

/**
 * Appel JSON vers /api/<path>. Lève une ApiError (avec les erreurs de validation de Laravel).
 * Une session expirée (401/419) déclenche hooks.onUnauthorized().
 */
export async function api(path, { method = 'GET', body, query } = {}) {
    const qs = query ? '?' + new URLSearchParams(query) : '';
    let res;
    try {
        res = await fetch(`/api/${path}${qs}`, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
    } catch {
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
        const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new ApiError(res.status, firstError || data?.message || `Erreur ${res.status}`, data?.errors);
    }
    return data;
}
