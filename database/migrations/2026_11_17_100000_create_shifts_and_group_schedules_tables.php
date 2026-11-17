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
        // 1. Shifts definition table
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Day Shift', 'Night Shift'
            $table->string('name_am')->nullable(); // e.g., 'የቀን ፈረቃ', 'የማታ ፈረቃ'
            $table->string('code')->unique(); // e.g., 'day', 'night'
            $table->string('start_time')->default('08:00'); // 24-hr time e.g., '08:00'
            $table->string('end_time')->default('17:00'); // 24-hr time e.g., '17:00'
            $table->string('ethiopian_start_time')->default('02:00 ጠዋት'); // Ethiopian local time
            $table->string('ethiopian_end_time')->default('11:00 ከቀኑ');
            $table->text('description')->nullable();
            $table->foreignId('library_id')->nullable()->constrained('libraries')->nullOnDelete(); // null = all branches
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Shift Groups table (e.g., Group A, Group B, Group C, Group D)
        Schema::create('shift_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Group A', 'Group B'
            $table->foreignId('library_id')->nullable()->constrained('libraries')->nullOnDelete(); // null = General Branch
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['library_id', 'is_active']);
        });

        // 3. Shift Group Members (Attendants / Librarians assigned to group)
        Schema::create('shift_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_group_id')->constrained('shift_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shift_group_id', 'user_id']);
        });

        // 4. Monthly Ethiopian Calendar Group Shift Schedules
        Schema::create('group_shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('libraries')->cascadeOnDelete();
            $table->foreignId('shift_group_id')->constrained('shift_groups')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->unsignedSmallInteger('ethiopian_year'); // e.g., 2018 or 2019
            $table->unsignedTinyInteger('ethiopian_month'); // 1 to 13 (Meskerem to Pagume)
            $table->json('days'); // Array of integers [1, 2, 3 ... 30]
            $table->string('status')->default('active'); // active, archived
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['library_id', 'shift_group_id', 'shift_id', 'ethiopian_year', 'ethiopian_month'],
                'unique_group_shift_schedule'
            );
            $table->index(['library_id', 'ethiopian_year', 'ethiopian_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_shift_schedules');
        Schema::dropIfExists('shift_group_user');
        Schema::dropIfExists('shift_groups');
        Schema::dropIfExists('shifts');
    }
};
