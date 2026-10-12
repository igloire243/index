import { api } from '../api';

export const DURATIONS = [
    { minutes: 60, label: '1 heure' },
    { minutes: 1440, label: '24 heures' },
    { minutes: 10080, label: '7 jours' },
];

export const shareUrl = (token) => `${typeof location !== 'undefined' ? location.origin : ''}/partage/${token}`;
export const shareContentUrl = (token) => `/api/partage/${token}/contenu`;

/* ----- Côté propriétaire (coffre déverrouillé) ----- */

export const listShares = (fileId) => api(`dossier-files/${fileId}/partages`);
export const createShare = (fileId, minutes) => api(`dossier-files/${fileId}/partages`, { method: 'POST', body: { minutes } });
export const revokeShare = (id) => api(`dossier-file-shares/${id}`, { method: 'DELETE' });

/* ----- Côté public (aucune session) ----- */

export const fetchShareInfo = (token) => api(`partage/${token}`);
