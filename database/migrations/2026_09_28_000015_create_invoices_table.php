<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('storage_disk', 50)->default('local');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('file_sha256', 64);
            $table->string('supplier_name')->nullable();
            $table->string('supplier_tax_identifier', 80)->nullable();
            $table->text('supplier_address')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_tax_identifier', 80)->nullable();
            $table->string('invoice_number', 120)->nullable();
            $table->string('purchase_order_reference', 120)->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('subtotal', 18, 3)->nullable();
            $table->decimal('vat_amount', 18, 3)->nullable();
            $table->decimal('fodec_amount', 18, 3)->nullable();
            $table->decimal('other_tax_amount', 18, 3)->nullable();
            $table->decimal('stamp_amount', 18, 3)->nullable();
            $table->decimal('withholding_rate', 8, 3)->nullable();
            $table->decimal('withholding_amount', 18, 3)->nullable();
            $table->decimal('total_amount', 18, 3)->nullable();
            $table->text('payment_terms')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_reference', 190)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('uploaded');
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'file_sha256']);
            $table->index(['company_id', 'invoice_number']);
            $table->index(['company_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
