<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('chart_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('analytical_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('debit', 18, 3)->default(0);
            $table->decimal('credit', 18, 3)->default(0);
            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_number']);
            $table->index(['chart_account_id', 'journal_entry_id']);
            $table->index(['third_party_id', 'journal_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
