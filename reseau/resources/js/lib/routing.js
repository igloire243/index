import { distKm } from './contacts';

/**
 * Itinéraires calculés avec les serveurs de routage OpenStreetMap (OSRM, FOSSGIS) : gratuits, sans clé.
 * Les coordonnées de départ et d'arrivée leur sont envoyées pour calculer le trajet.
 */
export const MODES = {
    driving: { label: 'Voiture', emoji: '🚗', url: 'https://routing.openstreetmap.de/routed-car/route/v1/driving' },
    walking: { label: 'À pied', emoji: '🚶', url: 'https://routing.openstreetmap.de/routed-foot/route/v1/driving' },
    cycling: { label: 'Vélo', emoji: '🚲', url: 'https://routing.openstreetmap.de/routed-bike/route/v1/driving' },
};

const DIRECTIONS = {
    left: 'à gauche',
    right: 'à droite',
    'slight left': 'légèrement à gauche',
    'slight right': 'légèrement à droite',
    'sharp left': 'franchement à gauche',
    'sharp right': 'franchement à droite',
};

const ordinal = (n) => (n === 1 ? '1re' : `${n}e`);

/** Phrase française pour une étape OSRM. */
export function formatStep(step) {
    const { type, modifier, exit } = step.maneuver || {};
    const on = step.name ? ` sur ${step.name}` : '';
    const dir = DIRECTIONS[modifier];

    switch (type) {
        case 'depart':
            return step.name ? `Démarre sur ${step.name}` : 'Démarre';
        case 'arrive':
            return modifier === 'left' ? 'Tu es arrivé : la destination est à gauche' : modifier === 'right' ? 'Tu es arrivé : la destination est à droite' : 'Tu es arrivé';
        case 'turn':
            if (modifier === 'uturn') return `Fais demi-tour${on}`;
            if (modifier === 'straight') return `Continue tout droit${on}`;
            return `Tourne ${dir ?? 'à gauche ou à droite'}${on}`;
        case 'new name':
            return `Continue${on}`;
        case 'continue':
            return modifier === 'uturn' ? `Fais demi-tour${on}` : dir ? `Continue ${dir}${on}` : `Continue tout droit${on}`;
        case 'merge':
            return `Insère-toi${on}`;
        case 'on ramp':
        case 'off ramp':
            return `Prends la bretelle${dir ? ` ${dir}` : ''}${on}`;
        case 'fork':
            return `À la bifurcation, prends ${modifier?.includes('left') ? 'à gauche' : modifier?.includes('right') ? 'à droite' : 'tout droit'}${on}`;
        case 'end of road':
            return `Au bout de la route, tourne ${dir ?? 'à gauche ou à droite'}${on}`;
        case 'roundabout':
        case 'rotary':
            return exit ? `Au rond-point, prends la ${ordinal(exit)} sortie${on}` : `Prends le rond-point${on}`;
        case 'roundabout turn':
            return `Au rond-point, tourne ${dir ?? 'à gauche ou à droite'}${on}`;
        case 'exit roundabout':
        case 'exit rotary':
            return `Sors du rond-point${on}`;
        default:
            return `Continue${on}`;
    }
}

export function fmtDuration(seconds) {
    const min = Math.max(1, Math.round(seconds / 60));
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m ? `${h} h ${String(m).padStart(2, '0')}` : `${h} h`;
}

export function fmtRouteDistance(meters) {
    if (meters < 1000) return `${Math.max(10, Math.round(meters / 10) * 10)} m`;
    return `${(meters / 1000).toLocaleString('fr-FR', { maximumFractionDigits: meters < 10000 ? 1 : 0 })} km`;
}

/** Transforme la réponse OSRM en trajet simple : tracé [lat, lng], distance, durée et étapes en français. */
export function parseRoute(route) {
    const steps = route.legs.flatMap((leg) => leg.steps).map((s) => ({
        text: formatStep(s),
        distance: s.distance,
        duration: s.duration,
        type: s.maneuver?.type,
        location: [s.maneuver.location[1], s.maneuver.location[0]],
    }));

    return {
        distance: route.distance,
        duration: route.duration,
        coords: route.geometry.coordinates.map(([lng, lat]) => [lat, lng]),
        steps,
    };
}

export async function fetchRoute(from, to, mode = 'driving', signal) {
    const base = (MODES[mode] || MODES.driving).url;
    const res = await fetch(`${base}/${from.lng},${from.lat};${to.lng},${to.lat}?overview=full&geometries=geojson&steps=true`, { signal });
    if (!res.ok) throw new Error(`Service d’itinéraire indisponible (${res.status}).`);
    const data = await res.json();
    if (data.code !== 'Ok' || !data.routes?.length) {
        throw new Error(data.code === 'NoRoute' ? 'Aucun itinéraire trouvé entre ces deux points.' : 'Itinéraire introuvable.');
    }
    return parseRoute(data.routes[0]);
}

const meters = (a, b) => distKm({ lat: a[0], lng: a[1] }, { lat: b[0], lng: b[1] }) * 1000;

/** Étape en cours : on passe à la suivante quand on est à moins de `radius` mètres de son point de manœuvre. */
export function advanceStep(steps, current, pos, radius = 30) {
    let i = current;
    while (i < steps.length - 1 && meters(pos, steps[i + 1].location) <= radius) i++;
    return i;
}

/** Distance (m) entre la position et le point du tracé le plus proche (les points du tracé sont rapprochés). */
export function distanceToRoute(coords, pos) {
    let min = Infinity;
    for (const c of coords) min = Math.min(min, meters(pos, c));
    return min;
}

export const distanceMeters = meters;
