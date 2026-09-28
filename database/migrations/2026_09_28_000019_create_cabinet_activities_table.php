<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabinet_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->timestamps();

            $table->unique(['cabinet_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_activities');
    }
};
