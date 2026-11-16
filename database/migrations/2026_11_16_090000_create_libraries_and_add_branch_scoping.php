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
        // 1. Create Libraries Table
        Schema::create('libraries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_am')->nullable();
            $table->string('code', 10)->unique();
            $table->string('slug')->unique();
            $table->string('location')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Add library_id to users (null = General / All Branches)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('library_id')->nullable()->after('id')->constrained('libraries')->nullOnDelete();
            $table->index('library_id');
        });

        // 3. Add library_id to books
        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('library_id')->nullable()->after('id')->constrained('libraries')->nullOnDelete();
            $table->index('library_id');
        });

        // 4. Add library_id to duty_schedules
        Schema::table('duty_schedules', function (Blueprint $table) {
            $table->foreignId('library_id')->nullable()->after('user_id')->constrained('libraries')->nullOnDelete();
            $table->index(['library_id', 'shift_date']);
        });

        // 5. Add library_id to book_loans
        Schema::table('book_loans', function (Blueprint $table) {
            $table->foreignId('library_id')->nullable()->after('id')->constrained('libraries')->nullOnDelete();
            $table->index('library_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_loans', function (Blueprint $table) {
            $table->dropForeign(['library_id']);
            $table->dropColumn('library_id');
        });

        Schema::table('duty_schedules', function (Blueprint $table) {
            $table->dropForeign(['library_id']);
            $table->dropColumn('library_id');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign(['library_id']);
            $table->dropColumn('library_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['library_id']);
            $table->dropColumn('library_id');
        });

        Schema::dropIfExists('libraries');
    }
};
