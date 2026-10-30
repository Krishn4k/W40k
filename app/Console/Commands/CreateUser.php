<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-user {--name= : Nom affiché} {--email= : Adresse e-mail de connexion} {--admin : Donne les droits d\'administration}')]
#[Description('Crée un compte utilisateur (les joueurs peuvent aussi être invités depuis l\'administration)')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $attributes = [
            'name' => $this->option('name') ?? text('Nom', required: true),
            'email' => $this->option('email') ?? text('Adresse e-mail', required: true),
            'password' => password('Mot de passe (8 caractères minimum)', required: true),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($validator->validated());
        $user->forceFill(['role' => $this->option('admin') ? UserRole::Admin : UserRole::Player, 'approved_at' => now()])->save();

        $this->info("Compte créé : {$user->email} ({$user->role->label()}).");

        return self::SUCCESS;
    }
}
