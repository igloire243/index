<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\DossierFile;
use App\Models\Secret;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegarde chiffrée du coffre (dossiers et mots de passe), téléchargeable en un clic, indépendante du FTP.
 *
 * Format du fichier produit (extension .enc) :
 *     "RSXP2"  (5 octets, identifie le format)
 *     sel      (16 octets, pour dériver la clé du mot de passe)
 *     vecteur  (12 octets, AES-256-GCM)
 *     [zip chiffré, avec son étiquette d'authentification de 16 octets accolée à la fin]
 *
 * AES-256-GCM plutôt que le chiffrement déjà utilisé ailleurs dans le Coffre (XChaCha20-Poly1305/sodium) : c'est
 * ce que `window.crypto.subtle` sait faire nativement, sans bibliothèque. L'outil de déchiffrement
 * (`dechiffrer.html`, servi à la racine publique) ouvre ainsi ce fichier dans n'importe quel navigateur, même hors
 * ligne, sans installer PHP ni rien d'autre.
 *
 * La clé est dérivée (PBKDF2-SHA256, 310 000 tours — le nombre recommandé par l'OWASP) du mot de passe choisi par
 * l'utilisateur : ce mot de passe est à LUI, indépendant du mot de passe du compte et de APP_KEY — à garder
 * précieusement, il ne se retrouve nulle part sur le serveur.
 */
class VaultExport
{
    public const MAGIC = 'RSXP2';

    private const PBKDF2_ITERATIONS = 310_000;

    // Au-delà, l'export tiendrait difficilement en mémoire sur un hébergement mutualisé : mieux vaut une
    // sauvegarde FTP classique pour un coffre aussi volumineux.
    public const MAX_BYTES = 750 * 1024 * 1024;

    public function __construct(private readonly VaultFiles $vault) {}

    /** Taille totale (fichiers prêts uniquement) que l'export va devoir charger en mémoire. */
    public function estimatedBytes(int $userId): int
    {
        return (int) DossierFile::where('user_id', $userId)->where('status', 'ready')->sum('size');
    }

    /**
     * Construit l'archive chiffrée pour cet utilisateur et la renvoie en une seule chaîne.
     *
     * @param  Collection<int, Dossier>  $dossiers
     * @param  Collection<int, Secret>  $secrets
     */
    public function build($dossiers, $secrets, string $password): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'rsxp');
        if ($zipPath === false) {
            throw new RuntimeException('Impossible de créer un fichier temporaire.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Impossible de créer l’archive.');
            }

            $zip->addFromString('LISEZ-MOI.txt', $this->readme());
            $zip->addFromString('dossiers.json', json_encode($this->dossiersPayload($dossiers), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->addFromString('mots-de-passe.json', json_encode($this->secretsPayload($secrets), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            foreach ($dossiers as $dossier) {
                foreach ($dossier->files as $file) {
                    $this->addFileToZip($zip, $dossier, $file);
                }
            }

            $zip->close();

            $plain = file_get_contents($zipPath);
            if ($plain === false) {
                throw new RuntimeException('Impossible de lire l’archive.');
            }

            return $this->encrypt($plain, $password);
        } finally {
            @unlink($zipPath);
        }
    }

    private function addFileToZip(ZipArchive $zip, $dossier, DossierFile $file): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rsxf');
        $handle = fopen($tmp, 'wb');
        foreach ($this->vault->readRange($file, 0, $file->size - 1) as $piece) {
            fwrite($handle, $piece);
        }
        fclose($handle);

        $folder = $this->slug($dossier->id.'-'.$dossier->title);
        $zip->addFile($tmp, "dossiers/$folder/{$file->id}-{$file->name}");
        // ZipArchive ne lit le fichier qu'à la fermeture de l'archive : on ne peut pas le supprimer tout de suite.
        register_shutdown_function(static fn () => @unlink($tmp));
    }

    private function dossiersPayload($dossiers): array
    {
        return $dossiers->map(fn ($d) => [
            'titre' => $d->title,
            'sujet' => $d->subject,
            'resume' => $d->summary,
            'fichiers' => $d->files->map(fn ($f) => ['nom' => $f->name, 'type' => $f->mime, 'taille' => $f->size, 'note' => $f->caption])->values(),
        ])->values()->all();
    }

    private function secretsPayload($secrets): array
    {
        return $secrets->map(fn ($s) => [
            'type' => $s->type,
            'titre' => $s->payload['title'] ?? '',
            'identifiant' => $s->payload['username'] ?? null,
            'secret' => $s->payload['secret'] ?? null,
            'adresse' => $s->payload['url'] ?? null,
            'notes' => $s->payload['notes'] ?? null,
            'champs' => $s->payload['fields'] ?? [],
        ])->values()->all();
    }

    private function readme(): string
    {
        return "Sauvegarde du Coffre Réseau.\n\n".
            "dossiers.json : titres, résumés et liste des fichiers (dans dossiers/<dossier>/).\n".
            "mots-de-passe.json : toutes tes fiches, en clair.\n\n".
            "Cette archive n'est plus protégée une fois ouverte : garde-la dans un endroit sûr, et supprime-la\n".
            "après l'avoir mise en lieu sûr si tu l'as extraite sur un appareil partagé.\n";
    }

    private function encrypt(string $plain, string $password): string
    {
        $salt = random_bytes(16);
        $key = hash_pbkdf2('sha256', $password, $salt, self::PBKDF2_ITERATIONS, 32, true);
        $iv = random_bytes(12);

        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Le chiffrement de l’export a échoué.');
        }

        // L'étiquette accolée à la fin, pas séparée : c'est ce que `crypto.subtle.decrypt` (AES-GCM) attend côté navigateur.
        return self::MAGIC.$salt.$iv.$cipher.$tag;
    }

    private function slug(string $s): string
    {
        $s = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $s) ?? '', '-'));

        return $s === '' ? 'dossier' : substr($s, 0, 60);
    }
}
