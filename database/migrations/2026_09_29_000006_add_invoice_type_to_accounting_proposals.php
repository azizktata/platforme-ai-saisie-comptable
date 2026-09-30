<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_proposals', function (Blueprint $table): void {
            $table->string('invoice_type', 40)->nullable()->after('journal_id');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_proposals', function (Blueprint $table): void {
            $table->dropColumn('invoice_type');
        });
    }
};
