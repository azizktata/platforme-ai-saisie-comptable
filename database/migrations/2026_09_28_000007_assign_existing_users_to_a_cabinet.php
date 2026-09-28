<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->whereNull('cabinet_id')
            ->get(['id', 'name']);

        foreach ($users as $user) {
            $slug = 'legacy-owner-'.$user->id;
            $cabinetId = DB::table('cabinets')->where('slug', $slug)->value('id');

            if ($cabinetId === null) {
                $now = now();
                $cabinetId = DB::table('cabinets')->insertGetId([
                    'name' => 'Cabinet de '.$user->name,
                    'slug' => $slug,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('users')->where('id', $user->id)->whereNull('cabinet_id')->update([
                'cabinet_id' => $cabinetId,
                'cabinet_role' => 'cabinet_admin',
            ]);
        }
    }

    public function down(): void
    {
        // Keep the user-to-cabinet assignments. The preceding schema migration
        // removes the cabinet columns during a full rollback; reversing them here
        // could merge or orphan tenant data during a partial rollback.
    }
};
