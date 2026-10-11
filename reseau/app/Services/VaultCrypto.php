<?php

namespace App\Services;

use RuntimeException;

/**
 * Chiffrement des fichiers du coffre : XChaCha20-Poly1305, morceau par morceau.
 *
 * Chaque fichier a sa propre clé aléatoire. Il est découpé en morceaux de 1 Mio chiffrés indépendamment, ce qui permet
 * de lire un passage précis (avancer dans une vidéo) sans tout déchiffrer. Chaque morceau est stocké sous la forme
 * « nonce aléatoire (24 o) + données chiffrées + étiquette d'authentification (16 o) ». L'identifiant du fichier, la
 * position du morceau et le nombre total de morceaux sont authentifiés : on ne peut ni réordonner, ni échanger, ni
 * tronquer des morceaux sans que le déchiffrement échoue.
 */
final class VaultCrypto
{
    public const CHUNK = 1_048_576;

    public const NONCE = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES; // 24

    public const TAG = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;      // 16

    public static function newKey(): string
    {
        return sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
    }

    public static function totalChunks(int $size): int
    {
        return max(1, (int) ceil($size / self::CHUNK));
    }

    /** Taille en clair attendue pour le morceau $index d'un fichier de $size octets. */
    public static function plainLength(int $size, int $index): int
    {
        $total = self::totalChunks($size);

        return $index < $total - 1 ? self::CHUNK : $size - ($total - 1) * self::CHUNK;
    }

    public static function cipherChunkSize(): int
    {
        return self::NONCE + self::CHUNK + self::TAG;
    }

    /** Taille du fichier chiffré sur le disque. */
    public static function cipherSize(int $size): int
    {
        return $size + self::totalChunks($size) * (self::NONCE + self::TAG);
    }

    public static function encryptChunk(string $plain, string $key, string $uuid, int $index, int $total): string
    {
        $nonce = random_bytes(self::NONCE);

        return $nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, self::ad($uuid, $index, $total), $nonce, $key);
    }

    public static function decryptChunk(string $blob, string $key, string $uuid, int $index, int $total): string
    {
        if (strlen($blob) < self::NONCE + self::TAG) {
            throw new RuntimeException('Fichier du coffre tronqué.');
        }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($blob, self::NONCE), self::ad($uuid, $index, $total), substr($blob, 0, self::NONCE), $key);
        if ($plain === false) {
            throw new RuntimeException('Fichier du coffre altéré ou clé incorrecte.');
        }

        return $plain;
    }

    private static function ad(string $uuid, int $index, int $total): string
    {
        return $uuid.'|'.$index.'|'.$total;
    }
}
