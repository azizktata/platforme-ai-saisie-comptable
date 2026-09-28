<?php

namespace Database\Seeders;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $cabinet = Cabinet::updateOrCreate(
            ['slug' => 'demo-cabinet'],
            ['name' => env('DEMO_CABINET_NAME', 'Cabinet de démonstration')],
        );

        $user = User::updateOrCreate(
            ['email' => env('DEMO_USER_EMAIL', 'demo@example.test')],
            [
                'cabinet_id' => $cabinet->id,
                'cabinet_role' => User::CABINET_ROLE_ADMIN,
                'name' => 'Administrateur Démo',
                'password' => env('DEMO_USER_PASSWORD', 'password'),
            ],
        );

        if ($user->cabinet_id !== $cabinet->id || ! $user->isCabinetAdmin()) {
            $user->update([
                'cabinet_id' => $cabinet->id,
                'cabinet_role' => User::CABINET_ROLE_ADMIN,
            ]);
        }

        foreach ([
            [
                'name' => 'Atlas Informatique',
                'legal_name' => 'Atlas Informatique SARL',
                'tax_identifier' => '1234567A',
                'activity' => 'Vente et services informatiques',
                'sector' => 'Technologies',
            ],
            [
                'name' => 'Bureau El Amen',
                'legal_name' => 'Bureau El Amen SARL',
                'tax_identifier' => '7654321B',
                'activity' => 'Services professionnels',
                'sector' => 'Conseil',
            ],
            [
                'name' => 'Carthage Négoce',
                'legal_name' => 'Carthage Négoce SARL',
                'tax_identifier' => '2345678C',
                'activity' => 'Commerce de gros',
                'sector' => 'Commerce',
            ],
        ] as $companyData) {
            Company::updateOrCreate(
                ['cabinet_id' => $cabinet->id, 'name' => $companyData['name']],
                $companyData + ['country_code' => 'TN', 'currency' => 'TND'],
            );
        }

        $this->call(AccountingDataSeeder::class);
    }
}
