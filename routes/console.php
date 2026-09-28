<?php

use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('about:product', function (): void {
    $this->comment('Plateforme de saisie comptable assistée — gestion multi-cabinet et multi-société.');
})->purpose('Display a short description of this application');

Artisan::command('cabinet:create', function (): int {
    $name = trim((string) $this->ask('Nom du cabinet'));
    $adminName = trim((string) $this->ask('Nom de l’administrateur'));
    $email = Str::lower(trim((string) $this->ask('Adresse e-mail de l’administrateur')));
    $password = (string) $this->secret('Mot de passe (12 caractères minimum)');

    $validator = Validator::make(
        ['name' => $name, 'admin_name' => $adminName, 'email' => $email, 'password' => $password],
        [
            'name' => ['required', 'string', 'max:160'],
            'admin_name' => ['required', 'string', 'max:190'],
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

    DB::transaction(function () use ($name, $adminName, $email, $password): void {
        $cabinet = Cabinet::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ]);

        $cabinet->users()->create([
            'name' => $adminName,
            'email' => $email,
            'password' => $password,
            'cabinet_role' => User::CABINET_ROLE_ADMIN,
        ]);
    });

    $this->info('Cabinet et compte administrateur créés.');

    return 0;
})->purpose('Create a cabinet and its first administrator');

Artisan::command('users:create {cabinetSlug}', function (): int {
    $cabinet = Cabinet::query()->where('slug', $this->argument('cabinetSlug'))->first();

    if (! $cabinet) {
        $this->error('Cabinet introuvable. Utilisez son slug, ou créez un cabinet avec cabinet:create.');

        return 1;
    }

    $name = trim((string) $this->ask('Nom du compte'));
    $email = Str::lower(trim((string) $this->ask('Adresse e-mail')));
    $password = (string) $this->secret('Mot de passe (12 caractères minimum)');
    $role = $this->choice('Rôle du compte', [User::CABINET_ROLE_MEMBER, User::CABINET_ROLE_ADMIN], 0);

    $validator = Validator::make(
        ['name' => $name, 'email' => $email, 'password' => $password],
        [
            'name' => ['required', 'string', 'max:190'],
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

    $cabinet->users()->create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'cabinet_role' => $role,
    ]);

    $this->info("Compte créé pour {$email}. Affectez-lui les sociétés depuis l’espace utilisateurs.");

    return 0;
})->purpose('Create a user in an existing cabinet');
