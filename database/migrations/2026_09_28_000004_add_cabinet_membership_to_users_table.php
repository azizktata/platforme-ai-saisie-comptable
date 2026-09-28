<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('cabinet_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('cabinet_role', 40)->default('member');
            $table->index(['cabinet_id', 'cabinet_role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['cabinet_id', 'cabinet_role']);
            $table->dropConstrainedForeignId('cabinet_id');
            $table->dropColumn('cabinet_role');
        });
    }
};
