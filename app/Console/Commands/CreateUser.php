<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-user {--name= : Nom affiché} {--email= : Adresse e-mail de connexion}')]
#[Description('Crée un compte utilisateur (pas d\'inscription publique avant la Phase 6)')]
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

        $this->info("Compte créé : {$user->email}");

        return self::SUCCESS;
    }
}
