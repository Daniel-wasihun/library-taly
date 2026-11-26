<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\AcademicYear;
use App\Models\GroupShiftSchedule;
use App\Models\Shift;
use App\Models\ShiftGroup;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure ONLY 2019 E.C. exists in academic_years table
        AcademicYear::where('code', '!=', 'AY-2019-EC')->delete();
        AcademicYear::updateOrCreate(
            ['code' => 'AY-2019-EC'],
            [
                'name' => '2019 E.C. (2026/2027 G.C.)',
                'start_date' => '2026-09-11',
                'end_date' => '2027-07-07',
                'is_current' => true,
                'description' => 'Current Active University Academic Year (2019 E.C.)',
            ]
        );

        // 2. Ensure Librarian & Attendant have update/delete attendance permissions
        $permissions = ['attendance.update', 'attendance.delete', 'attendance.record', 'attendance.view'];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $librarianRole = Role::where('name', 'Librarian')->first();
        if ($librarianRole) {
            $librarianRole->givePermissionTo(['attendance.update', 'attendance.delete', 'attendance.record', 'attendance.view']);
        }

        $attendantRole = Role::where('name', 'Attendant')->first();
        if ($attendantRole) {
            $attendantRole->givePermissionTo(['attendance.update', 'attendance.delete', 'attendance.record', 'attendance.view']);
        }

        // 3. Seed professional rotating duty schedules for past and current months (Meskerem 1, Tikimt 2, Hidar 3, Tahsas 4 of 2019 E.C.)
        $groupA = ShiftGroup::where('name', 'Group A')->first();
        $groupB = ShiftGroup::where('name', 'Group B')->first();
        $groupC = ShiftGroup::where('name', 'Group C')->first();
        $groupD = ShiftGroup::where('name', 'Group D')->first();

        $dayShift = Shift::where('code', 'DAY')->orWhere('name', 'like', '%Day%')->first();
        $nightShift = Shift::where('code', 'NIGHT')->orWhere('name', 'like', '%Night%')->first();

        if ($groupA && $groupB && $groupC && $groupD && $dayShift && $nightShift) {
            $monthConfigs = [
                1 => [
                    'name' => 'Meskerem',
                    'name_am' => 'መስከረም',
                    'a_day' => [1, 5, 9, 13, 17, 21, 25, 29],
                    'a_night' => [3, 7, 11, 15, 19, 23, 27],
                    'b_day' => [3, 7, 11, 15, 19, 23, 27],
                    'b_night' => [1, 5, 9, 13, 17, 21, 25, 29],
                    'c_day' => [2, 6, 10, 14, 18, 22, 26, 30],
                    'c_night' => [4, 8, 12, 16, 20, 24, 28],
                    'd_day' => [4, 8, 12, 16, 20, 24, 28],
                    'd_night' => [2, 6, 10, 14, 18, 22, 26, 30],
                ],
                2 => [
                    'name' => 'Tikimt',
                    'name_am' => 'ጥቅምት',
                    'a_day' => [4, 8, 12, 16, 20, 24, 28],
                    'a_night' => [2, 6, 10, 14, 18, 22, 26, 30],
                    'b_day' => [2, 6, 10, 14, 18, 22, 26, 30],
                    'b_night' => [4, 8, 12, 16, 20, 24, 28],
                    'c_day' => [1, 5, 9, 13, 17, 21, 25, 29],
                    'c_night' => [3, 7, 11, 15, 19, 23, 27],
                    'd_day' => [3, 7, 11, 15, 19, 23, 27],
                    'd_night' => [1, 5, 9, 13, 17, 21, 25, 29],
                ],
                3 => [
                    'name' => 'Hidar',
                    'name_am' => 'ኅዳር',
                    'a_day' => [1, 5, 9, 13, 17, 21, 25, 29],
                    'a_night' => [3, 7, 11, 15, 19, 23, 27],
                    'b_day' => [3, 7, 11, 15, 19, 23, 27],
                    'b_night' => [1, 5, 9, 13, 17, 21, 25, 29],
                    'c_day' => [2, 6, 10, 14, 18, 22, 26, 30],
                    'c_night' => [4, 8, 16, 20, 24, 28],
                    'd_day' => [4, 8, 16, 20, 24, 28],
                    'd_night' => [2, 6, 10, 14, 18, 22, 26, 30],
                ],
                4 => [
                    'name' => 'Tahsas',
                    'name_am' => 'ታኅሣሥ',
                    'a_day' => [4, 8, 12, 16, 20, 24, 28],
                    'a_night' => [2, 6, 10, 14, 18, 22, 26, 30],
                    'b_day' => [2, 6, 10, 14, 18, 22, 26, 30],
                    'b_night' => [4, 8, 12, 16, 20, 24, 28],
                    'c_day' => [1, 5, 9, 13, 17, 21, 25, 29],
                    'c_night' => [3, 7, 11, 15, 19, 23, 27],
                    'd_day' => [3, 7, 11, 15, 19, 23, 27],
                    'd_night' => [1, 5, 9, 13, 17, 21, 25, 29],
                ],
            ];

            foreach ($monthConfigs as $month => $cfg) {
                // Group A
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupA->id, 'shift_id' => $dayShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $month],
                    ['library_id' => null, 'days' => $cfg['a_day'], 'status' => 'active', 'notes' => "Group A Day Shift - {$cfg['name']} 2019 across all library facilities"]
                );
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupA->id, 'shift_id' => $nightShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $month],
                    ['library_id' => null, 'days' => $cfg['a_night'], 'status' => 'active', 'notes' => "Group A Night Shift - {$cfg['name']} 2019 across all library facilities"]
                );

                // Group B
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupB->id, 'shift_id' => $dayShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $month],
                    ['library_id' => null, 'days' => $cfg['b_day'], 'status' => 'active', 'notes' => "Group B Day Shift - {$cfg['name']} 2019 across all library facilities"]
                );
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupB->id, 'shift_id' => $nightShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $month],
                    ['library_id' => null, 'days' => $cfg['b_night'], 'status' => 'active', 'notes' => "Group B Night Shift - {$cfg['name']} 2019 across all library facilities"]
                );

                // Group C
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupC->id, 'shift_id' => $dayShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $month],
                    ['library_id' => null, 'days' => $cfg['c_day'], 'status' => 'active', 'notes' => "Group C Day Shift - {$cfg['name']} 2019 across all library facilities"]
                );
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupC->id, 'shift_id' => $nightShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $month],
                    ['library_id' => null, 'days' => $cfg['c_night'], 'status' => 'active', 'notes' => "Group C Night Shift - {$cfg['name']} 2019 across all library facilities"]
                );

                // Group D
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupD->id, 'shift_id' => $dayShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $month],
                    ['library_id' => null, 'days' => $cfg['d_day'], 'status' => 'active', 'notes' => "Group D Day Shift - {$cfg['name']} 2019 across all library facilities"]
                );
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupD->id, 'shift_id' => $nightShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $month],
                    ['library_id' => null, 'days' => $cfg['d_night'], 'status' => 'active', 'notes' => "Group D Night Shift - {$cfg['name']} 2019 across all library facilities"]
                );
            }
        }
    }

    public function down(): void
    {
        // Safe reversible no-op
    }
};
