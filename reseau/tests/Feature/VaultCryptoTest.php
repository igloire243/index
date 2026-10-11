<?php

namespace Tests\Feature;

use App\Models\DossierFile;
use App\Models\User;
use App\Services\VaultCrypto;
use App\Services\VaultFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class VaultCryptoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chunk_roundtrips_and_each_encryption_is_different(): void
    {
        $key = VaultCrypto::newKey();
        $a = VaultCrypto::encryptChunk('secret', $key, 'uuid-1', 0, 1);
        $b = VaultCrypto::encryptChunk('secret', $key, 'uuid-1', 0, 1);

        $this->assertNotSame($a, $b, 'nonce aléatoire : le même texte ne donne jamais le même chiffré');
        $this->assertStringNotContainsString('secret', $a);
        $this->assertSame('secret', VaultCrypto::decryptChunk($a, $key, 'uuid-1', 0, 1));
        $this->assertSame(strlen('secret') + VaultCrypto::NONCE + VaultCrypto::TAG, strlen($a));
    }

    public function test_tampering_reordering_and_wrong_context_are_detected(): void
    {
        $key = VaultCrypto::newKey();
        $blob = VaultCrypto::encryptChunk('contenu', $key, 'uuid-1', 2, 5);

        $this->expectExceptionOfDecrypt(fn () => VaultCrypto::decryptChunk($blob, VaultCrypto::newKey(), 'uuid-1', 2, 5));  // autre clé
        $this->expectExceptionOfDecrypt(fn () => VaultCrypto::decryptChunk($blob, $key, 'uuid-2', 2, 5));                  // autre fichier
        $this->expectExceptionOfDecrypt(fn () => VaultCrypto::decryptChunk($blob, $key, 'uuid-1', 3, 5));                  // morceau déplacé
        $this->expectExceptionOfDecrypt(fn () => VaultCrypto::decryptChunk($blob, $key, 'uuid-1', 2, 4));                  // fichier tronqué
        $flipped = $blob;
        $flipped[strlen($flipped) - 1] = $flipped[strlen($flipped) - 1] ^ "\x01";
        $this->expectExceptionOfDecrypt(fn () => VaultCrypto::decryptChunk($flipped, $key, 'uuid-1', 2, 5));              // un bit modifié
        $this->expectExceptionOfDecrypt(fn () => VaultCrypto::decryptChunk('court', $key, 'uuid-1', 2, 5));
    }

    private function expectExceptionOfDecrypt(callable $fn): void
    {
        try {
            $fn();
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Le déchiffrement aurait dû échouer.');
    }

    public function test_sizes(): void
    {
        $overhead = VaultCrypto::NONCE + VaultCrypto::TAG;

        $this->assertSame(1, VaultCrypto::totalChunks(1));
        $this->assertSame(1, VaultCrypto::totalChunks(VaultCrypto::CHUNK));
        $this->assertSame(2, VaultCrypto::totalChunks(VaultCrypto::CHUNK + 1));
        $this->assertSame(VaultCrypto::CHUNK, VaultCrypto::plainLength(2 * VaultCrypto::CHUNK + 10, 0));
        $this->assertSame(10, VaultCrypto::plainLength(2 * VaultCrypto::CHUNK + 10, 2));
        $this->assertSame(VaultCrypto::CHUNK, VaultCrypto::plainLength(VaultCrypto::CHUNK, 0));
        $this->assertSame(100 + $overhead, VaultCrypto::cipherSize(100));
        $this->assertSame(VaultCrypto::CHUNK + 5 + 2 * $overhead, VaultCrypto::cipherSize(VaultCrypto::CHUNK + 5));
    }

    public function test_random_access_reads_across_chunk_boundaries(): void
    {
        Storage::fake('vault');
        $user = User::factory()->create();
        $dossier = $user->dossiers()->create(['title' => 'T']);
        $service = app(VaultFiles::class);

        $plain = random_bytes(2 * VaultCrypto::CHUNK + 777);
        $key = VaultCrypto::newKey();
        $file = DossierFile::create([
            'dossier_id' => $dossier->id, 'user_id' => $user->id, 'uuid' => 'aaaaaaaa-0000-0000-0000-000000000001', 'name' => 'x', 'size' => strlen($plain),
            'total_chunks' => 3, 'received' => [0, 1, 2], 'encrypted_key' => $service->encryptedKey($key), 'status' => 'ready',
        ]);
        // On écrit les morceaux dans le désordre : chacun va à sa place.
        foreach ([2, 0, 1] as $i) {
            $service->writeChunk($file, $i, substr($plain, $i * VaultCrypto::CHUNK, VaultCrypto::plainLength(strlen($plain), $i)));
        }

        $this->assertSame(VaultCrypto::cipherSize(strlen($plain)), $service->diskSize($file));
        $read = fn (int $a, int $b) => implode('', iterator_to_array($service->readRange($file, $a, $b), false));
        $this->assertSame($plain, $read(0, strlen($plain) - 1));
        $this->assertSame(substr($plain, 5, 10), $read(5, 14));
        $this->assertSame(substr($plain, VaultCrypto::CHUNK - 3, 8), $read(VaultCrypto::CHUNK - 3, VaultCrypto::CHUNK + 4));
        $this->assertSame(substr($plain, -1), $read(strlen($plain) - 1, strlen($plain) - 1));
        $this->assertStringNotContainsString(substr($plain, 100, 64), Storage::disk('vault')->get($service->relativePath($file)), 'rien n’est lisible sur le disque');
    }
}
