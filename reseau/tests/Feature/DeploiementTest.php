<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploiementTest extends TestCase
{
    private string $racine;

    private string $depot;

    private string $dest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->racine = sys_get_temp_dir().'/deploiement-'.uniqid();
        $this->depot = $this->racine.'/depot';
        $this->dest = $this->racine.'/app';
        File::ensureDirectoryExists($this->depot);
        File::ensureDirectoryExists($this->dest);
        config(['deploiement.depot' => $this->depot, 'deploiement.destination' => $this->dest]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->racine);
        parent::tearDown();
    }

    public function test_nothing_happens_without_a_deposited_version(): void
    {
        $this->artisan('deploiement:finaliser')->assertSuccessful();
        $this->assertFileDoesNotExist($this->dest.'/storage/app/version-traitee');
    }

    public function test_a_new_version_is_copied_then_marked_as_processed_once(): void
    {
        File::put($this->depot.'/VERSION', 'abc123 run=1 migrer=1');
        File::put($this->depot.'/fichier.txt', 'nouveau');
        File::put($this->dest.'/.env', 'GARDE=moi');

        $this->artisan('deploiement:finaliser')->assertSuccessful()->expectsOutputToContain('Déploiement finalisé');

        $this->assertSame('nouveau', File::get($this->dest.'/fichier.txt'));
        $this->assertSame('GARDE=moi', File::get($this->dest.'/.env'), 'ce qui appartient au serveur ne bouge pas');
        $this->assertSame('abc123 run=1 migrer=1', File::get($this->dest.'/storage/app/version-traitee'));
        $this->assertDirectoryExists($this->dest.'/storage/framework/sessions');
        $this->assertDirectoryExists($this->dest.'/bootstrap/cache');

        // Deuxième passage avec la même version : rien à refaire.
        File::delete($this->dest.'/fichier.txt');
        $this->artisan('deploiement:finaliser')->assertSuccessful()->doesntExpectOutputToContain('Déploiement finalisé');
        $this->assertFileDoesNotExist($this->dest.'/fichier.txt');
    }

    public function test_a_later_version_is_picked_up(): void
    {
        File::put($this->depot.'/VERSION', 'v1');
        $this->artisan('deploiement:finaliser')->assertSuccessful();

        File::put($this->depot.'/VERSION', 'v2');
        File::put($this->depot.'/autre.txt', 'x');
        $this->artisan('deploiement:finaliser')->assertSuccessful()->expectsOutputToContain('v2');
        $this->assertSame('v2', File::get($this->dest.'/storage/app/version-traitee'));
        $this->assertFileExists($this->dest.'/autre.txt');
    }

    public function test_cron_and_bootstrap_scripts_refuse_the_web(): void
    {
        foreach (['cron.php', 'amorcage.php'] as $script) {
            $this->assertStringContainsString("PHP_SAPI !== 'cli'", File::get(base_path($script)));
        }
    }
}
