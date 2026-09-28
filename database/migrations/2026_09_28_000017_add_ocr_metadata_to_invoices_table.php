<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('vat_rate', 8, 3)->nullable();
            $table->decimal('fodec_rate', 8, 3)->nullable();
            $table->json('ocr_response')->nullable();
            $table->json('ocr_data')->nullable();
            $table->json('ocr_usage')->nullable();
            $table->json('ocr_warnings')->nullable();
            $table->string('ocr_model', 100)->nullable();
            $table->unsignedSmallInteger('ocr_attempts')->default(0);
            $table->timestamp('ocr_started_at')->nullable();
            $table->timestamp('ocr_completed_at')->nullable();
            $table->timestamp('ocr_failed_at')->nullable();
            $table->string('ocr_error_code', 80)->nullable();
            $table->string('ocr_error_message', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'vat_rate',
                'fodec_rate',
                'ocr_response',
                'ocr_data',
                'ocr_usage',
                'ocr_warnings',
                'ocr_model',
                'ocr_attempts',
                'ocr_started_at',
                'ocr_completed_at',
                'ocr_failed_at',
                'ocr_error_code',
                'ocr_error_message',
            ]);
        });
    }
};
