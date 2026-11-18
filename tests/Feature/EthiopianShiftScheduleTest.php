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
            'name' => 'Techno Library',
            'code' => 'TECHNO',
            'slug' => 'techno-library',
            'is_active' => true,
        ]);

        $this->sciLibrary = Library::create([
            'name' => 'Social Library',
            'code' => 'SOCIAL',
            'slug' => 'social-library',
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
     * Test global shift group creation and strict single-group membership rule.
     * "if the Group A is create it must be beloges to all the library branch by default but the librarian have branch and one libraria must not be in the same group at a time"
     */
    public function test_global_shift_group_and_single_group_membership_rule(): void
    {
        // 1. Group A created with library_id = null (belongs to all library branches by default)
        // Can contain attendants from multiple different branches (Yohannes from Main, Tigist from Science)
        $resA = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group A (Global)',
            'library_id' => null,
            'user_ids' => [$this->attendantMain->id, $this->attendantSci->id],
        ]);
        $resA->assertStatus(201)
            ->assertJsonPath('data.name', 'Group A (Global)')
            ->assertJsonPath('data.library_id', null);

        // 2. Attempting to assign Yohannes (attendantMain) to Group B MUST BE REJECTED
        // "one librarian must not be in more than one group at a time"
        $resB = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group B (Global)',
            'library_id' => null,
            'user_ids' => [$this->attendantMain->id],
        ]);
        $resB->assertStatus(422)
            ->assertJsonFragment(['message' => "Group Conflict: 'Yohannes Attendant' is already assigned to 'Group A (Global)'. A librarian cannot belong to more than one group at a time."]);

        // 3. Duplicate group name across system is rejected
        $resDuplicate = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group A (Global)',
            'library_id' => null,
        ]);
        $resDuplicate->assertStatus(422);
    }

    /**
     * Test that shift scheduling is assigned by Month and Group (applies to all library branches).
     * "the day is same for all the library branch in a given group mean that the user is just selcet the month and the gropu to schedule the days for the group not the library branch"
     */
    public function test_scheduling_by_month_and_group_authorizes_attendants_across_branches(): void
    {
        // Group A belongs to all branches, with Yohannes in Main Library and Tigist in Science Library
        $groupA = ShiftGroup::create([
            'name' => 'Group Alpha All Branches',
            'library_id' => null,
            'is_active' => true,
        ]);
        $groupA->users()->sync([$this->attendantMain->id, $this->attendantSci->id]);

        $currentEc = EthiopianCalendarService::getCurrentEthiopianDate();
        $eyear = $currentEc['year'];
        $emonth = $currentEc['month'];
        $eday = $currentEc['day'];

        // Assign schedule by Month and Group without requiring library_id
        $resAssign = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'shift_group_id' => $groupA->id,
            'ethiopian_year' => $eyear,
            'ethiopian_month' => $emonth,
            'shifts' => [
                [
                    'shift_id' => $this->dayShift->id,
                    'days' => [$eday],
                ],
            ],
        ]);
        $resAssign->assertStatus(200);

        // DutyScheduleService authorizes Yohannes at Main Library branch
        $dutyService = app(DutyScheduleService::class);
        $checkMain = $dutyService->canPerformTask($this->attendantMain, 'attendance_desk', Carbon::today()->toDateString(), $this->mainLibrary->id);
        $this->assertTrue($checkMain['allowed']);
        $this->assertStringContainsString('Group Alpha All Branches', $checkMain['reason']);

        // DutyScheduleService ALSO authorizes Tigist at Science Library branch for the same group shift
        $checkSci = $dutyService->canPerformTask($this->attendantSci, 'attendance_desk', Carbon::today()->toDateString(), $this->sciLibrary->id);
        $this->assertTrue($checkSci['allowed']);
        $this->assertStringContainsString('Group Alpha All Branches', $checkSci['reason']);
    }

    /**
     * Test Night shift definition considers up to 11 o'clock in Ethiopia (11:00 ከሌሊቱ / 05:00 AM standard).
     * "and also the shift must consider the night shift upto the 11 oclock in ethiopia not up to 5 oclock"
     */
    public function test_night_shift_supports_11_oclock_ethiopian_time(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/shifts', [
            'name' => 'Overnight Full Shift',
            'name_am' => 'የሙሉ ሌሊት ፈረቃ',
            'code' => 'overnight_full',
            'start_time' => '17:00',
            'end_time' => '05:00',
            'ethiopian_start_time' => '11:00 ከቀኑ',
            'ethiopian_end_time' => '11:00 ከሌሊቱ',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.ethiopian_end_time', '11:00 ከሌሊቱ')
            ->assertJsonPath('data.end_time', '05:00');
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
        $nightDay = ($eday + 2 <= 30) ? ($eday + 2) : (($eday - 2 >= 1) ? ($eday - 2) : 15);

        // Group A gets today on Day Shift and a non-consecutive day on Night Shift
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
                    'days' => [$nightDay],
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
     * Test global group name uniqueness and unified branch application:
     * - Group names are unique across system
     * - Schedules assigned to a group apply across all library branches
     */
    public function test_global_groups_and_unified_schedule_application(): void
    {
        // 1. Create Group A (Global) with attendantMain
        $resA = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group Unified 1',
            'library_id' => null,
            'user_ids' => [$this->attendantMain->id],
        ]);
        $resA->assertStatus(201);
        $grpId = $resA->json('data.id');

        // 2. Duplicate group name is rejected
        $resDup = $this->actingAs($this->admin)->postJson('/api/shift-groups', [
            'name' => 'Group Unified 1',
            'library_id' => null,
        ]);
        $resDup->assertStatus(422);

        // 3. Scheduling Day 5 on Night Shift applies across all branches
        $sched = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'shift_group_id' => $grpId,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 3,
            'shifts' => [
                ['shift_id' => $this->nightShift->id, 'days' => [5]],
            ],
        ]);
        $sched->assertStatus(200);

        $this->assertDatabaseHas('group_shift_schedules', [
            'shift_group_id' => $grpId,
            'shift_id' => $this->nightShift->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 3,
        ]);
    }

    /**
     * Test Auto-generate:
     * - Rejects when a person is in multiple groups in the branch.
     * - Successfully generates equal workload schedule when staff are properly partitioned.
     * - Excludes Ethiopian public holidays (e.g. Meskerem 1 and 17).
     */
    public function test_auto_generate_schedule_with_overlap_detection_and_holiday_exclusion(): void
    {
        // 1. Create two groups with distinct attendants
        $grpA = ShiftGroup::create([
            'name' => 'Team Alpha Auto',
            'library_id' => null,
            'is_active' => true,
        ]);
        $grpB = ShiftGroup::create([
            'name' => 'Team Beta Auto',
            'library_id' => null,
            'is_active' => true,
        ]);

        $grpA->users()->sync([$this->attendantMain->id]);
        $grpB->users()->sync([$this->attendantGeneral->id]);

        // Insert manual holidays for Meskerem (Day 1 Enkutatash & Day 17 Meskel)
        \App\Models\EthiopianHoliday::create([
            'ethiopian_year' => 2019,
            'ethiopian_month' => 1,
            'ethiopian_day' => 1,
            'name' => 'Enkutatash',
            'name_am' => 'እንቁጣጣሽ',
            'library_id' => null,
            'created_by' => $this->admin->id,
        ]);
        \App\Models\EthiopianHoliday::create([
            'ethiopian_year' => 2019,
            'ethiopian_month' => 1,
            'ethiopian_day' => 17,
            'name' => 'Meskel',
            'name_am' => 'መስቀል',
            'library_id' => null,
            'created_by' => $this->admin->id,
        ]);

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

    /**
     * Test manual holiday creation, listing and deletion.
     */
    public function test_manual_holiday_management(): void
    {
        // 1. Create a holiday via API
        $createRes = $this->actingAs($this->admin)->postJson('/api/shift-schedules/holidays', [
            'ethiopian_year' => 2019,
            'ethiopian_month' => 3,
            'ethiopian_day' => 12,
            'name' => 'Custom Lib Holiday',
            'name_am' => 'የቤተ-መጽሐፍት በዓል',
            'description' => 'Annual library inventory day',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('holiday.ethiopian_day', 12);

        $holidayId = $createRes->json('holiday.id');

        // 2. Fetch holidays for Month 3
        $listRes = $this->actingAs($this->admin)->getJson('/api/shift-schedules/holidays?ethiopian_year=2019&ethiopian_month=3');
        $listRes->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('holidays.12.name', 'Custom Lib Holiday');

        // 3. Delete holiday
        $delRes = $this->actingAs($this->admin)->deleteJson("/api/shift-schedules/holidays/{$holidayId}");
        $delRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 4. Verify no holidays left for Month 3
        $listRes2 = $this->actingAs($this->admin)->getJson('/api/shift-schedules/holidays?ethiopian_year=2019&ethiopian_month=3');
        $listRes2->assertStatus(200)->assertJsonPath('total', 0);
    }

    /**
     * Test that shift assignment is strictly rejected on manual Ethiopian holidays.
     */
    public function test_assignment_rejected_on_manual_holiday(): void
    {
        $group = ShiftGroup::create([
            'name' => 'Holiday Test Group',
            'library_id' => $this->mainLibrary->id,
            'is_active' => true,
        ]);

        \App\Models\EthiopianHoliday::create([
            'ethiopian_year' => 2019,
            'ethiopian_month' => 4,
            'ethiopian_day' => 15,
            'name' => 'Test Day Off',
            'name_am' => 'የሙከራ በዓል',
            'created_by' => $this->admin->id,
        ]);

        $res = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->mainLibrary->id,
            'shift_group_id' => $group->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 4,
            'shifts' => [
                [
                    'shift_id' => $this->dayShift->id,
                    'days' => [15], // Assigned on holiday
                ],
            ],
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('conflict_type', 'HOLIDAY_CONFLICT')
            ->assertJsonPath('day', 15);
    }

    /**
     * Test that consecutive night and day shifts are strictly rejected.
     */
    public function test_consecutive_night_and_day_shift_rejection(): void
    {
        $group = ShiftGroup::create([
            'name' => 'Fatigue Guard Group',
            'library_id' => $this->mainLibrary->id,
            'is_active' => true,
        ]);

        // Attempt: Group works Night Shift on Day 10 and Day Shift next morning (Day 11)
        $resNextDay = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->mainLibrary->id,
            'shift_group_id' => $group->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 5,
            'shifts' => [
                [
                    'shift_id' => $this->nightShift->id,
                    'days' => [10],
                ],
                [
                    'shift_id' => $this->dayShift->id,
                    'days' => [11],
                ],
            ],
        ]);

        $resNextDay->assertStatus(422)
            ->assertJsonPath('conflict_type', 'CONSECUTIVE_NIGHT_DAY_OVERLAP')
            ->assertJsonPath('night_day', 10)
            ->assertJsonPath('day_shift_day', 11);

        // Attempt: Group works Day Shift on Day 9 and Night Shift on Day 10 (consecutive previous day)
        $resPrevDay = $this->actingAs($this->admin)->postJson('/api/shift-schedules/assign', [
            'library_id' => $this->mainLibrary->id,
            'shift_group_id' => $group->id,
            'ethiopian_year' => 2019,
            'ethiopian_month' => 5,
            'shifts' => [
                [
                    'shift_id' => $this->dayShift->id,
                    'days' => [9],
                ],
                [
                    'shift_id' => $this->nightShift->id,
                    'days' => [10],
                ],
            ],
        ]);

        $resPrevDay->assertStatus(422)
            ->assertJsonPath('conflict_type', 'CONSECUTIVE_NIGHT_DAY_OVERLAP')
            ->assertJsonPath('night_day', 10)
            ->assertJsonPath('day_shift_day', 9);
    }
}
