<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_proposal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accounting_proposal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('chart_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('analytical_account_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('debit', 18, 3)->default(0);
            $table->decimal('credit', 18, 3)->default(0);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['accounting_proposal_id', 'line_number'],
                'proposal_lines_proposal_line_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_proposal_lines');
    }
};
