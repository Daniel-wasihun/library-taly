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
        // Duty Schedules (Turns / Shifts for library tasks)
        Schema::create('duty_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('task_key'); // e.g., 'book_circulation', 'inventory_audit', 'fine_waiver', 'archive_access'
            $table->date('shift_date');
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('18:00:00');
            $table->string('status')->default('scheduled'); // scheduled, active, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'task_key', 'shift_date', 'status']);
        });

        // Books Table
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('isbn')->unique();
            $table->string('author');
            $table->string('category');
            $table->string('shelf_location')->nullable();
            $table->integer('total_copies')->default(1);
            $table->integer('available_copies')->default(1);
            $table->timestamps();
        });

        // Book Loans (Circulation: Issue & Return)
        Schema::create('book_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_date');
            $table->dateTime('due_date');
            $table->dateTime('returned_date')->nullable();
            $table->string('status')->default('borrowed'); // borrowed, returned, overdue
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['book_id', 'member_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_loans');
        Schema::dropIfExists('books');
        Schema::dropIfExists('duty_schedules');
    }
};
