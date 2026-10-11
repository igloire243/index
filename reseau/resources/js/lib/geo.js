/**
 * Explique pourquoi la localisation a échoué, avec le geste qui permet de la réparer.
 * Le cas le plus courant sur téléphone : le site est ouvert en http:// (hors localhost),
 * et le navigateur interdit alors la géolocalisation.
 */
export function describeGeoError(err, { secure = true, supported = true } = {}) {
    if (!secure) {
        return 'La localisation exige une connexion sécurisée (HTTPS). Ouvre le site en https://, ou depuis localhost sur l’ordinateur.';
    }
    if (!supported) return 'Ce navigateur ne gère pas la localisation.';
    switch (err?.code) {
        case 1:
            return 'Localisation refusée. Autorise-la pour ce site dans les réglages du navigateur, et vérifie que la localisation du téléphone est activée.';
        case 2:
            return 'Position introuvable. Active la localisation (GPS) du téléphone puis réessaie.';
        case 3:
            return 'La recherche de position a pris trop de temps. Réessaie, de préférence avec le GPS activé.';
        default:
            return 'Position indisponible.';
    }
}
