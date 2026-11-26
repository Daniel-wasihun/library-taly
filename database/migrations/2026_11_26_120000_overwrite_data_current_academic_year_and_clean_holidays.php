<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\AcademicYear;
use App\Models\GroupShiftSchedule;
use App\Models\Shift;
use App\Models\ShiftGroup;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Truncate/delete all holidays ("no holiday seed")
        DB::table('ethiopian_holidays')->delete();

        // 2. Overwrite and ensure ONLY the current academic year 2019 E.C. exists
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

        // 3. Ensure balanced 1..30 rotating shift schedules for 2019 E.C. (Meskerem 1, Tikimt 2, Hidar 3, Tahsas 4)
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
                    'a_day' => [1, 5, 9, 13, 17, 21, 25, 29],
                    'a_night' => [3, 7, 11, 15, 19, 23, 27],
                    'b_day' => [3, 7, 11, 15, 19, 23, 27],
                    'b_night' => [1, 5, 9, 13, 17, 21, 25, 29],
                    'c_day' => [2, 6, 10, 14, 18, 22, 26, 30],
                    'c_night' => [4, 8, 12, 16, 20, 24, 28], // Full rotation including day 12 (no holiday exclusion)
                    'd_day' => [4, 8, 12, 16, 20, 24, 28],   // Full rotation including day 12
                    'd_night' => [2, 6, 10, 14, 18, 22, 26, 30],
                ],
                4 => [
                    'name' => 'Tahsas',
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

            foreach ($monthConfigs as $monthNum => $cfg) {
                // Group A
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupA->id, 'shift_id' => $dayShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $monthNum],
                    ['library_id' => null, 'days' => $cfg['a_day'], 'status' => 'active', 'notes' => "Group A Day {$cfg['name']} 2019"]
                );
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupA->id, 'shift_id' => $nightShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $monthNum],
                    ['library_id' => null, 'days' => $cfg['a_night'], 'status' => 'active', 'notes' => "Group A Night {$cfg['name']} 2019"]
                );

                // Group B
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupB->id, 'shift_id' => $dayShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $monthNum],
                    ['library_id' => null, 'days' => $cfg['b_day'], 'status' => 'active', 'notes' => "Group B Day {$cfg['name']} 2019"]
                );
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupB->id, 'shift_id' => $nightShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $monthNum],
                    ['library_id' => null, 'days' => $cfg['b_night'], 'status' => 'active', 'notes' => "Group B Night {$cfg['name']} 2019"]
                );

                // Group C
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupC->id, 'shift_id' => $dayShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $monthNum],
                    ['library_id' => null, 'days' => $cfg['c_day'], 'status' => 'active', 'notes' => "Group C Day {$cfg['name']} 2019"]
                );
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupC->id, 'shift_id' => $nightShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $monthNum],
                    ['library_id' => null, 'days' => $cfg['c_night'], 'status' => 'active', 'notes' => "Group C Night {$cfg['name']} 2019"]
                );

                // Group D
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupD->id, 'shift_id' => $dayShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $monthNum],
                    ['library_id' => null, 'days' => $cfg['d_day'], 'status' => 'active', 'notes' => "Group D Day {$cfg['name']} 2019"]
                );
                GroupShiftSchedule::updateOrCreate(
                    ['shift_group_id' => $groupD->id, 'shift_id' => $nightShift->id, 'ethiopian_year' => 2019, 'ethiopian_month' => $monthNum],
                    ['library_id' => null, 'days' => $cfg['d_night'], 'status' => 'active', 'notes' => "Group D Night {$cfg['name']} 2019"]
                );
            }
        }
    }

    public function down(): void
    {
    }
};
