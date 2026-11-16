<?php

namespace Tests\Feature;

use App\Models\Library;
use App\Models\LibraryAttendance;
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
            'login' => 'yohannes_attendant',
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

    public function test_attendant_can_log_physical_attendance_for_their_branch(): void
    {
        $onDutyUser = User::where('username', 'yohannes_attendant')->first();

        $response = $this->actingAs($onDutyUser)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $onDutyUser->library_id,
                'user_type' => 'student',
                'gender' => 'female',
                'count' => 3,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 3)
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.user_type', 'student');
    }

    public function test_attendant_cannot_log_attendance_for_another_library_branch(): void
    {
        $onDutyUser = User::where('username', 'yohannes_attendant')->first();
        $anotherBranch = Library::where('id', '!=', $onDutyUser->library_id)->first();

        $response = $this->actingAs($onDutyUser)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $anotherBranch->id,
                'user_type' => 'student',
                'gender' => 'male',
                'count' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_log_attendance_for_any_branch(): void
    {
        $admin = User::where('username', 'admin')->first();
        $branch = Library::first();

        $response = $this->actingAs($admin)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $branch->id,
                'user_type' => 'teacher',
                'gender' => 'male',
                'count' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_attendance_stats_endpoint(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)
            ->getJson('/api/physical/attendance/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_users',
                'total_male',
                'total_female',
                'gender_distribution',
                'type_distribution',
                'library_distribution',
                'trends',
            ]);
    }

    public function test_attendant_cannot_access_analytics_and_stats(): void
    {
        $attendant = User::where('username', 'yohannes_attendant')->first();

        $response = $this->actingAs($attendant)
            ->getJson('/api/physical/attendance/stats');

        $response->assertStatus(403);
    }

    public function test_manager_can_access_analytics_and_stats(): void
    {
        $manager = User::where('username', 'kassahun_manager')->first();

        $response = $this->actingAs($manager)
            ->getJson('/api/physical/attendance/stats');

        $response->assertStatus(200);
    }

    public function test_2_minute_update_rule_enforcement_for_attendant(): void
    {
        $attendant = User::where('username', 'yohannes_attendant')->first();
        $branchId = $attendant->library_id;

        // Fresh attendance entry (created right now - within 2 minutes)
        $fresh = LibraryAttendance::create([
            'library_id' => $branchId,
            'user_type' => 'student',
            'gender' => 'male',
            'visitor_count' => 1,
            'staff_id' => $attendant->id,
            'entry_at' => now(),
        ]);

        // Attendant CAN update count within 2 minutes
        $response = $this->actingAs($attendant)
            ->patchJson("/api/physical/attendance/{$fresh->slug}/count", ['count' => 2]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.visitor_count', 2);

        // Old attendance entry (created 5 minutes ago)
        $old = LibraryAttendance::create([
            'library_id' => $branchId,
            'user_type' => 'student',
            'gender' => 'male',
            'visitor_count' => 1,
            'staff_id' => $attendant->id,
            'entry_at' => now()->subMinutes(5),
        ]);

        // Attendant CANNOT update count after 2 minutes
        $failResponse = $this->actingAs($attendant)
            ->patchJson("/api/physical/attendance/{$old->slug}/count", ['count' => 2]);

        $failResponse->assertStatus(403);

        // Admin CAN update count even after 2 minutes
        $admin = User::where('username', 'admin')->first();
        $adminResponse = $this->actingAs($admin)
            ->patchJson("/api/physical/attendance/{$old->slug}/count", ['count' => 5]);

        $adminResponse->assertStatus(200)
            ->assertJsonPath('data.visitor_count', 5);
    }

    public function test_2_minute_deletion_rule_enforcement(): void
    {
        $attendant = User::where('username', 'yohannes_attendant')->first();
        $branchId = $attendant->library_id;

        // Fresh attendance entry (created right now)
        $fresh = LibraryAttendance::create([
            'library_id' => $branchId,
            'user_type' => 'student',
            'gender' => 'male',
            'visitor_count' => 1,
            'staff_id' => $attendant->id,
            'entry_at' => now(),
        ]);

        // Attendant CAN delete fresh entry
        $response = $this->actingAs($attendant)
            ->deleteJson("/api/physical/attendance/{$fresh->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Expired attendance entry (created 5 minutes ago)
        $expired = LibraryAttendance::create([
            'library_id' => $branchId,
            'user_type' => 'student',
            'gender' => 'female',
            'visitor_count' => 1,
            'staff_id' => $attendant->id,
            'entry_at' => now()->subMinutes(5),
        ]);

        // Attendant CANNOT delete expired entry
        $failResponse = $this->actingAs($attendant)
            ->deleteJson("/api/physical/attendance/{$expired->slug}");

        $failResponse->assertStatus(403);

        // Admin CAN delete expired entry
        $admin = User::where('username', 'admin')->first();
        $adminResponse = $this->actingAs($admin)
            ->deleteJson("/api/physical/attendance/{$expired->slug}");

        $adminResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
