<?php

namespace App\Services;

use App\Models\DossierFile;
use Generator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Lecture et écriture des fichiers chiffrés du coffre sur le disque « vault ». */
class VaultFiles
{
    public function disk()
    {
        return Storage::disk('vault');
    }

    public function relativePath(DossierFile $file): string
    {
        return $file->user_id.'/'.$file->uuid.'.bin';
    }

    public function key(DossierFile $file): string
    {
        return base64_decode(Crypt::decryptString($file->encrypted_key), true);
    }

    public function encryptedKey(string $key): string
    {
        return Crypt::encryptString(base64_encode($key));
    }

    public function writeChunk(DossierFile $file, int $index, string $plain): void
    {
        $blob = VaultCrypto::encryptChunk($plain, $this->key($file), $file->uuid, $index, $file->total_chunks);
        $this->disk()->makeDirectory((string) $file->user_id);

        $handle = fopen($this->disk()->path($this->relativePath($file)), 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Écriture impossible dans le coffre.');
        }
        try {
            flock($handle, LOCK_EX);
            fseek($handle, $index * VaultCrypto::cipherChunkSize());
            fwrite($handle, $blob);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public function diskSize(DossierFile $file): int
    {
        $path = $this->relativePath($file);

        return $this->disk()->exists($path) ? $this->disk()->size($path) : 0;
    }

    /**
     * Texte en clair des octets $start à $end (inclus), morceau par morceau.
     *
     * @return Generator<string>
     */
    public function readRange(DossierFile $file, int $start, int $end): Generator
    {
        $key = $this->key($file);
        $handle = fopen($this->disk()->path($this->relativePath($file)), 'rb');
        if ($handle === false) {
            throw new RuntimeException('Fichier du coffre introuvable.');
        }

        try {
            $first = intdiv($start, VaultCrypto::CHUNK);
            $last = intdiv($end, VaultCrypto::CHUNK);
            for ($i = $first; $i <= $last; $i++) {
                fseek($handle, $i * VaultCrypto::cipherChunkSize());
                $blob = (string) fread($handle, VaultCrypto::NONCE + VaultCrypto::plainLength($file->size, $i) + VaultCrypto::TAG);
                $plain = VaultCrypto::decryptChunk($blob, $key, $file->uuid, $i, $file->total_chunks);

                $from = $i === $first ? $start - $i * VaultCrypto::CHUNK : 0;
                $to = $i === $last ? $end - $i * VaultCrypto::CHUNK : strlen($plain) - 1;
                yield substr($plain, $from, $to - $from + 1);
            }
        } finally {
            fclose($handle);
        }
    }

    public function delete(DossierFile $file): void
    {
        $this->disk()->delete($this->relativePath($file));
    }
}
