<?php

namespace Database\Seeders;

use App\Models\Library;
use App\Models\LibraryAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LiveShiftDemonstrationSeeder extends Seeder
{
    /**
     * Run the database seeds for real-time shift activity and inactivity demonstration.
     *
     * Resulting State:
     * 1. Techno Library: ACTIVE & COMPLIANT (Green Card)
     *    - Operators: Samuel Alemu (logged 2 mins ago) & Rahel Bekele (logged 12 mins ago).
     *    - Other scheduled: Daniel Fikre, Selamawit Gashaw.
     * 2. Social Library: ACTIVE & COMPLIANT (Green Card)
     *    - Operator: Natnael Solomon (logged 7 mins ago).
     *    - Other scheduled: Kalkidan Abebe, Biniyam Tesfaye, Meron Haile.
     * 3. Natural Library: CRITICAL INACTIVE (> 30 Mins Gap) (Red Alert Card)
     *    - Scheduled attendants: Yohannes Girma, Almaz Kebede, Dawit Mengistu, Bethlehem Tadesse.
     *    - No counts logged for > 3 hours!
     */
    public function run(): void
    {
        $now = Carbon::now('Africa/Addis_Ababa');
        $todayStr = $now->toDateString();

        // 1. Fetch Branches
        $techno = Library::where('code', 'TECHNO')->first() ?? Library::where('name', 'like', '%Techno%')->first();
        $social = Library::where('code', 'SOC')->first() ?? Library::where('name', 'like', '%Social%')->first();
        $natural = Library::where('code', 'NAT')->first() ?? Library::where('name', 'like', '%Natural%')->first();

        if (!$techno || !$social || !$natural) {
            $this->command?->error('One or more library branches not found.');
            return;
        }

        // 2. Fetch Group C Attendants
        $samuel = User::where('username', 'techno_attendant_9')->first();
        $rahel = User::where('username', 'techno_attendant_10')->first();
        $natnael = User::where('username', 'social_attendant_9')->first();
        $yohannes = User::where('username', 'natural_attendant_9')->first();

        // Remove today's recent demo entries to establish a clean, predictable state
        LibraryAttendance::whereIn('library_id', [$techno->id, $social->id, $natural->id])
            ->whereDate('entry_at', $todayStr)
            ->forceDelete();

        // 3. Seed Techno Library: Multiple Active Attendants (Highly Compliant)
        if ($samuel) {
            $entries = [
                ['time' => $now->copy()->setTime(8, 45, 0), 'type' => 'student', 'gender' => 'male', 'count' => 12],
                ['time' => $now->copy()->setTime(9, 30, 0), 'type' => 'student', 'gender' => 'female', 'count' => 18],
                ['time' => $now->copy()->setTime(10, 45, 0), 'type' => 'student', 'gender' => 'male', 'count' => 22],
                ['time' => $now->copy()->setTime(11, 54, 0), 'type' => 'student', 'gender' => 'female', 'count' => 14],
                ['time' => $now->copy()->subMinutes(6), 'type' => 'student', 'gender' => 'male', 'count' => 9],
                ['time' => $now->copy()->subMinutes(2), 'type' => 'teacher', 'gender' => 'female', 'count' => 6],
            ];

            foreach ($entries as $e) {
                LibraryAttendance::create([
                    'slug' => (string) Str::uuid(),
                    'library_id' => $techno->id,
                    'staff_id' => $samuel->id,
                    'user_type' => $e['type'],
                    'gender' => $e['gender'],
                    'visitor_count' => $e['count'],
                    'entry_at' => $e['time']->utc(),
                    'created_at' => $e['time']->utc(),
                    'updated_at' => $e['time']->utc(),
                ]);
            }
        }

        if ($rahel) {
            $entries = [
                ['time' => $now->copy()->setTime(10, 15, 0), 'type' => 'student', 'gender' => 'female', 'count' => 15],
                ['time' => $now->copy()->setTime(11, 30, 0), 'type' => 'student', 'gender' => 'male', 'count' => 11],
                ['time' => $now->copy()->subMinutes(14), 'type' => 'student', 'gender' => 'female', 'count' => 8],
            ];

            foreach ($entries as $e) {
                LibraryAttendance::create([
                    'slug' => (string) Str::uuid(),
                    'library_id' => $techno->id,
                    'staff_id' => $rahel->id,
                    'user_type' => $e['type'],
                    'gender' => $e['gender'],
                    'visitor_count' => $e['count'],
                    'entry_at' => $e['time']->utc(),
                    'created_at' => $e['time']->utc(),
                    'updated_at' => $e['time']->utc(),
                ]);
            }
        }

        // 4. Seed Social Library: Single Active Attendant (Compliant via Natnael Solomon)
        if ($natnael) {
            $entries = [
                ['time' => $now->copy()->setTime(9, 10, 0), 'type' => 'student', 'gender' => 'male', 'count' => 8],
                ['time' => $now->copy()->setTime(10, 40, 0), 'type' => 'student', 'gender' => 'female', 'count' => 14],
                ['time' => $now->copy()->subMinutes(7), 'type' => 'student', 'gender' => 'male', 'count' => 11],
            ];

            foreach ($entries as $e) {
                LibraryAttendance::create([
                    'slug' => (string) Str::uuid(),
                    'library_id' => $social->id,
                    'staff_id' => $natnael->id,
                    'user_type' => $e['type'],
                    'gender' => $e['gender'],
                    'visitor_count' => $e['count'],
                    'entry_at' => $e['time']->utc(),
                    'created_at' => $e['time']->utc(),
                    'updated_at' => $e['time']->utc(),
                ]);
            }
        }

        // 5. Seed Natural Library: Early Morning Entry Only (Now Inactive for > 3 Hours)
        if ($yohannes) {
            // One morning entry at 09:05 AM (more than 3.5 hours ago)
            $earlyMorning = $now->copy()->setTime(9, 5, 0);
            LibraryAttendance::create([
                'slug' => (string) Str::uuid(),
                'library_id' => $natural->id,
                'staff_id' => $yohannes->id,
                'user_type' => 'student',
                'gender' => 'male',
                'visitor_count' => 5,
                'entry_at' => $earlyMorning->utc(),
                'created_at' => $earlyMorning->utc(),
                'updated_at' => $earlyMorning->utc(),
            ]);
        }
    }
}
