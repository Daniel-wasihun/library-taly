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
        // 1. Drop old book catalog and loans tables
        Schema::dropIfExists('book_loans');
        Schema::dropIfExists('books');

        // 2. Create physical library attendances table (matching LMS schema)
        Schema::create('library_attendances', function (Blueprint $table) {
            $table->id();
            $table->uuid('slug')->unique();
            $table->foreignId('library_id')->nullable()->constrained('libraries')->nullOnDelete();
            $table->enum('user_type', ['student', 'teacher', 'staff'])->default('student');
            $table->enum('gender', ['male', 'female'])->default('male');
            $table->unsignedInteger('visitor_count')->default(1);
            $table->timestamp('entry_at')->useCurrent();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gender', 'user_type', 'entry_at']);
            $table->index('library_id');
            $table->index('staff_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_attendances');
    }
};
