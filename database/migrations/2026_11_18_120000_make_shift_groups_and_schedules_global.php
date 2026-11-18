<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Groups are unified across all library branches by default.
     * Shift scheduling is per Month & Group (same across all library branches).
     * One librarian cannot belong to more than one group at a time.
     */
    public function up(): void
    {
        // 1. Clean up duplicate group schedules and duplicate branch groups before altering constraints
        // In previous per-branch scheme, there were multiple "Group A" per branch.
        // We will consolidate shift_groups by name.
        DB::statement('DELETE FROM group_shift_schedules');
        DB::statement('DELETE FROM shift_group_user');
        DB::statement('DELETE FROM shift_groups');

        // 2. Make shift_groups.library_id nullable (null = all branches by default)
        Schema::table('shift_groups', function (Blueprint $table) {
            $table->foreignId('library_id')->nullable()->change();
            $table->unique('name', 'shift_groups_name_unique');
        });

        // 3. Ensure one user cannot belong to multiple groups at the same time
        Schema::table('shift_group_user', function (Blueprint $table) {
            $table->unique('user_id', 'shift_group_user_user_id_unique');
        });

        // 4. Make group_shift_schedules.library_id nullable and update unique schedule key
        Schema::table('group_shift_schedules', function (Blueprint $table) {
            $table->foreignId('library_id')->nullable()->change();
            $table->dropUnique('unique_group_shift_schedule');
            $table->unique(
                ['shift_group_id', 'shift_id', 'ethiopian_year', 'ethiopian_month'],
                'unique_global_group_shift_schedule'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_shift_schedules', function (Blueprint $table) {
            $table->dropUnique('unique_global_group_shift_schedule');
            $table->unique(
                ['library_id', 'shift_group_id', 'shift_id', 'ethiopian_year', 'ethiopian_month'],
                'unique_group_shift_schedule'
            );
        });

        Schema::table('shift_group_user', function (Blueprint $table) {
            $table->dropUnique('shift_group_user_user_id_unique');
        });

        Schema::table('shift_groups', function (Blueprint $table) {
            $table->dropUnique('shift_groups_name_unique');
        });
    }
};
