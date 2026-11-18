<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ethiopian_holidays', function (Blueprint $table) {
            $table->id();
            $table->integer('ethiopian_year')->index();
            $table->integer('ethiopian_month')->index();
            $table->integer('ethiopian_day')->index();
            $table->string('name'); // Holiday name e.g. 'Hidar Zion'
            $table->string('name_am')->nullable(); // e.g. 'ኅዳር ጽዮን'
            $table->text('description')->nullable();
            $table->foreignId('library_id')->nullable()->constrained('libraries')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ethiopian_year', 'ethiopian_month', 'ethiopian_day', 'library_id'], 'eth_holiday_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ethiopian_holidays');
    }
};
