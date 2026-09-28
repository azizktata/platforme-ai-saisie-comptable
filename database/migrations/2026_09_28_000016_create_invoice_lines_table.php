<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 18, 3)->nullable();
            $table->decimal('unit_price', 18, 3)->nullable();
            $table->decimal('subtotal', 18, 3)->nullable();
            $table->decimal('vat_rate', 8, 3)->nullable();
            $table->decimal('vat_amount', 18, 3)->nullable();
            $table->decimal('fodec_rate', 8, 3)->nullable();
            $table->decimal('fodec_amount', 18, 3)->nullable();
            $table->decimal('other_tax_amount', 18, 3)->nullable();
            $table->decimal('total_amount', 18, 3)->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
