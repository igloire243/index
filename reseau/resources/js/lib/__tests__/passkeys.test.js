import { describe, expect, it } from 'vitest';
import { b64uToBuf, bufToB64u, describePasskeyError } from '../passkeys';

describe('base64url', () => {
    it('fait l’aller-retour sur des octets quelconques', () => {
        const bytes = Uint8Array.from([0, 1, 2, 250, 251, 252, 253, 254, 255, 62, 63]);
        const text = bufToB64u(bytes.buffer);
        expect(text).not.toMatch(/[+/=]/);
        expect([...new Uint8Array(b64uToBuf(text))]).toEqual([...bytes]);
    });

    it('décode les valeurs sans remplissage', () => {
        expect([...new Uint8Array(b64uToBuf('AQID'))]).toEqual([1, 2, 3]);
        expect([...new Uint8Array(b64uToBuf('AQI'))]).toEqual([1, 2]);
    });
});

describe('describePasskeyError', () => {
    it('traduit les erreurs du navigateur en conseils', () => {
        expect(describePasskeyError({ name: 'NotAllowedError' })).toMatch(/annulée/);
        expect(describePasskeyError({ name: 'InvalidStateError' })).toMatch(/déjà enregistré/);
        expect(describePasskeyError({ name: 'SecurityError' })).toMatch(/HTTPS/);
        expect(describePasskeyError({ name: 'NotSupportedError' })).toMatch(/ne propose pas/);
    });

    it('garde le message du serveur pour les autres erreurs', () => {
        expect(describePasskeyError(new Error('Trop de tentatives.'))).toBe('Trop de tentatives.');
        expect(describePasskeyError(undefined)).toBe('Vérification impossible.');
    });
});
