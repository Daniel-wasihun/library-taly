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
        // Freeze time to 10:00 AM East Africa Time on Nov 18, 2026 (Hidar 9, 2019 E.C.)
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 10, 0, 0, 'Africa/Addis_Ababa'));
        $this->seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    public function test_four_groups_seeded_with_at_least_four_attendants_per_branch_and_sixteen_per_library(): void
    {
        $techno = Library::where('code', 'TECHNO')->first();
        $social = Library::where('code', 'SOCIAL')->first();
        $natural = Library::where('code', 'NATURAL')->first();

        $this->assertNotNull($techno);
        $this->assertNotNull($social);
        $this->assertNotNull($natural);

        // Minimum 16 attendants per branch
        $technoCount = User::role('Attendant')->where('library_id', $techno->id)->count();
        $socialCount = User::role('Attendant')->where('library_id', $social->id)->count();
        $naturalCount = User::role('Attendant')->where('library_id', $natural->id)->count();

        $this->assertGreaterThanOrEqual(16, $technoCount);
        $this->assertGreaterThanOrEqual(16, $socialCount);
        $this->assertGreaterThanOrEqual(16, $naturalCount);

        // 4 Groups seeded, each containing at least 4 from each library
        $groups = \App\Models\ShiftGroup::all();
        $this->assertGreaterThanOrEqual(4, $groups->count());

        foreach ($groups as $group) {
            $groupUsers = $group->users;
            $fromTechno = $groupUsers->where('library_id', $techno->id)->count();
            $fromSocial = $groupUsers->where('library_id', $social->id)->count();
            $fromNatural = $groupUsers->where('library_id', $natural->id)->count();

            $this->assertGreaterThanOrEqual(4, $fromTechno, "Group {$group->name} must have at least 4 attendants from Techno Library");
            $this->assertGreaterThanOrEqual(4, $fromSocial, "Group {$group->name} must have at least 4 attendants from Social Library");
            $this->assertGreaterThanOrEqual(4, $fromNatural, "Group {$group->name} must have at least 4 attendants from Natural Library");
        }
    }

    public function test_attendant_cannot_log_attendance_outside_shift_hours(): void
    {
        // On Nov 18, 2026 at 20:00 (8:00 PM), Group A's Day Shift (08:00 - 17:00) is closed
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 20, 0, 0, 'Africa/Addis_Ababa'));
        \Illuminate\Support\Facades\Cache::flush();

        $onDutyUser = User::where('username', 'yohannes_attendant')->first();

        $response = $this->actingAs($onDutyUser)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $onDutyUser->library_id,
                'user_type' => 'student',
                'gender' => 'male',
                'count' => 1,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'OUTSIDE_SHIFT_HOURS');
    }

    public function test_attendant_cannot_log_attendance_when_off_duty_day(): void
    {
        // techno_attendant_9 is in Group C, which has NO shift on Day 9 (Hidar 9)
        \Illuminate\Support\Facades\Cache::flush();
        $offDutyUser = User::where('username', 'techno_attendant_9')->first();

        $response = $this->actingAs($offDutyUser)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $offDutyUser->library_id,
                'user_type' => 'student',
                'gender' => 'male',
                'count' => 1,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'OUTSIDE_DUTY_TURN');
    }

    public function test_attendants_from_techno_social_and_natural_libraries_can_log_for_their_own_branch(): void
    {
        // At 10:00 AM on Day 9, Group A attendants in Techno, Social, and Natural are active
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 10, 0, 0, 'Africa/Addis_Ababa'));
        \Illuminate\Support\Facades\Cache::flush();

        $technoUser = User::where('username', 'yohannes_attendant')->first();
        $socialUser = User::where('username', 'social_attendant_1')->first();
        $naturalUser = User::where('username', 'natural_attendant_1')->first();

        // 1. Techno attendant logs for Techno
        $this->actingAs($technoUser)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $technoUser->library_id,
                'user_type' => 'student',
                'gender' => 'female',
                'count' => 2,
            ])
            ->assertStatus(201);

        // 2. Social attendant logs for Social
        $this->actingAs($socialUser)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $socialUser->library_id,
                'user_type' => 'teacher',
                'gender' => 'male',
                'count' => 1,
            ])
            ->assertStatus(201);

        // 3. Natural attendant logs for Natural
        $this->actingAs($naturalUser)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $naturalUser->library_id,
                'user_type' => 'staff',
                'gender' => 'female',
                'count' => 3,
            ])
            ->assertStatus(201);

        // 4. Social attendant CANNOT log for Natural Library
        $this->actingAs($socialUser)
            ->postJson('/api/physical/attendance/log', [
                'library_id' => $naturalUser->library_id,
                'user_type' => 'student',
                'gender' => 'male',
                'count' => 1,
            ])
            ->assertStatus(403);
    }

    public function test_attendant_cannot_access_dashboard_stats(): void
    {
        $attendant = User::where('username', 'yohannes_attendant')->first();

        // Attendant does not have dashboard.view permission
        $response = $this->actingAs($attendant)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Unauthorized: Dashboard view permission is required.');
    }

    public function test_admin_and_manager_can_access_dashboard_stats(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'stats' => ['today_total', 'total_branches'],
            ]);

        $manager = User::where('username', 'manager_haile')->first();
        if ($manager) {
            $this->actingAs($manager)
                ->getJson('/api/dashboard/stats')
                ->assertStatus(200);
        }
    }

    public function test_attendant_cannot_create_or_modify_libraries(): void
    {
        $attendant = User::where('username', 'yohannes_attendant')->first();
        $targetLib = Library::first();

        // Attendant cannot create branch
        $this->actingAs($attendant)
            ->postJson('/api/libraries', [
                'name' => 'Unauthorized Library',
                'code' => 'UNAUTH',
            ])
            ->assertStatus(403);

        // Attendant cannot update branch
        $this->actingAs($attendant)
            ->putJson("/api/libraries/{$targetLib->id}", [
                'name' => 'Updated Branch Name',
                'code' => $targetLib->code,
            ])
            ->assertStatus(403);

        // Attendant cannot delete branch
        $this->actingAs($attendant)
            ->deleteJson("/api/libraries/{$targetLib->id}")
            ->assertStatus(403);
    }

    public function test_backend_report_generation_print_and_export(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Official HTML report generation
        $responsePrint = $this->actingAs($admin)
            ->get('/api/physical/attendance/report/print?lang=en');

        $responsePrint->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Official Library Attendance and Demographic Analytics Report')
            ->assertSee('Patron Demographic Categorization');

        // 2. Official Amharic HTML report generation
        $responsePrintAm = $this->actingAs($admin)
            ->get('/api/physical/attendance/report/print?lang=am');

        $responsePrintAm->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('የቤተ-መጻሕፍት አጠቃቀም እና የስነ-ሕዝብ ትንተና ይፋዊ ሪፖርት')
            ->assertSee('1. ዋና ዋና የአፈጻጸም ጠቋሚዎች (KPIs)')
            ->assertSee('2. የተጠቃሚዎች ምድብና ስነ-ሕዝብ ዝርዝር')
            ->assertDontSee('(Print)')
            ->assertDontSee('(12hr)');

        // 3. CSV report export
        $responseExport = $this->actingAs($admin)
            ->get('/api/physical/attendance/export?lang=en');

        $responseExport->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // 4. Multi-format downloadable reports (xlsx, doc, csv, pdf)
        $responseXlsx = $this->actingAs($admin)
            ->get('/api/physical/attendance/report/download?format=xlsx&lang=en');
        $responseXlsx->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertSee('Workbook')
            ->assertSee('Worksheet');

        $responseDoc = $this->actingAs($admin)
            ->get('/api/physical/attendance/report/download?format=doc&lang=en');
        $responseDoc->assertStatus(200)
            ->assertHeader('Content-Type', 'application/msword; charset=UTF-8')
            ->assertSee('html')
            ->assertSee('WOLDIA UNIVERSITY')
            ->assertSee('Directorate of Academic Library');

        $responseCsv = $this->actingAs($admin)
            ->get('/api/physical/attendance/report/download?format=csv&lang=en');
        $responseCsv->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('Total Visitors');

        // Amharic download test
        $responseAmDoc = $this->actingAs($admin)
            ->get('/api/physical/attendance/report/download?format=doc&lang=am');
        $responseAmDoc->assertStatus(200)
            ->assertHeader('Content-Type', 'application/msword; charset=UTF-8')
            ->assertSee('የቤተ-መጻሕፍት አጠቃቀም እና የስነ-ሕዝብ ትንተና ይፋዊ ሪፖርት')
            ->assertSee('ወልድያ ዩኒቨርሲቲ');
    }
}
