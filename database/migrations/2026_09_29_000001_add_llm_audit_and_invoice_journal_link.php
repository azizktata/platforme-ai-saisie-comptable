<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->longText('ocr_text')->nullable();
            $table->json('extraction_response')->nullable();
            $table->string('extraction_model', 100)->nullable();
            $table->json('extraction_usage')->nullable();
            $table->timestamp('extraction_corrected_at')->nullable();
            $table->foreignId('extraction_corrected_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropUnique('journal_entries_invoice_id_unique');
            $table->dropConstrainedForeignId('invoice_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'ocr_text',
                'extraction_response',
                'extraction_model',
                'extraction_usage',
            ]);
            $table->dropConstrainedForeignId('extraction_corrected_by');
            $table->dropColumn('extraction_corrected_at');
        });
    }
};
