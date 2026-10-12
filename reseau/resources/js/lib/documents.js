import { ApiError } from '../api';
import { norm } from './contacts';
import { daysBetween } from './dates';

const xsrfToken = () => decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1] || '');

/** Catégories des documents personnels (clés alignées sur config/vault.php côté serveur). */
export const DOCUMENT_CATEGORIES = {
    acte_naissance: { label: 'Acte de naissance', icon: 'file' },
    diplome: { label: 'Diplôme', icon: 'book' },
    certificat: { label: 'Certificat', icon: 'file' },
    carte_identite: { label: 'Carte d’identité', icon: 'card' },
    passeport: { label: 'Passeport', icon: 'globe' },
    bulletin: { label: 'Bulletin', icon: 'book' },
    document_parcellaire: { label: 'Document parcellaire', icon: 'folder' },
    document_vehicule: { label: 'Document de véhicule', icon: 'card' },
    preuve_paiement: { label: 'Preuve de paiement', icon: 'wallet' },
    autre: { label: 'Autre document', icon: 'folder' },
};

export const categoryLabel = (key) => DOCUMENT_CATEGORIES[key]?.label ?? key;

/**
 * État d'une échéance (passeport, carte d'identité…) : « expired », « soon » (30 j ou moins), ou null si rien à
 * signaler. Fonction pure, utilisée pour les badges dans la liste et la fiche d'un document.
 */
export function expiryStatus(doc, today = new Date().toISOString().slice(0, 10)) {
    if (!doc.expiry_date) return null;
    const days = daysBetween(today, doc.expiry_date);
    if (days < 0) return 'expired';
    if (days <= 30) return 'soon';

    return null;
}

export function filterDocuments(list, { query = '', category = '' } = {}) {
    const q = norm(query).trim();

    return list.filter((d) => (!category || d.category === category) && (!q || norm(`${d.title} ${d.subject ?? ''}`).includes(q)));
}

/** Un fichier ne peut être fusionné en PDF que s'il peut devenir une page : PDF ou image JPEG/PNG. */
export const isMergeable = (file) => file.mime === 'application/pdf' || file.mime === 'image/jpeg' || file.mime === 'image/png';

/** Fusionne plusieurs fichiers en un seul PDF (réponse binaire, pas JSON : ne passe pas par `api()`). */
export async function mergeDocuments(fileIds) {
    const res = await fetch('/api/documents/fusionner', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify({ file_ids: fileIds }),
    });
    if (!res.ok) {
        const data = await res.json().catch(() => null);
        throw new ApiError(res.status, data?.message || `Erreur ${res.status}`, data?.errors, data?.code ?? null);
    }

    const name = /filename\*=UTF-8''([^;]+)/.exec(res.headers.get('Content-Disposition') ?? '')?.[1];

    return { blob: await res.blob(), name: name ? decodeURIComponent(name) : 'documents-fusionnes.pdf' };
}
