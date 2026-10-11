import { describe, expect, it } from 'vitest';
import { advanceStep, distanceToRoute, fmtDuration, fmtRouteDistance, formatStep, parseRoute } from '../routing';

const step = (type, modifier, name = '', extra = {}) => ({ name, distance: 100, duration: 10, maneuver: { type, modifier, location: [15.3, -4.3], ...extra } });

describe('formatStep', () => {
    it('décrit les manœuvres en français', () => {
        expect(formatStep(step('depart', null, 'Avenue de la Paix'))).toBe('Démarre sur Avenue de la Paix');
        expect(formatStep(step('depart'))).toBe('Démarre');
        expect(formatStep(step('turn', 'left', 'Boulevard du 30 Juin'))).toBe('Tourne à gauche sur Boulevard du 30 Juin');
        expect(formatStep(step('turn', 'slight right'))).toBe('Tourne légèrement à droite');
        expect(formatStep(step('turn', 'sharp left'))).toBe('Tourne franchement à gauche');
        expect(formatStep(step('turn', 'straight', 'Rue X'))).toBe('Continue tout droit sur Rue X');
        expect(formatStep(step('turn', 'uturn'))).toBe('Fais demi-tour');
        expect(formatStep(step('new name', null, 'Rue Y'))).toBe('Continue sur Rue Y');
        expect(formatStep(step('end of road', 'right'))).toBe('Au bout de la route, tourne à droite');
        expect(formatStep(step('roundabout', 'right', '', { exit: 2 }))).toBe('Au rond-point, prends la 2e sortie');
        expect(formatStep(step('roundabout', 'right', '', { exit: 1 }))).toBe('Au rond-point, prends la 1re sortie');
        expect(formatStep(step('fork', 'slight left'))).toBe('À la bifurcation, prends à gauche');
        expect(formatStep(step('merge', 'right', 'N1'))).toBe('Insère-toi sur N1');
        expect(formatStep(step('arrive', 'left'))).toBe('Tu es arrivé : la destination est à gauche');
        expect(formatStep(step('arrive'))).toBe('Tu es arrivé');
    });

    it('ne plante pas sur une manœuvre inconnue ou incomplète', () => {
        expect(formatStep(step('teleport'))).toBe('Continue');
        expect(formatStep({ name: '', distance: 1, duration: 1 })).toBe('Continue');
        expect(formatStep(step('turn', 'bizarre'))).toBe('Tourne à gauche ou à droite');
    });
});

describe('formats', () => {
    it('formate les durées', () => {
        expect(fmtDuration(20)).toBe('1 min');
        expect(fmtDuration(14 * 60 + 20)).toBe('14 min');
        expect(fmtDuration(3600)).toBe('1 h');
        expect(fmtDuration(3600 + 5 * 60)).toBe('1 h 05');
    });

    it('formate les distances', () => {
        expect(fmtRouteDistance(4)).toBe('10 m');
        expect(fmtRouteDistance(347)).toBe('350 m');
        expect(fmtRouteDistance(4300)).toBe('4,3 km');
        expect(fmtRouteDistance(25400)).toBe('25 km');
    });
});

describe('parseRoute', () => {
    const osrm = {
        distance: 1200,
        duration: 300,
        geometry: { coordinates: [[15.3, -4.3], [15.31, -4.31], [15.32, -4.32]] },
        legs: [{ steps: [step('depart', null, 'A'), step('turn', 'left', 'B'), step('arrive', 'right')] }],
    };

    it('inverse longitude/latitude et garde les étapes', () => {
        const r = parseRoute(osrm);
        expect(r.coords[0]).toEqual([-4.3, 15.3]);
        expect(r.steps).toHaveLength(3);
        expect(r.steps[1].text).toBe('Tourne à gauche sur B');
        expect(r.steps[0].location).toEqual([-4.3, 15.3]);
        expect(r.distance).toBe(1200);
    });
});

describe('suivi', () => {
    const steps = [
        { location: [-4.3, 15.3] },
        { location: [-4.31, 15.31] },   // ~1,5 km plus loin
        { location: [-4.32, 15.32] },
    ];

    it('avance à l’étape suivante seulement quand on en est proche', () => {
        expect(advanceStep(steps, 0, [-4.3, 15.3])).toBe(0);
        expect(advanceStep(steps, 0, [-4.3099, 15.3099])).toBe(1); // à ~15 m de l’étape 1
        expect(advanceStep(steps, 1, [-4.3, 15.3])).toBe(1);       // ne revient jamais en arrière
    });

    it('peut sauter plusieurs étapes si on les a toutes dépassées d’un coup', () => {
        expect(advanceStep([{ location: [0, 0] }, { location: [0, 0.0001] }, { location: [0, 0.0002] }], 0, [0, 0.0002])).toBe(2);
    });

    it('mesure l’écart au tracé', () => {
        const coords = [[-4.3, 15.3], [-4.31, 15.31]];
        expect(distanceToRoute(coords, [-4.3, 15.3])).toBeLessThan(1);
        expect(distanceToRoute(coords, [-4.3, 15.4])).toBeGreaterThan(10000);
    });
});
