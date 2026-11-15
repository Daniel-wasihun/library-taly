<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\DutySchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutyScheduleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_login_with_username_or_email(): void
    {
        // Login with username
        $response = $this->postJson('/api/auth/login', [
            'login' => 'tigist_duty',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user' => ['username', 'roles']]);

        // Login with email
        $responseEmail = $this->postJson('/api/auth/login', [
            'login' => 'admin@unilib.edu',
            'password' => 'password123',
        ]);

        $responseEmail->assertStatus(200)
            ->assertJsonPath('user.username', 'admin');
    }

    public function test_on_duty_librarian_can_issue_book_today(): void
    {
        $onDutyUser = User::where('username', 'tigist_duty')->first();
        $book = Book::where('available_copies', '>', 0)->first();
        $student = User::where('username', 'sara_student')->first();

        $response = $this->actingAs($onDutyUser)
            ->postJson('/api/circulation/issue', [
                'book_id' => $book->id,
                'member_id' => $student->id,
                'due_days' => 14,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_off_duty_librarian_is_blocked_by_schedule_gate(): void
    {
        $offDutyUser = User::where('username', 'yohannes_offduty')->first();
        $book = Book::where('available_copies', '>', 0)->first();
        $student = User::where('username', 'sara_student')->first();

        $response = $this->actingAs($offDutyUser)
            ->postJson('/api/circulation/issue', [
                'book_id' => $book->id,
                'member_id' => $student->id,
                'due_days' => 14,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'OUTSIDE_DUTY_TURN')
            ->assertJsonStructure([
                'duty_gate' => [
                    'task',
                    'is_on_duty_today',
                    'next_turn',
                ],
            ]);
    }

    public function test_admin_has_override_privilege(): void
    {
        $admin = User::where('username', 'admin')->first();
        $book = Book::where('available_copies', '>', 0)->first();
        $student = User::where('username', 'sara_student')->first();

        $response = $this->actingAs($admin)
            ->postJson('/api/circulation/issue', [
                'book_id' => $book->id,
                'member_id' => $student->id,
                'due_days' => 14,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }
}
