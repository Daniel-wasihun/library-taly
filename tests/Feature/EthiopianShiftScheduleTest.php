<?php

namespace Tests\Feature;

use App\Models\GroupShiftSchedule;
use App\Models\Library;
use App\Models\Shift;
use App\Models\ShiftGroup;
use App\Models\User;
use App\Services\DutyScheduleService;
use App\Services\EthiopianCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EthiopianShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $attendantMain;
    protected User $attendantSci;
    protected User $attendantGeneral;
    protected Library $mainLibrary;
    protected Library $sciLibrary;
    protected Shift $dayShift;
    protected Shift $nightShift;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup roles & permissions
        $role = Role::create(['name' => 'Admin']);
        $attendantRole = Role::create(['name' => 'Attendant']);

        $permManage = Permission::create(['name' => 'libraries.manage']);
        $permAttend = Permission::create(['name' => 'attendance.record']);
        $role->syncPermissions([$permManage, $permAttend]);
        $attendantRole->syncPermissions([$permAttend]);

        // Libraries
        $this->mainLibrary = Library::create([
            'name' => 'Main Central Library',
            'code' => 'MAIN',
            'slug' => 'main-central-library',
            'is_active' => true,
        ]);

        $this->sciLibrary = Library::create([
            'name' => 'Science & Tech Library',
            'code' => 'SCITECH',
            'slug' => 'science-tech-library',
            'is_active' => true,
        ]);

        // Users
        $this->admin = User::create([
            'name' => 'Admin Dawit',
            'username' => 'admin_dawit',
            'email' => 'admin@unilib.edu',
            'password' => bcrypt('password123'),
            'library_id' => null,
        ]);
        $this->admin->assignRole('Admin');

        $this->attendantMain = User::create([
            'name' => 'Yohannes Attendant',
            'username' => 'yohannes_main',
            'email' => 'yohannes@unilib.edu',
            'password' => bcrypt('password123'),
            'library_id' => $this->mainLibrary->id,
        ]);
        $this->attendantMain->assignRole('Attendant');

        $this->attendantSci = User::create([
            'name' => 'Tigist Attendant',
            'username' => 'tigist_sci',
            'email' => 'tigist@unilib.edu',
            'password' => bcrypt('password123'),
            'library_id' => $this->sciLibrary->id,
        ]);
        $this->attendantSci->assignRole('Attendant');

        $this->attendantGeneral = User::create([
            'name' => 'Sara Floating',
            'username' => 'sara_general',
            'email' => 'sara@unilib.edu',
            'password' => bcrypt('password123'),
            'library_id' => null,
        ]);
        $this->attendantGeneral->assignRole('Attendant');

        // Shifts
        $this->dayShift = Shift::create([
            'name' => 'Day Shift',
            'name_am' => 'የቀን ፈረቃ',
            'code' => 'day',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'ethiopian_start_time' => '02:00 ጠዋት',
            'ethiopian_end_time' => '11:00 ከቀኑ',
            'is_active' => true,
        ]);

        $this->nightShift = Shift::create([
            'name' => 'Night Shift',
            'name_am' => 'የማታ ፈረቃ',
            'code' => 'night',
            'start_time' => '17:00',
            'end_time' => '23:00',
            'ethiopian_start_time' => '11:00 ከቀኑ',
            'ethiopian_end_time' => '05:00 ከምሽቱ',
            'is_active' => true,
        ]);
    }

    /**
     * Test Shift CRUD operations.
     */
    public function test_shift_crud_operations(): void
    {
        // 1. Create shift
        $res = $this->actingAs($this->admin)->postJson('/api/shifts', [
            'name' => 'Morning Early Shift',
            'name_am' => 'የማለዳ ፈረቃ',
            'code' => 'morning_early',
            'start_time' => '06:00',
            'end_time' => '12:00',
            'ethiopian_start_time' => '12:00 ጠዋት',
            'ethiopian_end_time' => '06:00 ከቀኑ',
        ]);
        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Morning Early Shift')
            ->assertJsonPath('data.ethiopian_start_time', '12:00 ጠዋት');

        $shiftId = $res->json('data.id');

        // 2. Update shift
        $updateRes = $this->actingAs($this->admin)->putJson("/api/shifts/{$shiftId}", [
            'name' => 'Early Dawn Shift',
            'start_time' => '06:30',
            'end_time' => '12:30',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Early Dawn Shift');

        // 3. Delete shift
        $delRes = $this->actingAs($this->admin)->deleteJson("/api/shifts/{$shiftId}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('shifts', ['id' => $shiftId]);
    }

    /**
     * Test Shift Group CRUD and branch compatibility validation rule.
     * "a group is created with specific branch that a user can be assigned to that group if they are same in library branch or if the group or librarian have general branch"
     */
    public function test_shift_group_branch_compatibility_validation(): void
    {
        // Branch match + general user: ALLOWED
        $res = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group A (Main)',
            'library_id' => $this->mainLibrary->id,
            'user_ids' => [$this->attendantMain->id, $this->attendantGeneral->id],
        ]);
        $res->assertStatus(201);
        $groupId = $res->json('data.id');

        // Different branch attendant (attendantSci belongs to SCITECH, not MAIN): REJECTED
        $conflictRes = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group Conflict',
            'library_id' => $this->mainLibrary->id,
            'user_ids' => [$this->attendantSci->id],
        ]);
        $conflictRes->assertStatus(422)
            ->assertJsonFragment(['message' => "Branch Conflict: The following staff members belong to other branches and cannot be assigned to 'Main Central Library': Tigist Attendant (Science & Tech Library). Only staff assigned to Main Central Library or General Branch may join this group."]);
    }

    /**
     * Test Rule 1: Intra-group cross-shift conflict.
     * "if the date is selected in one shift it must be disabled for another shif in the shchedule of the same scheduule assigning"
     */
    public function test_intra_group_cross_shift_conflict_is_rejected(): void
    {
        $groupA = ShiftGroup::create([
            'name' => 'Group A',
            'library_id' => $this->mainLibrary->id,
        ]);

        // Attempt to assign Day 3 to both Day Shift and Night Shift for Group A
        $res = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->mainLibrary->id,
            'shift_group_id' => $groupA->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 3,
            'shifts' => [
                [
                    'shift_id' => $this->dayShift->id,
                    'days' => [2, 3, 6],
                ],
                [
                    'shift_id' => $this->nightShift->id,
                    'days' => [3, 7, 9], // Day 3 conflicts!
                ],
            ],
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('conflict_type', 'INTRA_GROUP_OVERLAP')
            ->assertJsonPath('day', 3);
    }

    /**
     * Test Rule 2: Inter-group same-shift same-library conflict.
     * "and after that in that same library for other group shift date assigning fo rthe shif date selction ui the date that are belonges to another group in that same shif must be diabled also meaning the date that is selceted in night shift is 3 this means in group B the night shif date list the day 3 must be diabled not able to selctable like this"
     */
    public function test_inter_group_same_shift_conflict_is_rejected(): void
    {
        $groupA = ShiftGroup::create([
            'name' => 'Group A',
            'library_id' => $this->mainLibrary->id,
        ]);
        $groupB = ShiftGroup::create([
            'name' => 'Group B',
            'library_id' => $this->mainLibrary->id,
        ]);

        // 1. Group A schedules Day 3 on Night Shift
        $resA = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->mainLibrary->id,
            'shift_group_id' => $groupA->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 3,
            'shifts' => [
                [
                    'shift_id' => $this->nightShift->id,
                    'days' => [1, 3, 5],
                ],
            ],
        ]);
        $resA->assertStatus(200);

        // 2. Group B attempts to schedule Day 3 on Night Shift in the same library
        $resB = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->mainLibrary->id,
            'shift_group_id' => $groupB->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 3,
            'shifts' => [
                [
                    'shift_id' => $this->nightShift->id,
                    'days' => [3, 7, 9], // Day 3 is already taken by Group A!
                ],
            ],
        ]);

        $resB->assertStatus(422)
            ->assertJsonPath('conflict_type', 'INTER_GROUP_OVERLAP')
            ->assertJsonPath('day', 3)
            ->assertJsonPath('assigned_group', 'Group A');
    }

    /**
     * Test Valid Monthly Ethiopian Schedule Assignment & Duty Authorization.
     */
    public function test_valid_monthly_assignment_and_duty_authorization(): void
    {
        $groupA = ShiftGroup::create([
            'name' => 'Group A',
            'library_id' => $this->mainLibrary->id,
        ]);
        $groupA->users()->attach([$this->attendantMain->id]);

        $groupB = ShiftGroup::create([
            'name' => 'Group B',
            'library_id' => $this->mainLibrary->id,
        ]);

        // Today's Ethiopian Date
        $currentEc = EthiopianCalendarService::getCurrentEthiopianDate();
        $eyear = $currentEc['year'];
        $emonth = $currentEc['month'];
        $eday = $currentEc['day'];

        // Group A gets today on Day Shift
        $res = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->mainLibrary->id,
            'shift_group_id' => $groupA->id,
            'ethiopian_year' => $eyear,
            'ethiopian_month' => $emonth,
            'shifts' => [
                [
                    'shift_id' => $this->dayShift->id,
                    'days' => [$eday],
                ],
                [
                    'shift_id' => $this->nightShift->id,
                    'days' => [($eday % 30) + 1],
                ],
            ],
        ]);
        $res->assertStatus(200);

        // Verify Matrix Endpoint returns accurate conflicts
        $matrixRes = $this->actingAs($this->admin)->getJson("/api/shift-schedules/matrix?library_id={$this->mainLibrary->id}&ethiopian_year={$eyear}&ethiopian_month={$emonth}");
        $matrixRes->assertStatus(200)
            ->assertJsonPath("conflict_matrix.by_shift.{$this->dayShift->id}.{$eday}.group_name", 'Group A');

        // Verify DutyScheduleService authorizes attendantMain today
        $dutyService = app(DutyScheduleService::class);
        $check = $dutyService->canPerformTask($this->attendantMain, 'attendance_desk', Carbon::today()->toDateString(), $this->mainLibrary->id);
        $this->assertTrue($check['allowed']);
        $this->assertStringContainsString('Group A', $check['reason']);
    }

    /**
     * Test separate groups per library branch:
     * - Main Library and Science Library can both have "Group A"
     * - Attendant can belong to multiple groups at the same library
     * - Shift schedule for Main Library does not conflict with Science Library
     */
    public function test_separate_groups_per_library_and_multi_group_attendant(): void
    {
        // 1. Create Group A for Main Library with attendantMain
        $resMain = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group A',
            'library_id' => $this->mainLibrary->id,
            'user_ids' => [$this->attendantMain->id],
        ]);
        $resMain->assertStatus(201);
        $mainGroupId = $resMain->json('data.id');

        // 2. Create Group B for Main Library ALSO containing attendantMain (attendant in multiple groups in same library)
        $resMainB = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group B',
            'library_id' => $this->mainLibrary->id,
            'user_ids' => [$this->attendantMain->id],
        ]);
        $resMainB->assertStatus(201);
        $mainGroupBId = $resMainB->json('data.id');

        // Verify attendantMain belongs to both groups
        $this->assertDatabaseHas('shift_group_user', [
            'shift_group_id' => $mainGroupId,
            'user_id' => $this->attendantMain->id,
        ]);
        $this->assertDatabaseHas('shift_group_user', [
            'shift_group_id' => $mainGroupBId,
            'user_id' => $this->attendantMain->id,
        ]);

        // 3. Create Group A for Science Library (same name "Group A", separate library branch)
        $resSci = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group A',
            'library_id' => $this->sciLibrary->id,
            'user_ids' => [$this->attendantSci->id],
        ]);
        $resSci->assertStatus(201);
        $sciGroupId = $resSci->json('data.id');

        // 4. Duplicate name within the SAME library is rejected
        $resDuplicate = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group A',
            'library_id' => $this->mainLibrary->id,
        ]);
        $resDuplicate->assertStatus(422);

        // 5. Scheduling Day 5 on Night Shift in Main Library
        $schedMain = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->mainLibrary->id,
            'shift_group_id' => $mainGroupId,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 3,
            'shifts' => [
                ['shift_id' => $this->nightShift->id, 'days' => [5]],
            ],
        ]);
        $schedMain->assertStatus(200);

        // 6. Scheduling Day 5 on Night Shift in Science Library is completely allowed (separate library!)
        $schedSci = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->sciLibrary->id,
            'shift_group_id' => $sciGroupId,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 3,
            'shifts' => [
                ['shift_id' => $this->nightShift->id, 'days' => [5]],
            ],
        ]);
        $schedSci->assertStatus(200);
    }

    /**
     * Test Auto-generate:
     * - Rejects when a person is in multiple groups in the branch.
     * - Successfully generates equal workload schedule when staff are properly partitioned.
     * - Excludes Ethiopian public holidays (e.g. Meskerem 1 and 17).
     */
    public function test_auto_generate_schedule_with_overlap_detection_and_holiday_exclusion(): void
    {
        // 1. Create two groups in Main Library
        $grpA = ShiftGroup::create([
            'name' => 'Team Alpha',
            'library_id' => $this->mainLibrary->id,
        ]);
        $grpB = ShiftGroup::create([
            'name' => 'Team Beta',
            'library_id' => $this->mainLibrary->id,
        ]);

        // Place attendantMain in BOTH groups (violates the auto-scheduling single group constraint)
        $grpA->users()->attach([$this->attendantMain->id]);
        $grpB->users()->attach([$this->attendantMain->id]);

        // Attempt auto-generate -> MUST fail with staff overlap detection
        $resOverlap = $this->actingAs($this->admin)->postJson('/api/shift-schedules/auto-generate', [
            'library_id' => $this->mainLibrary->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 1, // Meskerem (contains Meskerem 1 Enkutatash & Meskerem 17 Meskel)
            'exclude_holidays' => true,
        ]);

        $resOverlap->assertStatus(422)
            ->assertJsonStructure(['errors' => ['staff_overlap']]);

        // 2. Resolve overlap: Assign attendantMain to Team Alpha only, attendantGeneral to Team Beta only
        $grpA->users()->sync([$this->attendantMain->id]);
        $grpB->users()->sync([$this->attendantGeneral->id]);

        // 3. Run auto-generate for Meskerem (Month 1) with holiday exclusion
        $resGen = $this->actingAs($this->admin)->postJson('/api/shift-schedules/auto-generate', [
            'library_id' => $this->mainLibrary->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 1,
            'exclude_holidays' => true,
        ]);

        $resGen->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_calendar_days', 30)
            ->assertJsonPath('working_days_scheduled', 28); // 30 minus Day 1 & Day 17 = 28 days!

        // Verify detected and excluded holidays
        $excludedHolidays = $resGen->json('excluded_holidays');
        $excludedDays = array_column($excludedHolidays, 'day');
        $this->assertContains(1, $excludedDays); // Enkutatash
        $this->assertContains(17, $excludedDays); // Meskel

        // 4. Verify in DB that Day 1 and Day 17 are completely removed / NOT scheduled for any group in this branch
        $schedules = GroupShiftSchedule::where('library_id', $this->mainLibrary->id)
            ->where('ethiopian_year', 2019)
            ->where('ethiopian_month', 1)
            ->get();

        foreach ($schedules as $sched) {
            $days = $sched->days ?? [];
            $this->assertNotContains(1, $days, 'Holiday Day 1 (Enkutatash) must not be scheduled');
            $this->assertNotContains(17, $days, 'Holiday Day 17 (Meskel) must not be scheduled');
        }

        // 5. Verify Equal Workload:
        // 28 working days with 2 shifts = 56 total shift slots across 2 groups -> 28 shifts per group!
        $workloadStats = $resGen->json('workload_stats');
        $this->assertCount(2, $workloadStats);
        $this->assertEquals(28, $workloadStats[0]['total_assigned_days']);
        $this->assertEquals(28, $workloadStats[1]['total_assigned_days']);
    }
}
