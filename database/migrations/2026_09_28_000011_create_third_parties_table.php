<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_parties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('sage_identifier', 120)->nullable();
            $table->string('party_type', 30);
            $table->string('name');
            $table->string('tax_identifier', 80)->nullable();
            $table->foreignId('payables_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->foreignId('receivables_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'tax_identifier']);
            $table->index(['company_id', 'party_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_parties');
    }
};
