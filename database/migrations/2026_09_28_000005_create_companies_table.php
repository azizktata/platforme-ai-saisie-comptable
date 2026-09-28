<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_identifier', 80)->nullable();
            $table->string('activity')->nullable();
            $table->string('sector')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('sage_identifier')->nullable();
            $table->timestamps();

            $table->unique(['cabinet_id', 'name']);
            $table->index(['cabinet_id', 'tax_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
