<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('supplier_phone', 50)->nullable();
            $table->string('supplier_mobile', 50)->nullable();
            $table->string('supplier_email')->nullable();
            $table->string('customer_reference', 120)->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->string('payment_method', 120)->nullable();
            $table->decimal('total_discount_amount', 18, 3)->nullable();
            $table->decimal('net_to_pay_amount', 18, 3)->nullable();
        });

        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->decimal('discount_amount', 18, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropColumn('discount_amount');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'supplier_phone',
                'supplier_mobile',
                'supplier_email',
                'customer_reference',
                'customer_address',
                'customer_phone',
                'payment_method',
                'total_discount_amount',
                'net_to_pay_amount',
            ]);
        });
    }
};
