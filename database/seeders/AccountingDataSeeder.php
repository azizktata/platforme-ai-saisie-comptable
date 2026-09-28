<?php

namespace Database\Seeders;

use App\Models\Cabinet;
use App\Models\Company;
use App\Services\AccountingData\SeedDemoAccountingData;
use Illuminate\Database\Seeder;

class AccountingDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $cabinet = Cabinet::query()->where('slug', 'demo-cabinet')->first();

        if ($cabinet === null) {
            return;
        }

        $seedDemoAccountingData = app(SeedDemoAccountingData::class);

        Company::query()
            ->where('cabinet_id', $cabinet->id)
            ->each(fn (Company $company) => $seedDemoAccountingData->handle($company));
    }
}
