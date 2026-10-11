<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $description = "Crée un utilisateur, ou met à jour son mot de passe.";

    protected $signature = 'reseau:user {email? : Adresse e-mail} {--name= : Nom affiché} {--password= : Mot de passe (déconseillé : visible dans l\'historique du terminal)}';

    public function handle(): int
    {
        $email = $this->argument('email') ?: text('Adresse e-mail', required: true);
        $password = $this->option('password') ?: password('Mot de passe (10 caractères minimum)', required: true);

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'min:10', 'max:255']],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->option('name') ?: ($user->name ?: strstr($email, '@', true));
        $user->password = $password; // haché par le cast « hashed »
        $user->save();

        $this->info($user->wasRecentlyCreated ? "Utilisateur $email créé." : "Mot de passe de $email mis à jour.");

        return self::SUCCESS;
    }
}
