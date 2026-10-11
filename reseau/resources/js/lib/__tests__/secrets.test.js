import { describe, expect, it } from 'vitest';
import { dotCenter, entropyBits, filterSecrets, generatePassword, generatePin, patternFromString, patternToString, patternValid, strength } from '../secrets';

describe('generatePassword', () => {
    it('respecte la longueur et contient chaque famille choisie', () => {
        for (let i = 0; i < 200; i++) {
            const p = generatePassword({ length: 12 });
            expect(p).toHaveLength(12);
            expect(p).toMatch(/[a-z]/);
            expect(p).toMatch(/[A-Z]/);
            expect(p).toMatch(/\d/);
            expect(p).toMatch(/[^A-Za-z0-9]/);
        }
    });
    it('n’utilise que les familles demandées', () => {
        expect(generatePassword({ length: 30, upper: false, symbols: false })).toMatch(/^[a-z0-9]+$/);
        expect(generatePassword({ length: 30, lower: false, upper: false, symbols: false })).toMatch(/^[0-9]+$/);
        expect(generatePassword({ length: 10, lower: false, upper: false, digits: false, symbols: false })).toMatch(/^[a-z]+$/); // jamais vide
    });
    it('borne la longueur', () => {
        expect(generatePassword({ length: 1 })).toHaveLength(4);
        expect(generatePassword({ length: 9999 })).toHaveLength(128);
    });
    it('est aléatoire', () => {
        expect(new Set(Array.from({ length: 50 }, () => generatePassword())).size).toBe(50);
    });
    it('mélange avec le tirage fourni', () => {
        let n = 0;
        expect(generatePassword({ length: 8 }, () => 0 * n++)).toHaveLength(8);
    });
});

describe('generatePin', () => {
    it('ne contient que des chiffres, 4 à 12', () => {
        expect(generatePin(4)).toMatch(/^\d{4}$/);
        expect(generatePin(6)).toMatch(/^\d{6}$/);
        expect(generatePin(2)).toHaveLength(4);
        expect(generatePin(99)).toHaveLength(12);
    });
    it('couvre tous les chiffres', () => {
        const seen = new Set(generatePin(12) + generatePin(12) + generatePin(12) + generatePin(12) + generatePin(12) + generatePin(12));
        expect(seen.size).toBeGreaterThanOrEqual(9);
    });
});

describe('strength', () => {
    it('classe les mots de passe', () => {
        expect(strength('').level).toBe(0);
        expect(strength('1234').level).toBe(1);
        expect(strength('password').level).toBe(1);
        expect(strength('aaaaaaaaaaaa').level).toBe(1);
        expect(strength('Chat2024').level).toBeLessThanOrEqual(3);
        expect(strength(generatePassword({ length: 20 })).level).toBe(4);
    });
    it('croît avec la longueur', () => {
        expect(entropyBits('Zq8!kd')).toBeLessThan(entropyBits('Zq8!kdPw3#mX'));
    });
});

describe('schéma', () => {
    it('s’écrit et se relit', () => {
        expect(patternToString([1, 5, 9, 6])).toBe('1-5-9-6');
        expect(patternFromString('1-5-9-6')).toEqual([1, 5, 9, 6]);
        expect(patternFromString('')).toEqual([]);
        expect(patternFromString(null)).toEqual([]);
        expect(patternFromString('1-1-2-x-12-0')).toEqual([1, 2]);
    });
    it('exige 4 points au minimum', () => {
        expect(patternValid([1, 2, 3])).toBe(false);
        expect(patternValid([1, 2, 3, 6])).toBe(true);
    });
    it('place les points sur une grille 3 × 3', () => {
        expect(dotCenter(1)).toEqual({ x: 50, y: 50 });
        expect(dotCenter(5)).toEqual({ x: 150, y: 150 });
        expect(dotCenter(9)).toEqual({ x: 250, y: 250 });
        expect(dotCenter(4)).toEqual({ x: 50, y: 150 });
    });
});

describe('filterSecrets', () => {
    const list = [
        { id: 1, type: 'site', title: 'Facebook', username: 'moi@x.com', url: 'https://facebook.com', favorite: false },
        { id: 2, type: 'pin', title: 'Code carte', username: null, url: null, favorite: true },
        { id: 3, type: 'site', title: 'Épargne en ligne', username: null, url: null, favorite: false },
    ];
    it('met les favoris en premier puis trie par titre', () => {
        expect(filterSecrets(list).map((s) => s.id)).toEqual([2, 3, 1]);
    });
    it('filtre par type et par texte sans tenir compte des accents', () => {
        expect(filterSecrets(list, { type: 'site' }).map((s) => s.id)).toEqual([3, 1]);
        expect(filterSecrets(list, { query: 'epargne' }).map((s) => s.id)).toEqual([3]);
        expect(filterSecrets(list, { query: 'FACEBOOK.com' }).map((s) => s.id)).toEqual([1]);
        expect(filterSecrets(list, { query: 'zzz' })).toEqual([]);
    });
});
