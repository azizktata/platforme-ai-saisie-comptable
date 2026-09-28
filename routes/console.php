<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('about:product', function (): void {
    $this->comment('ComptaFlow — revue et comptabilisation de factures fournisseurs.');
})->purpose('Display a short description of this application');

Artisan::command('users:create', function (): int {
    $name = trim((string) $this->ask('Nom du compte'));
    $email = Str::lower(trim((string) $this->ask('Adresse e-mail')));
    $password = (string) $this->secret('Mot de passe (12 caractères minimum)');

    $validator = Validator::make(
        ['name' => $name, 'email' => $email, 'password' => $password],
        [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ],
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::create($validator->validated());

    $this->info("Compte créé pour {$user->email}.");

    return 0;
})->purpose('Create a user account from a secure interactive prompt');
