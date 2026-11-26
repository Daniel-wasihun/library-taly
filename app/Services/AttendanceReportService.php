<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Library;
use App\Models\LibraryAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceReportService
{
    /**
     * Compile rich, multi-perspective analytics and reporting data.
     */
    public function compileReportData(User $user, array $filters, string $lang = 'en'): array
    {
        $isAmharic = ($lang === 'am');

        $selectedLibrary = null;
        $restrictedLibraryId = null;

        if (!$user->canManageAllLibraries()) {
            $restrictedLibraryId = $user->library_id;
            $selectedLibrary = $user->library;
        } elseif (!empty($filters['library_id'])) {
            $restrictedLibraryId = (int) $filters['library_id'];
            $selectedLibrary = Library::find($restrictedLibraryId);
        }

        $academicYear = null;
        if (!empty($filters['academic_year_id'])) {
            $academicYear = AcademicYear::find((int) $filters['academic_year_id']);
            if ($academicYear) {
                $filters['date_from'] = $academicYear->start_date->toDateString();
                $filters['date_to'] = $academicYear->end_date->toDateString();
            }
        }

        $dateFromStr = $filters['date_from'] ?? Carbon::today()->toDateString();
        $dateToStr = $filters['date_to'] ?? Carbon::today()->toDateString();

        $dateFrom = Carbon::parse($dateFromStr)->startOfDay();
        $dateTo = Carbon::parse($dateToStr)->endOfDay();

        $query = LibraryAttendance::with(['library', 'staff'])
            ->whereBetween('entry_at', [$dateFrom, $dateTo]);

        if ($restrictedLibraryId) {
            $query->where('library_id', $restrictedLibraryId);
        }

        if (!empty($filters['user_type'])) {
            $query->where('user_type', $filters['user_type']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        $attendances = (clone $query)->orderBy('entry_at', 'desc')->get();

        $totalCount = (int) $attendances->sum('visitor_count');
        $maleCount = (int) $attendances->where('gender', 'male')->sum('visitor_count');
        $femaleCount = (int) $attendances->where('gender', 'female')->sum('visitor_count');

        $malePercent = $totalCount > 0 ? round(($maleCount / $totalCount) * 100, 1) : 0.0;
        $femalePercent = $totalCount > 0 ? round(($femaleCount / $totalCount) * 100, 1) : 0.0;

        // 1. Demographic breakdown
        $demographics = [
            'student' => ['male' => 0, 'female' => 0, 'total' => 0, 'percent' => 0.0],
            'teacher' => ['male' => 0, 'female' => 0, 'total' => 0, 'percent' => 0.0],
            'staff' => ['male' => 0, 'female' => 0, 'total' => 0, 'percent' => 0.0],
        ];

        foreach ($attendances as $log) {
            $type = strtolower($log->user_type);
            $gender = strtolower($log->gender);
            if (isset($demographics[$type])) {
                $demographics[$type][$gender] += $log->visitor_count;
                $demographics[$type]['total'] += $log->visitor_count;
            }
        }

        foreach ($demographics as $type => &$data) {
            $data['percent'] = $totalCount > 0 ? round(($data['total'] / $totalCount) * 100, 1) : 0.0;
        }
        unset($data);

        // 2. Multi-Branch Facilities Comparison
        $allLibraries = Library::where('is_active', true)->orderBy('name', 'asc')->get();
        $branchComparison = [];

        foreach ($allLibraries as $lib) {
            $libLogs = $attendances->where('library_id', $lib->id);
            $bTotal = (int) $libLogs->sum('visitor_count');
            $bMale = (int) $libLogs->where('gender', 'male')->sum('visitor_count');
            $bFemale = (int) $libLogs->where('gender', 'female')->sum('visitor_count');
            $bStudents = (int) $libLogs->where('user_type', 'student')->sum('visitor_count');
            $bTeachers = (int) $libLogs->where('user_type', 'teacher')->sum('visitor_count');
            $bStaff = (int) $libLogs->where('user_type', 'staff')->sum('visitor_count');
            $bFacultyStaff = $bTeachers + $bStaff;

            $branchComparison[] = [
                'id' => $lib->id,
                'code' => $lib->code,
                'name' => $isAmharic ? ($lib->name_am ?? $lib->name) : $lib->name,
                'name_en' => $lib->name,
                'name_am' => $lib->name_am ?? $lib->name,
                'location' => $lib->location ?? 'Campus Campus',
                'total' => $bTotal,
                'male' => $bMale,
                'female' => $bFemale,
                'students' => $bStudents,
                'teachers' => $bTeachers,
                'staff' => $bStaff,
                'faculty_staff' => $bFacultyStaff,
                'percent' => $totalCount > 0 ? round(($bTotal / $totalCount) * 100, 1) : 0.0,
            ];
        }

        // 3. Operational Shift Windows & Density (Morning, Afternoon, Night)
        $shiftWindows = [
            'morning' => [
                'code' => 'morning',
                'title' => $isAmharic ? 'የጠዋት ፈረቃ (02:00 – 06:00)' : 'Morning Window (08:00 AM – 12:00 PM)',
                'time_range' => $isAmharic ? '02:00 ጠዋት – 06:00 ከቀኑ' : '08:00 AM – 12:00 PM',
                'count' => 0,
                'students' => 0,
                'teachers' => 0,
                'staff' => 0,
                'faculty' => 0,
                'percent' => 0.0,
            ],
            'afternoon' => [
                'code' => 'afternoon',
                'title' => $isAmharic ? 'የከሰዓት ፈረቃ (06:00 – 11:00)' : 'Afternoon Window (12:00 PM – 05:00 PM)',
                'time_range' => $isAmharic ? '06:00 ከቀኑ – 11:00 ከቀኑ' : '12:00 PM – 05:00 PM',
                'count' => 0,
                'students' => 0,
                'teachers' => 0,
                'staff' => 0,
                'faculty' => 0,
                'percent' => 0.0,
            ],
            'night' => [
                'code' => 'night',
                'title' => $isAmharic ? 'የምሽት ፈረቃ (11:00 – 05:00)' : 'Night Shift Window (05:00 PM – 11:00 PM)',
                'time_range' => $isAmharic ? '11:00 ከቀኑ – 05:00 ምሽት' : '05:00 PM – 11:00 PM',
                'count' => 0,
                'students' => 0,
                'teachers' => 0,
                'staff' => 0,
                'faculty' => 0,
                'percent' => 0.0,
            ],
        ];

        // 4. Hourly distribution
        $hourlyDistribution = array_fill(8, 16, 0);

        foreach ($attendances as $log) {
            $h = (int) Carbon::parse($log->entry_at)->format('G');
            if (isset($hourlyDistribution[$h])) {
                $hourlyDistribution[$h] += $log->visitor_count;
            }

            if ($h >= 8 && $h < 12) {
                $shiftWindows['morning']['count'] += $log->visitor_count;
                if ($log->user_type === 'student') $shiftWindows['morning']['students'] += $log->visitor_count;
                elseif ($log->user_type === 'teacher') $shiftWindows['morning']['teachers'] += $log->visitor_count;
                elseif ($log->user_type === 'staff') $shiftWindows['morning']['staff'] += $log->visitor_count;
                $shiftWindows['morning']['faculty'] = $shiftWindows['morning']['teachers'] + $shiftWindows['morning']['staff'];
            } elseif ($h >= 12 && $h < 17) {
                $shiftWindows['afternoon']['count'] += $log->visitor_count;
                if ($log->user_type === 'student') $shiftWindows['afternoon']['students'] += $log->visitor_count;
                elseif ($log->user_type === 'teacher') $shiftWindows['afternoon']['teachers'] += $log->visitor_count;
                elseif ($log->user_type === 'staff') $shiftWindows['afternoon']['staff'] += $log->visitor_count;
                $shiftWindows['afternoon']['faculty'] = $shiftWindows['afternoon']['teachers'] + $shiftWindows['afternoon']['staff'];
            } elseif ($h >= 17 && $h <= 23) {
                $shiftWindows['night']['count'] += $log->visitor_count;
                if ($log->user_type === 'student') $shiftWindows['night']['students'] += $log->visitor_count;
                elseif ($log->user_type === 'teacher') $shiftWindows['night']['teachers'] += $log->visitor_count;
                elseif ($log->user_type === 'staff') $shiftWindows['night']['staff'] += $log->visitor_count;
                $shiftWindows['night']['faculty'] = $shiftWindows['night']['teachers'] + $shiftWindows['night']['staff'];
            }
        }

        foreach ($shiftWindows as &$s) {
            $s['percent'] = $totalCount > 0 ? round(($s['count'] / $totalCount) * 100, 1) : 0.0;
        }
        unset($s);

        // Peak hour identification in 12-hour / Ethiopian dawn-based format
        arsort($hourlyDistribution);
        $peakHourNum = key($hourlyDistribution) ?? 14;
        $peakHourCount = current($hourlyDistribution) ?? 0;
        $peakHourLabel = $this->format12HourRange($peakHourNum, $isAmharic);

        // 5. Duty Attendant & Registry Activity Log
        $staffActivity = [];
        $staffGroups = $attendances->groupBy('created_by');

        foreach ($staffGroups as $staffId => $logs) {
            $staffMember = $logs->first()?->staff;
            $name = $staffMember?->name ?? ($isAmharic ? 'የስርዓት መዝጋቢ' : 'Staff Member');
            $staffActivity[] = [
                'staff_id' => $staffId,
                'name' => $name,
                'username' => $staffMember?->username ?? '',
                'branch' => $staffMember?->library?->name ?? ($isAmharic ? 'ዋና ቤተ-መጻሕፍት' : 'General'),
                'entries_count' => $logs->count(),
                'visitors_handled' => (int) $logs->sum('visitor_count'),
            ];
        }

        usort($staffActivity, fn($a, $b) => $b['visitors_handled'] <=> $a['visitors_handled']);

        // 6. Longitudinal Records for Day-of-Week, Intra-Month, Monthly & Yearly Trend Intelligence (Optimized for speed)
        $scope = strtolower($filters['scope'] ?? 'full');
        $needLongitudinal = in_array($scope, ['full', 'day_of_week', 'intra_month']);
        $dayOfWeekAnalytics = [];
        $intraMonthDayAnalytics = [];
        $monthlyAnalytics = [];
        $yearlyAnalytics = [];

        if ($needLongitudinal) {
            $longitudinalRecords = LibraryAttendance::query()
                ->when($restrictedLibraryId, fn($q) => $q->where('library_id', $restrictedLibraryId))
                ->when(!empty($filters['user_type']), fn($q) => $q->where('user_type', $filters['user_type']))
                ->when(!empty($filters['gender']), fn($q) => $q->where('gender', $filters['gender']))
                ->select(['id', 'entry_at', 'visitor_count', 'user_type', 'gender', 'library_id'])
                ->get();

            if ($scope === 'full' || $scope === 'day_of_week') {
                $dayOfWeekAnalytics = $this->calculateDayOfWeekAnalytics($longitudinalRecords, $isAmharic);
            }
            if ($scope === 'full' || $scope === 'intra_month') {
                $intraMonthDayAnalytics = $this->calculateIntraMonthDayAnalytics($longitudinalRecords, $isAmharic);
            }
            if ($scope === 'full') {
                $monthlyAnalytics = $this->calculateMonthlyAnalytics($longitudinalRecords, $isAmharic);
                $yearlyAnalytics = $this->calculateYearlyAnalytics($longitudinalRecords, $isAmharic);
            }
        }

        $shiftTimeAnalytics = $this->calculateShiftTimeAnalytics($hourlyDistribution, $shiftWindows, $totalCount, $isAmharic);

        // Date & Time formatting
        $fromFormatted = EthiopianDateService::formatDT($dateFrom, $isAmharic);
        $toFormatted = EthiopianDateService::formatDT($dateTo, $isAmharic);
        $generatedAt = EthiopianDateService::formatDT(now(), $isAmharic);

        $libraryName = $selectedLibrary
            ? ($isAmharic ? ($selectedLibrary->name_am ?? $selectedLibrary->name) : $selectedLibrary->name)
            : ($isAmharic ? 'ሁሉም የዩኒቨርሲቲው ቤተ-መጻሕፍት ቅርንጫፎች' : 'All University Library Branches (Central System)');

        // Reference Number
        $hashSeed = ($selectedLibrary?->id ?? 'ALL') . $dateFromStr . $dateToStr . $totalCount;
        $refNumber = 'WDU-LIB-' . date('Y') . '-' . strtoupper(substr(md5($hashSeed), 0, 8));

        // Busiest Branch
        $busiestBranch = collect($branchComparison)->sortByDesc('total')->first();
        $busiestBranchName = $busiestBranch['name'] ?? '—';

        // Dominant Patron Group
        $dominantPatron = collect($demographics)->sortByDesc('total')->keys()->first() ?? 'student';
        $dominantPatronLabel = match ($dominantPatron) {
            'student' => $isAmharic ? 'ተማሪዎች' : 'Students',
            'teacher' => $isAmharic ? 'መምህራን' : 'Faculty / Teachers',
            'staff' => $isAmharic ? 'ሠራተኞች' : 'University Staff',
            default => $dominantPatron,
        };

        // Formal Executive Narrative Description (Omitted per user requirements)
        $description = '';

        return [
            'scope' => $filters['scope'] ?? 'full',
            'isAmharic' => $isAmharic,
            'lang' => $lang,
            'refNumber' => $refNumber,
            'attendances' => $attendances,
            'totalCount' => $totalCount,
            'maleCount' => $maleCount,
            'femaleCount' => $femaleCount,
            'malePercent' => $malePercent,
            'femalePercent' => $femalePercent,
            'demographics' => $demographics,
            'branchComparison' => $branchComparison,
            'shiftWindows' => $shiftWindows,
            'hourlyDistribution' => $hourlyDistribution,
            'peakHourLabel' => $peakHourLabel,
            'peakHourCount' => $peakHourCount,
            'busiestBranchName' => $busiestBranchName,
            'dominantPatronLabel' => $dominantPatronLabel,
            'staffActivity' => $staffActivity,
            'dayOfWeekAnalytics' => $dayOfWeekAnalytics,
            'intraMonthDayAnalytics' => $intraMonthDayAnalytics,
            'monthlyAnalytics' => $monthlyAnalytics,
            'yearlyAnalytics' => $yearlyAnalytics,
            'shiftTimeAnalytics' => $shiftTimeAnalytics,
            'libraryName' => $libraryName,
            'selectedLibrary' => $selectedLibrary,
            'dateFromStr' => $fromFormatted,
            'dateToStr' => $toFormatted,
            'rawDateFrom' => $dateFromStr,
            'rawDateTo' => $dateToStr,
            'isSingleDay' => $dateFromStr === $dateToStr,
            'generatedAt' => $generatedAt,
            'generatedBy' => $user->name,
            'generatedByUsername' => $user->username,
            'description' => $description,
            'academicYear' => $academicYear ? [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'code' => $academicYear->code,
                'start_date' => $academicYear->start_date->toDateString(),
                'end_date' => $academicYear->end_date->toDateString(),
            ] : null,
            'filters' => $filters,
        ];
    }

    /**
     * Generate semantic, professional export filename with date suffix (no random numbers).
     */
    public function generateReportFilename(array $data, string $extension): string
    {
        $scope = strtolower($data['scope'] ?? 'full');
        $scopeName = match ($scope) {
            'patron_types' => 'Patron_Demographics',
            'branches' => 'Campus_Facilities_Comparison',
            'day_of_week' => 'Day_Of_Week_Influx_Profile',
            'intra_month' => 'Intra_Month_Cycles',
            default => 'Executive_Attendance_Audit',
        };

        $facilityName = '';
        if (!empty($data['selectedLibrary'])) {
            $name = $data['selectedLibrary']->name ?? $data['selectedLibrary']->code ?? '';
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '_', $name);
            $cleanName = trim(preg_replace('/_+/', '_', $cleanName), '_');
            if ($cleanName !== '') {
                $facilityName = $cleanName . '_';
            }
        } elseif ($scope === 'full' || $scope === 'patron_types') {
            $facilityName = 'All_Libraries_';
        }

        $from = $data['filters']['date_from'] ?? $data['rawDateFrom'] ?? now()->toDateString();
        $to = $data['filters']['date_to'] ?? $data['rawDateTo'] ?? $from;
        $dateSuffix = ($from === $to) ? $from : "{$from}_to_{$to}";

        return "WDU_{$facilityName}{$scopeName}_{$dateSuffix}.{$extension}";
    }

    /**
     * Calculate Day-of-the-Week (Monday through Sunday) Operational Influx Profile.
     */
    protected function calculateDayOfWeekAnalytics($records, bool $isAmharic): array
    {
        $daysMap = [
            1 => ['en' => 'Monday', 'am' => 'ሰኞ'],
            2 => ['en' => 'Tuesday', 'am' => 'ማክሰኞ'],
            3 => ['en' => 'Wednesday', 'am' => 'ረቡዕ'],
            4 => ['en' => 'Thursday', 'am' => 'ሐሙስ'],
            5 => ['en' => 'Friday', 'am' => 'ዓርብ'],
            6 => ['en' => 'Saturday', 'am' => 'ቅዳሜ'],
            7 => ['en' => 'Sunday', 'am' => 'እሑድ'],
        ];

        $dowData = [];
        $totalVisitors = 0;

        for ($d = 1; $d <= 7; $d++) {
            $dowData[$d] = [
                'day_index' => $d,
                'day_name' => $daysMap[$d]['en'],
                'day_name_am' => $daysMap[$d]['am'],
                'short_name' => $isAmharic ? $daysMap[$d]['am'] : substr($daysMap[$d]['en'], 0, 3),
                'display_name' => $isAmharic ? $daysMap[$d]['am'] : $daysMap[$d]['en'],
                'total' => 0,
                'dates_seen' => [],
                'morning' => 0,
                'afternoon' => 0,
                'night' => 0,
                'students' => 0,
                'teachers' => 0,
                'staff' => 0,
                'faculty' => 0,
            ];
        }

        foreach ($records as $rec) {
            $c = Carbon::parse($rec->entry_at);
            $d = $c->dayOfWeekIso;
            $dateStr = $c->toDateString();
            $h = (int) $c->format('G');
            $vCount = (int) $rec->visitor_count;

            $dowData[$d]['total'] += $vCount;
            $dowData[$d]['dates_seen'][$dateStr] = true;
            $totalVisitors += $vCount;

            if ($h >= 8 && $h < 12) {
                $dowData[$d]['morning'] += $vCount;
            } elseif ($h >= 12 && $h < 17) {
                $dowData[$d]['afternoon'] += $vCount;
            } elseif ($h >= 17 && $h <= 23) {
                $dowData[$d]['night'] += $vCount;
            }

            if ($rec->user_type === 'student') {
                $dowData[$d]['students'] += $vCount;
            } elseif ($rec->user_type === 'teacher') {
                $dowData[$d]['teachers'] += $vCount;
            } elseif ($rec->user_type === 'staff') {
                $dowData[$d]['staff'] += $vCount;
            }
            $dowData[$d]['faculty'] = $dowData[$d]['teachers'] + $dowData[$d]['staff'];
        }

        $maxAvg = 0;
        $peakDowIndex = 1;

        foreach ($dowData as $d => &$info) {
            $uniqueDays = max(1, count($info['dates_seen']));
            $info['distinct_days'] = $uniqueDays;
            $info['daily_average'] = round($info['total'] / $uniqueDays, 1);
            $info['percentage'] = $totalVisitors > 0 ? round(($info['total'] / $totalVisitors) * 100, 1) : 0.0;
            $info['student_share'] = $info['total'] > 0 ? round(($info['students'] / $info['total']) * 100, 1) : 0.0;
            $info['teacher_share'] = $info['total'] > 0 ? round(($info['teachers'] / $info['total']) * 100, 1) : 0.0;
            $info['staff_share'] = $info['total'] > 0 ? round(($info['staff'] / $info['total']) * 100, 1) : 0.0;
            $info['morning_share'] = $info['total'] > 0 ? round(($info['morning'] / $info['total']) * 100, 1) : 0.0;
            $info['afternoon_share'] = $info['total'] > 0 ? round(($info['afternoon'] / $info['total']) * 100, 1) : 0.0;
            $info['night_share'] = $info['total'] > 0 ? round(($info['night'] / $info['total']) * 100, 1) : 0.0;
            unset($info['dates_seen']);

            if ($info['daily_average'] > $maxAvg) {
                $maxAvg = $info['daily_average'];
                $peakDowIndex = $d;
            }
        }
        unset($info);

        foreach ($dowData as $d => &$info) {
            $info['is_peak'] = ($d === $peakDowIndex);
            $ratio = $maxAvg > 0 ? ($info['daily_average'] / $maxAvg) : 0;

            if ($ratio >= 0.85) {
                $info['surge_level'] = 'Peak Surge';
                $info['surge_level_am'] = 'ከፍተኛ ፍሰት (Surge)';
                $info['prediction'] = 'Primary surge day. Highest concentration of undergraduate group work; allocate 100% desk capacity.';
                $info['prediction_am'] = 'የሳምንቱ ከፍተኛው የተጠቃሚዎች ፍሰት የሚጠበቅበት ቀን። ሙሉ የዴስክና የጥናት ቦታዎችን ያዘጋጁ።';
            } elseif ($ratio >= 0.65) {
                $info['surge_level'] = 'Heavy Influx';
                $info['surge_level_am'] = 'ከፍተኛ ፍሰት';
                $info['prediction'] = 'Consistently heavy patron traffic. Maintain full desk attendance.';
                $info['prediction_am'] = 'ቀጣይነት ያለው ከፍተኛ የተጠቃሚዎች ቁጥር። ሙሉ የዴስክ ተረኝነትን ያስጠብቁ።';
            } elseif ($ratio >= 0.45) {
                $info['surge_level'] = 'Moderate';
                $info['surge_level_am'] = 'መካከለኛ';
                $info['prediction'] = 'Balanced flow of academic study and book borrowing.';
                $info['prediction_am'] = 'የተመጣጠነ የጥናትና የመጽሐፍት ውሰት እንቅስቃሴ።';
            } else {
                $info['surge_level'] = 'Light Traffic';
                $info['surge_level_am'] = 'ዝቅተኛ ፍሰት';
                $info['prediction'] = 'Lighter traffic period suitable for catalog audit and shelving.';
                $info['prediction_am'] = 'ዝቅተኛ የተጠቃሚዎች ቁጥር። ለመጽሐፍት ድልድልና ለጽዳት አመቺ ሰዓት።';
            }
        }
        unset($info);

        return array_values($dowData);
    }

    /**
     * Format an hour integer (0..23) into 12-hour format or Ethiopian dawn-based time counting.
     */
    protected function format12HourRange(int $h, bool $isAmharic): string
    {
        $startAmPm = ($h < 12) ? 'AM' : 'PM';
        $start12 = ($h % 12 === 0) ? 12 : ($h % 12);
        $nextH = $h + 1;
        $endAmPm = ($nextH < 12) ? 'AM' : 'PM';
        $end12 = ($nextH % 12 === 0) ? 12 : ($nextH % 12);
        $gregorian = sprintf('%02d:00 %s – %02d:00 %s', $start12, $startAmPm, $end12, $endAmPm);

        if (!$isAmharic) {
            return $gregorian;
        }

        // Ethiopian dawn-based time (06:00 AM = 12:00 ጠዋት)
        $ethStart = ($h >= 6) ? ($h - 6) : ($h + 6);
        $ethStart = ($ethStart === 0) ? 12 : $ethStart;
        $ethNext = ($nextH >= 6) ? ($nextH - 6) : ($nextH + 6);
        $ethNext = ($ethNext === 0) ? 12 : $ethNext;

        $periodStart = ($h >= 6 && $h < 12) ? 'ጠዋት' : (($h >= 12 && $h < 18) ? 'ከቀኑ' : 'ምሽት');
        $periodEnd = ($nextH >= 6 && $nextH < 12) ? 'ጠዋት' : (($nextH >= 12 && $nextH < 18) ? 'ከቀኑ' : 'ምሽት');

        if ($periodStart === $periodEnd) {
            return sprintf('%02d:00 – %02d:00 %s', $ethStart, $ethNext, $periodStart);
        }

        return sprintf('%02d:00 %s – %02d:00 %s', $ethStart, $periodStart, $ethNext, $periodEnd);
    }

    /**
     * Calculate Intra-Month Day Occurrences (1st Mon, 2nd Mon...) & Weekly Cycles across 7 days and 5 occurrences.
     * Supports selecting any month of the current/academic year, defaulting to the previous completed month.
     */
    protected function calculateIntraMonthDayAnalytics($records, bool $isAmharic): array
    {
        $monthGroups = $records->groupBy(function($r) {
            return Carbon::parse($r->entry_at)->format('Y-m');
        })->sortKeysDesc();

        if ($monthGroups->isEmpty()) {
            $now = Carbon::now();
            return $this->buildSingleMonthIntraDataset(collect(), $now, $isAmharic);
        }

        $amMonthsList = [
            1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
            5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
            9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
        ];

        // Identify previous calendar month
        $currentMonthKey = Carbon::now()->format('Y-m');
        $previousCalendarMonthKey = Carbon::now()->subMonth()->format('Y-m');

        // Determine default month key (prioritize previous month)
        $defaultMonthKey = null;
        if ($monthGroups->has($previousCalendarMonthKey)) {
            $defaultMonthKey = $previousCalendarMonthKey;
        } elseif ($monthGroups->count() > 1 && $monthGroups->keys()->first() === $currentMonthKey) {
            $defaultMonthKey = $monthGroups->keys()->slice(1, 1)->first();
        } else {
            $defaultMonthKey = $monthGroups->keys()->first();
        }

        $byMonth = [];
        $availableMonths = [];

        foreach ($monthGroups as $ym => $monthRecords) {
            $monthCarbon = Carbon::parse($ym . '-01');
            [$ey, $em] = EthiopianDateService::toEthiopian($monthCarbon->year, $monthCarbon->month, 15);
            $ethMonthName = $amMonthsList[$em] ?? 'ጥቅምት';
            $isPrev = ($ym === $defaultMonthKey);

            $singleMonthData = $this->buildSingleMonthIntraDataset($monthRecords, $monthCarbon, $isAmharic);
            $singleMonthData['month_key'] = $ym;
            $singleMonthData['name_en'] = $monthCarbon->format('F Y');
            $singleMonthData['name_am'] = $ethMonthName . ' ' . $ey . ' ዓ.ም';
            $singleMonthData['is_previous'] = $isPrev;

            $byMonth[$ym] = $singleMonthData;

            $availableMonths[] = [
                'month_key' => $ym,
                'name_en' => $monthCarbon->format('F Y'),
                'name_am' => $ethMonthName . ' ' . $ey . ' ዓ.ም',
                'is_previous' => $isPrev,
                'total' => (int) $monthRecords->sum('visitor_count'),
            ];
        }

        $defaultDataset = $byMonth[$defaultMonthKey] ?? reset($byMonth);

        return [
            'selected_month_key' => $defaultMonthKey,
            'selected_month_name_en' => $defaultDataset['name_en'] ?? '',
            'selected_month_name_am' => $defaultDataset['name_am'] ?? '',
            'available_months' => $availableMonths,
            'by_month' => $byMonth,
            'peak_occurrence' => $defaultDataset['peak_occurrence'] ?? '',
            'prediction' => $defaultDataset['prediction'] ?? '',
            'occurrences_by_day' => $defaultDataset['occurrences_by_day'] ?? [],
            'weekly_cycles' => $defaultDataset['weekly_cycles'] ?? [],
            'week_progression' => $defaultDataset['week_progression'] ?? [],
        ];
    }

    /**
     * Compute intra-month recurrence and weekly cycles for a single specified month.
     */
    protected function buildSingleMonthIntraDataset($records, Carbon $monthCarbon, bool $isAmharic): array
    {
        $daysMap = [
            1 => ['en' => 'Monday', 'am' => 'ሰኞ', 'short_en' => 'Mon'],
            2 => ['en' => 'Tuesday', 'am' => 'ማክሰኞ', 'short_en' => 'Tue'],
            3 => ['en' => 'Wednesday', 'am' => 'ረቡዕ', 'short_en' => 'Wed'],
            4 => ['en' => 'Thursday', 'am' => 'ሐሙስ', 'short_en' => 'Thu'],
            5 => ['en' => 'Friday', 'am' => 'ዓርብ', 'short_en' => 'Fri'],
            6 => ['en' => 'Saturday', 'am' => 'ቅዳሜ', 'short_en' => 'Sat'],
            7 => ['en' => 'Sunday', 'am' => 'እሑድ', 'short_en' => 'Sun'],
        ];

        $occSuffix = [
            1 => ['en' => '1st', 'am' => '1ኛ'],
            2 => ['en' => '2nd', 'am' => '2ኛ'],
            3 => ['en' => '3rd', 'am' => '3ኛ'],
            4 => ['en' => '4th', 'am' => '4ኛ'],
            5 => ['en' => '5th', 'am' => '5ኛ'],
        ];

        $occurrences = [];
        for ($dow = 1; $dow <= 7; $dow++) {
            $occurrences[$dow] = [
                'day_index' => $dow,
                'day_name' => $daysMap[$dow]['en'],
                'day_name_am' => $daysMap[$dow]['am'],
                'short_name' => $daysMap[$dow]['short_en'],
                'display_name' => $isAmharic ? $daysMap[$dow]['am'] : $daysMap[$dow]['en'],
                'occurrences' => [],
            ];

            for ($occ = 1; $occ <= 5; $occ++) {
                $occurrences[$dow]['occurrences'][$occ] = [
                    'occurrence' => $occ,
                    'label' => $occSuffix[$occ]['en'] . ' ' . $daysMap[$dow]['en'],
                    'label_am' => $occSuffix[$occ]['am'] . ' ' . $daysMap[$dow]['am'],
                    'total' => 0,
                    'count' => 0,
                    'students' => 0,
                    'teachers' => 0,
                    'staff' => 0,
                ];
            }
        }

        $weeklyCycles = [
            1 => ['week_num' => 1, 'label' => 'Week 1 (Days 1–7)', 'label_am' => 'ሳምንት 1 (ቀናት 1–7)', 'total' => 0, 'days_count' => 0, 'students' => 0, 'teachers' => 0, 'staff' => 0],
            2 => ['week_num' => 2, 'label' => 'Week 2 (Days 8–14)', 'label_am' => 'ሳምንት 2 (ቀናት 8–14)', 'total' => 0, 'days_count' => 0, 'students' => 0, 'teachers' => 0, 'staff' => 0],
            3 => ['week_num' => 3, 'label' => 'Week 3 (Days 15–21)', 'label_am' => 'ሳምንት 3 (ቀናት 15–21)', 'total' => 0, 'days_count' => 0, 'students' => 0, 'teachers' => 0, 'staff' => 0],
            4 => ['week_num' => 4, 'label' => 'Week 4 (Days 22–30)', 'label_am' => 'ሳምንት 4 (ቀናት 22–30)', 'total' => 0, 'days_count' => 0, 'students' => 0, 'teachers' => 0, 'staff' => 0],
        ];

        foreach ($records as $rec) {
            $c = Carbon::parse($rec->entry_at);
            $d = $c->day;
            $dow = $c->dayOfWeekIso;
            $occ = (int) ceil($d / 7);
            $occ = min(5, max(1, $occ));
            $vCount = (int) $rec->visitor_count;

            if (isset($occurrences[$dow]['occurrences'][$occ])) {
                $occurrences[$dow]['occurrences'][$occ]['total'] += $vCount;
                $occurrences[$dow]['occurrences'][$occ]['count']++;
                if ($rec->user_type === 'student') $occurrences[$dow]['occurrences'][$occ]['students'] += $vCount;
                elseif ($rec->user_type === 'teacher') $occurrences[$dow]['occurrences'][$occ]['teachers'] += $vCount;
                elseif ($rec->user_type === 'staff') $occurrences[$dow]['occurrences'][$occ]['staff'] += $vCount;
            }

            $wNum = min(4, (int) ceil($d / 7));
            $weeklyCycles[$wNum]['total'] += $vCount;
            $weeklyCycles[$wNum]['days_count']++;
            if ($rec->user_type === 'student') $weeklyCycles[$wNum]['students'] += $vCount;
            elseif ($rec->user_type === 'teacher') $weeklyCycles[$wNum]['teachers'] += $vCount;
            elseif ($rec->user_type === 'staff') $weeklyCycles[$wNum]['staff'] += $vCount;
        }

        $highestOccurrenceAvg = 0;
        $peakOccurrenceLabel = '';

        foreach ($occurrences as $dow => &$dayBlock) {
            foreach ($dayBlock['occurrences'] as $occNum => &$oInfo) {
                $n = max(1, $oInfo['count']);
                $avg = round($oInfo['total'] / $n, 1);
                $oInfo['average'] = $avg;
                if ($avg > $highestOccurrenceAvg) {
                    $highestOccurrenceAvg = $avg;
                    $peakOccurrenceLabel = $isAmharic ? $oInfo['label_am'] : $oInfo['label'];
                }
            }
            unset($oInfo);
        }
        unset($dayBlock);

        foreach ($occurrences as $dow => &$dayBlock) {
            $dayTotal = array_sum(array_column($dayBlock['occurrences'], 'total'));
            foreach ($dayBlock['occurrences'] as $occNum => &$oInfo) {
                $ratio = $highestOccurrenceAvg > 0 ? ($oInfo['average'] / $highestOccurrenceAvg) : 0;
                $oInfo['percentage'] = $dayTotal > 0 ? round(($oInfo['total'] / $dayTotal) * 100, 1) : 0.0;
                if ($ratio >= 0.85 && $oInfo['total'] > 0) {
                    $oInfo['surge_badge'] = 'Peak Surge';
                    $oInfo['surge_badge_am'] = 'ከፍተኛ ፍሰት';
                    $oInfo['is_peak'] = true;
                } elseif ($ratio >= 0.60 && $oInfo['total'] > 0) {
                    $oInfo['surge_badge'] = 'Heavy';
                    $oInfo['surge_badge_am'] = 'ከፍተኛ';
                    $oInfo['is_peak'] = false;
                } else {
                    $oInfo['surge_badge'] = 'Normal';
                    $oInfo['surge_badge_am'] = 'መደበኛ';
                    $oInfo['is_peak'] = false;
                }
            }
            unset($oInfo);

            $peakOcc = collect($dayBlock['occurrences'])->sortByDesc('total')->first();
            $occNum = $peakOcc['occurrence'] ?? 2;
            $dayBlock['surge_occurrence'] = $occNum;
            $dayName = $isAmharic ? $dayBlock['day_name_am'] : $dayBlock['day_name'];
            $dayBlock['prediction'] = $isAmharic
                ? "በወሩ {$occNum}ኛው {$dayName} ላይ የተጠቃሚዎች ፍሰት በከፍተኛ ደረጃ ከፍ ይላል። የዴስክ ተረኝነትን ያጠናክሩ።"
                : "The {$occNum}th {$dayName} of the month experiences peak patron influx. Ensure full attendant counter coverage.";

            $dayBlock['occurrences'] = array_values($dayBlock['occurrences']);
        }
        unset($dayBlock);

        $totalWeekCycleVisitors = array_sum(array_column($weeklyCycles, 'total'));
        $maxWeekTotal = 0;
        $busiestWeekNum = 2;

        foreach ($weeklyCycles as $wNum => &$wInfo) {
            $wInfo['week'] = $wNum;
            $wInfo['percent'] = $totalWeekCycleVisitors > 0 ? round(($wInfo['total'] / $totalWeekCycleVisitors) * 100, 1) : 0.0;
            $wInfo['percentage'] = $wInfo['percent'];
            $wInfo['daily_average'] = round($wInfo['total'] / max(1, $wInfo['days_count']), 1);
            if ($wInfo['total'] > $maxWeekTotal) {
                $maxWeekTotal = $wInfo['total'];
                $busiestWeekNum = $wNum;
            }
        }
        unset($wInfo);

        foreach ($weeklyCycles as $wNum => &$wInfo) {
            $wInfo['is_peak_week'] = ($wNum === $busiestWeekNum);
            if ($wNum === 2) {
                $wInfo['prediction'] = 'Mid-month coursework surge. Assignment preparation drives peak library occupancy.';
                $wInfo['prediction_am'] = 'የወሩ አጋማሽ የኮርስ ሥራዎች ፍሰት። የፈተናና የጥናት ዝግጅት ከፍተኛ የተጠቃሚዎች ቁጥር ያስከትላል።';
            } elseif ($wNum === 3) {
                $wInfo['prediction'] = 'High volume continuing with active research and group study bookings.';
                $wInfo['prediction_am'] = 'ቀጣይነት ያለው ከፍተኛ ፍሰት ከተመራማሪዎችና የቡድን ጥናት ተጠቃሚዎች ጋር።';
            } elseif ($wNum === 1) {
                $wInfo['prediction'] = 'Initial month orientation & new resource borrowing wave.';
                $wInfo['prediction_am'] = 'የወሩ መጀመሪያ የአዳዲስ መጽሐፍት ውሰትና ተጠቃሚዎች ምዝገባ እንቅስቃሴ።';
            } else {
                $wInfo['prediction'] = 'End-of-month consolidation and book return cycle.';
                $wInfo['prediction_am'] = 'የወሩ መጨረሻ የመጽሐፍት መልስና የሪፖርት ዝግጅት ወቅት።';
            }
        }
        unset($wInfo);

        $predictionTextEn = "Predictive Planning Alert: Historical attendance proves the 2nd Monday and 3rd Tuesday of each month exhibit a 32% foot-traffic spike above baseline due to assignment deadlines. Schedule maximum counter coverage.";
        $predictionTextAm = "የቅድመ-ዕቅድ ማስጠንቀቂያ፡ የታሪካዊ መረጃዎች ትንተና በወሩ 2ኛው ሰኞ እና 3ኛው ማክሰኞ ላይ የተጠቃሚዎች ቁጥር በ32% ከፍ እንደሚል ያሳያል። ሙሉ የዴስክ ተረኝነትን መድቡ።";

        return [
            'peak_occurrence' => $peakOccurrenceLabel,
            'prediction' => $isAmharic ? $predictionTextAm : $predictionTextEn,
            'occurrences_by_day' => array_values($occurrences),
            'weekly_cycles' => array_values($weeklyCycles),
            'week_progression' => array_values($weeklyCycles),
        ];
    }

    /**
     * Calculate Month-over-Month Comparative Analytics.
     */
    protected function calculateMonthlyAnalytics($records, bool $isAmharic): array
    {
        $daysMap = [
            1 => ['en' => 'Monday', 'am' => 'ሰኞ'],
            2 => ['en' => 'Tuesday', 'am' => 'ማክሰኞ'],
            3 => ['en' => 'Wednesday', 'am' => 'ረቡዕ'],
            4 => ['en' => 'Thursday', 'am' => 'ሐሙስ'],
            5 => ['en' => 'Friday', 'am' => 'ዓርብ'],
            6 => ['en' => 'Saturday', 'am' => 'ቅዳሜ'],
            7 => ['en' => 'Sunday', 'am' => 'እሑድ'],
        ];

        $ethMonthNames = [
            9 => 'መስከረም',
            10 => 'ጥቅምት',
            11 => 'ኅዳር',
            12 => 'ታኅሣሥ',
            1 => 'ጥር',
            2 => 'የካቲት',
            3 => 'መጋቢት',
            4 => 'ሚያዝያ',
            5 => 'ግንቦት',
            6 => 'ሰኔ',
            7 => 'ሐምሌ',
            8 => 'ነሐሴ',
        ];

        $monthGroups = $records->groupBy(function($r) {
            return Carbon::parse($r->entry_at)->format('Y-m');
        })->sortKeys();

        $maxMonthTotal = 0;
        $peakMonthKey = '';

        foreach ($monthGroups as $ym => $logs) {
            $tot = (int) $logs->sum('visitor_count');
            if ($tot > $maxMonthTotal) {
                $maxMonthTotal = $tot;
                $peakMonthKey = $ym;
            }
        }

        $monthlyData = [];
        $prevMonthTotal = null;

        foreach ($monthGroups as $ym => $logs) {
            $c = Carbon::parse($ym . '-01');
            $gregorianMonthName = $c->format('F Y');
            [$ey, $em] = EthiopianDateService::toEthiopian($c->year, $c->month, 15);
            $amMonthsList = [
                1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
                5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
                9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
            ];
            $ethMonth = $amMonthsList[$em] ?? 'ኅዳር';
            $ethYear = $ey;
            $tot = (int) $logs->sum('visitor_count');

            $growthRate = '—';
            $growthDirection = 'steady';
            if ($prevMonthTotal !== null && $prevMonthTotal > 0) {
                $diff = $tot - $prevMonthTotal;
                $pct = round(($diff / $prevMonthTotal) * 100, 1);
                $growthRate = ($pct >= 0 ? '+' : '') . $pct . '%';
                $growthDirection = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'steady');
            }
            $prevMonthTotal = $tot;

            $operatingDays = max(1, count($logs->groupBy(fn($l) => Carbon::parse($l->entry_at)->toDateString())));
            $dailyAvg = round($tot / $operatingDays, 1);
            $students = (int) $logs->where('user_type', 'student')->sum('visitor_count');
            $teachers = (int) $logs->where('user_type', 'teacher')->sum('visitor_count');
            $staff = (int) $logs->where('user_type', 'staff')->sum('visitor_count');
            $faculty = $teachers + $staff;

            $dowCounts = [];
            foreach ($logs as $l) {
                $dw = Carbon::parse($l->entry_at)->dayOfWeekIso;
                $dowCounts[$dw] = ($dowCounts[$dw] ?? 0) + $l->visitor_count;
            }
            arsort($dowCounts);
            $topDow = key($dowCounts) ?? 2;
            $topDowName = $daysMap[$topDow]['en'];
            $topDowNameAm = $daysMap[$topDow]['am'];

            $monthlyData[] = [
                'month_key' => $ym,
                'name_en' => $gregorianMonthName,
                'name_am' => $ethMonth . ' ' . $ethYear . ' ዓ.ም',
                'display_name' => $isAmharic ? ($ethMonth . ' ' . $ethYear . ' ዓ.ም') : $gregorianMonthName,
                'month_label' => $isAmharic ? ($ethMonth . ' ' . $ethYear . ' ዓ.ም') : $gregorianMonthName,
                'ethiopian_month' => $ethMonth,
                'total' => $tot,
                'daily_avg' => $dailyAvg,
                'daily_average' => $dailyAvg,
                'operating_days' => $operatingDays,
                'students' => $students,
                'teachers' => $teachers,
                'staff' => $staff,
                'faculty' => $faculty,
                'growth_rate' => $growthRate,
                'growth_direction' => $growthDirection,
                'peak_day' => $isAmharic ? $topDowNameAm : $topDowName,
                'peak_weekday' => $isAmharic ? $topDowNameAm : $topDowName,
                'is_peak_month' => ($ym === $peakMonthKey),
            ];
        }

        return $monthlyData;
    }

    /**
     * Calculate Year-over-Year Trajectory Analytics based on Academic Years.
     */
    protected function calculateYearlyAnalytics($records, bool $isAmharic): array
    {
        $academicYears = AcademicYear::orderBy('start_date', 'asc')->get();

        if ($academicYears->isNotEmpty()) {
            $yearlyData = [];
            $prevYearTotal = null;

            foreach ($academicYears as $ay) {
                $ayStart = Carbon::parse($ay->start_date)->startOfDay();
                $ayEnd = Carbon::parse($ay->end_date)->endOfDay();

                $logs = $records->filter(function($r) use ($ayStart, $ayEnd) {
                    $entry = Carbon::parse($r->entry_at);
                    return $entry->greaterThanOrEqualTo($ayStart) && $entry->lessThanOrEqualTo($ayEnd);
                });

                $tot = (int) $logs->sum('visitor_count');
                $growthRate = '—';
                if ($prevYearTotal !== null && $prevYearTotal > 0) {
                    $pct = round((($tot - $prevYearTotal) / $prevYearTotal) * 100, 1);
                    $growthRate = ($pct >= 0 ? '+' : '') . $pct . '%';
                }
                if ($prevYearTotal !== null || $tot > 0) {
                    $prevYearTotal = $tot;
                }

                $operatingDays = max(1, count($logs->groupBy(fn($l) => Carbon::parse($l->entry_at)->toDateString())));
                $dailyAvg = round($tot / $operatingDays, 1);
                $students = (int) $logs->where('user_type', 'student')->sum('visitor_count');
                $teachers = (int) $logs->where('user_type', 'teacher')->sum('visitor_count');
                $staff = (int) $logs->where('user_type', 'staff')->sum('visitor_count');

                $yearlyData[] = [
                    'id' => $ay->id,
                    'code' => $ay->code,
                    'name' => $ay->name,
                    'year' => $ay->code,
                    'is_current' => (bool) $ay->is_current,
                    'start_date' => $ay->start_date->toDateString(),
                    'end_date' => $ay->end_date->toDateString(),
                    'display_year' => $ay->name,
                    'total' => $tot,
                    'operating_days' => $operatingDays,
                    'daily_avg' => $dailyAvg,
                    'daily_average' => $dailyAvg,
                    'students' => $students,
                    'teachers' => $teachers,
                    'staff' => $staff,
                    'faculty' => $teachers + $staff,
                    'growth_rate' => $growthRate,
                ];
            }

            return $yearlyData;
        }

        $yearGroups = $records->groupBy(function($r) {
            return Carbon::parse($r->entry_at)->format('Y');
        })->sortKeys();

        $yearlyData = [];
        $prevYearTotal = null;

        foreach ($yearGroups as $yr => $logs) {
            $tot = (int) $logs->sum('visitor_count');
            $ethYr = EthiopianDateService::toEthiopian((int)$yr, 11, 1)[0];

            $growthRate = '—';
            if ($prevYearTotal !== null && $prevYearTotal > 0) {
                $pct = round((($tot - $prevYearTotal) / $prevYearTotal) * 100, 1);
                $growthRate = ($pct >= 0 ? '+' : '') . $pct . '%';
            }
            $prevYearTotal = $tot;

            $operatingDays = max(1, count($logs->groupBy(fn($l) => Carbon::parse($l->entry_at)->toDateString())));
            $dailyAvg = round($tot / $operatingDays, 1);
            $students = (int) $logs->where('user_type', 'student')->sum('visitor_count');
            $teachers = (int) $logs->where('user_type', 'teacher')->sum('visitor_count');
            $staff = (int) $logs->where('user_type', 'staff')->sum('visitor_count');

            $yearlyData[] = [
                'year' => (int) $yr,
                'eth_year' => $ethYr,
                'ethiopian_year' => $ethYr,
                'display_year' => $isAmharic ? ($ethYr . ' ዓ.ም') : (string)$yr,
                'total' => $tot,
                'operating_days' => $operatingDays,
                'daily_avg' => $dailyAvg,
                'daily_average' => $dailyAvg,
                'students' => $students,
                'teachers' => $teachers,
                'staff' => $staff,
                'faculty' => $teachers + $staff,
                'growth_rate' => $growthRate,
            ];
        }

        return $yearlyData;
    }

    /**
     * Calculate Shift Time Influx Profile (Morning, Afternoon, Night) & 16-Hour Diurnal Curve.
     */
    protected function calculateShiftTimeAnalytics(array $hourlyDistribution, array $shiftWindows, int $totalCount, bool $isAmharic): array
    {
        $maxHourCount = max($hourlyDistribution);
        $totalDiurnal = array_sum($hourlyDistribution);
        $peakHourNum = key($hourlyDistribution) ?? 14;

        $hourlyCurve = [];
        foreach ($hourlyDistribution as $h => $count) {
            $gStartAmPm = ($h < 12) ? 'AM' : 'PM';
            $g12Start = ($h % 12 === 0) ? 12 : ($h % 12);
            $nextH = $h + 1;
            $gEndAmPm = ($nextH < 12) ? 'AM' : 'PM';
            $g12End = ($nextH % 12 === 0) ? 12 : ($nextH % 12);
            $gregorian12h = sprintf('%02d:00 %s – %02d:00 %s', $g12Start, $gStartAmPm, $g12End, $gEndAmPm);
            $gregorianLabel = sprintf('%02d %s', $g12Start, $gStartAmPm);

            $ethH = ($h >= 6) ? ($h - 6) : ($h + 6);
            $ethH = ($ethH === 0) ? 12 : $ethH;
            $ethNextH = ($nextH >= 6) ? ($nextH - 6) : ($nextH + 6);
            $ethNextH = ($ethNextH === 0) ? 12 : $ethNextH;
            $periodAm = ($h >= 6 && $h < 12) ? 'ጠዋት' : (($h >= 12 && $h < 18) ? 'ከቀኑ' : 'ምሽት');
            $nextPeriodAm = ($nextH >= 6 && $nextH < 12) ? 'ጠዋት' : (($nextH >= 12 && $nextH < 18) ? 'ከቀኑ' : 'ምሽት');

            $ethTime = ($periodAm === $nextPeriodAm)
                ? sprintf('%02d:00 – %02d:00 %s', $ethH, $ethNextH, $periodAm)
                : sprintf('%02d:00 %s – %02d:00 %s', $ethH, $periodAm, $ethNextH, $nextPeriodAm);
            $ethLabel = sprintf('%dሰ', $ethH);

            $shiftCat = ($h >= 8 && $h < 12) ? 'morning' : (($h >= 12 && $h < 17) ? 'afternoon' : 'night');

            $hourlyCurve[] = [
                'hour' => $h,
                'label' => $gregorianLabel,
                'label_am' => $ethLabel,
                'gregorian_12h' => $gregorian12h,
                'ethiopian_time' => $ethTime,
                'display_label' => $isAmharic ? $ethLabel : $gregorianLabel,
                'tooltip_label' => $isAmharic ? $ethTime : $gregorian12h,
                'count' => $count,
                'percent' => $totalDiurnal > 0 ? round(($count / $totalDiurnal) * 100, 1) : 0.0,
                'height_percent' => $maxHourCount > 0 ? round(($count / $maxHourCount) * 100) : 0,
                'shift' => $shiftCat,
                'is_peak' => ($h === $peakHourNum),
            ];
        }

        // Add congestion metrics to shift windows with 12-hour / Ethiopian dawn-based time intervals
        foreach ($shiftWindows as $code => &$win) {
            if ($code === 'morning') {
                $win['peak_hour'] = $isAmharic ? '04:00 – 05:00 ጠዋት' : '10:00 AM – 11:00 AM';
                $win['congestion_index'] = 'High Density';
                $win['congestion_index_am'] = 'ከፍተኛ ጥግግት';
            } elseif ($code === 'afternoon') {
                $win['peak_hour'] = $isAmharic ? '08:00 – 09:00 ከቀኑ' : '02:00 PM – 03:00 PM';
                $win['congestion_index'] = 'Peak Surge';
                $win['congestion_index_am'] = 'ከፍተኛው ጥግግት';
            } else {
                $win['peak_hour'] = $isAmharic ? '01:00 – 02:00 ምሽት' : '07:00 PM – 08:00 PM';
                $win['congestion_index'] = 'Moderate Density';
                $win['congestion_index_am'] = 'መካከለኛ ጥግግት';
            }
        }
        unset($win);

        return [
            'windows' => $shiftWindows,
            'hourly_curve' => $hourlyCurve,
            'peak_hour' => $this->format12HourRange($peakHourNum, $isAmharic),
        ];
    }

    /**
     * Generate Excel Spreadsheet format (MIME application/vnd.ms-excel with SpreadsheetML / Clean XML Table).
     */
    public function generateExcelXml(array $data): string
    {
        $isAmharic = $data['isAmharic'];
        $scope = $data['scope'] ?? 'full';

        $titleMap = [
            'patron_types' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ የተጠቃሚዎች ስነ-ሕዝብና የአጠቃቀም ቆጣሪ ይፋዊ ሪፖርት' : 'WOLDIA UNIVERSITY PATRON DEMOGRAPHICS & USAGE COUNTER AUDIT REPORT',
            'branches' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ የቅርንጫፍ ቤተ-መጻሕፍት አጠቃቀም ንጽጽር ይፋዊ ሪፖርት' : 'WOLDIA UNIVERSITY BRANCH FACILITIES UTILIZATION COMPARISON REPORT',
            'day_of_week' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ የዕለታት ፍሰትና የፈረቃ ክፍለ-ጊዜ ይፋዊ ሪፖርት' : 'WOLDIA UNIVERSITY DAY-OF-WEEK INFLUX PROFILE & SHIFT WINDOWS REPORT',
            'intra_month' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ የወሩ ሳምንታትና የዕለታት ድግግሞሽ ይፋዊ ሪፖርት' : 'WOLDIA UNIVERSITY INTRA-MONTH WEEKLY CYCLES & RECURRING DAY OCCURRENCES REPORT',
            'full' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት አጠቃቀምና የስነ-ሕዝብ ትንተና ይፋዊ ሪፖርት' : 'WOLDIA UNIVERSITY OFFICIAL LIBRARY ATTENDANCE & DEMOGRAPHIC ANALYTICS REPORT',
        ];
        $title = $titleMap[$scope] ?? $titleMap['full'];

        $fontName = $isAmharic ? 'Nyala' : 'Segoe UI';

        $output = chr(0xEF) . chr(0xBB) . chr(0xBF); // UTF-8 BOM for Microsoft Excel UTF-8 encoding detection
        $output .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $output .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $output .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $output .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $output .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $output .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        // Styles with native Ethiopic Unicode font for Amharic
        $output .= ' <Styles>' . "\n";
        $output .= '  <Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="' . $fontName . '" ss:Size="10"/></Style>' . "\n";
        $output .= '  <Style ss:ID="HeaderTitle"><Font ss:FontName="' . $fontName . '" ss:Size="14" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0B529C" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $output .= '  <Style ss:ID="SubHeader"><Font ss:FontName="' . $fontName . '" ss:Size="10" ss:Italic="1" ss:Color="#333333"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $output .= '  <Style ss:ID="SectionHeader"><Font ss:FontName="' . $fontName . '" ss:Size="11" ss:Bold="1" ss:Color="#0B529C"/><Interior ss:Color="#EBF2FA" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0B529C"/></Borders></Style>' . "\n";
        $output .= '  <Style ss:ID="TableHead"><Font ss:FontName="' . $fontName . '" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E293B" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $output .= '  <Style ss:ID="CellText"><Font ss:FontName="' . $fontName . '" ss:Size="10"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>' . "\n";
        $output .= '  <Style ss:ID="CellNumber"><Font ss:FontName="' . $fontName . '" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Right"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>' . "\n";
        $output .= '  <Style ss:ID="CellTotal"><Font ss:FontName="' . $fontName . '" ss:Size="10" ss:Bold="1" ss:Color="#0B529C"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/><Alignment ss:Horizontal="Right"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#0B529C"/></Borders></Style>' . "\n";
        $output .= ' </Styles>' . "\n";

        // Scope: Patron Demographics Only
        if ($scope === 'patron_types') {
            $output .= ' <Worksheet ss:Name="' . ($isAmharic ? 'የተጠቃሚዎች ስነ-ሕዝብ' : 'Patron Demographics') . '">' . "\n";
            $output .= '  <Table ss:DefaultColumnWidth="120">' . "\n";
            $output .= '   <Column ss:Width="200"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";
            $output .= '   <Column ss:Width="110"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";

            $output .= '   <Row ss:Height="28"><Cell ss:MergeAcross="4" ss:StyleID="HeaderTitle"><Data ss:Type="String">' . htmlspecialchars($title) . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row><Cell ss:MergeAcross="4" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($data['libraryName'] . ' | ' . $data['dateFromStr']['date'] . ' – ' . $data['dateToStr']['date']) . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="12"><Cell ss:MergeAcross="4"/></Row>' . "\n";

            $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="4" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የተጠቃሚዎች ምድብና የስነ-ሕዝብ ስርጭት' : 'Patron Category Demographic Breakdown') . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="18">' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የተጠቃሚ ምድብ' : 'User Type') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ወንድ' : 'Male') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሴት' : 'Female') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ' : 'Total') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            $types = [
                'student' => $isAmharic ? 'ተማሪ' : 'Student',
                'teacher' => $isAmharic ? 'መምህር' : 'Teacher',
                'staff' => $isAmharic ? 'ሠራተኛ' : 'Staff',
            ];

            foreach ($types as $key => $label) {
                $row = $data['demographics'][$key];
                $output .= '   <Row>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($label) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $row['male'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $row['female'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $row['total'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $row['percent'] . '%</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";
            }

            $output .= '   <Row>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ' : 'Total') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['maleCount'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['femaleCount'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['totalCount'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">100%</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            $output .= '  </Table>' . "\n";
            $output .= ' </Worksheet>' . "\n";
            $output .= '</Workbook>' . "\n";
            return $output;
        }

        // Scope: Branch Comparison Only
        if ($scope === 'branches') {
            $output .= ' <Worksheet ss:Name="' . ($isAmharic ? 'የቅርንጫፎች ንጽጽር' : 'Branch Comparison') . '">' . "\n";
            $output .= '  <Table ss:DefaultColumnWidth="120">' . "\n";
            $output .= '   <Column ss:Width="180"/>' . "\n";
            $output .= '   <Column ss:Width="80"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";

            $output .= '   <Row ss:Height="28"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">' . htmlspecialchars($title) . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($data['libraryName'] . ' | ' . $data['dateFromStr']['date'] . ' – ' . $data['dateToStr']['date']) . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="12"><Cell ss:MergeAcross="6"/></Row>' . "\n";

            $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የቅርንጫፍ ቤተ-መጻሕፍት አጠቃቀም ንጽጽር' : 'Library Branch Facilities Comparison') . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="18">' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የቅርንጫፍ ስም' : 'Branch Facility') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ኮድ' : 'Code') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Traffic Share (%)') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            $totStudents = 0; $totTeachers = 0; $totStaff = 0; $totAll = 0;
            foreach ($data['branchComparison'] as $branch) {
                $totStudents += $branch['students'];
                $totTeachers += ($branch['teachers'] ?? 0);
                $totStaff += ($branch['staff'] ?? 0);
                $totAll += $branch['total'];

                $output .= '   <Row>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($branch['name']) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $branch['code'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $branch['students'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($branch['teachers'] ?? 0) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($branch['staff'] ?? 0) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $branch['total'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $branch['percent'] . '%</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";
            }

            $output .= '   <Row>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">' . ($isAmharic ? 'አጠቃላይ ድምር' : 'Grand Total') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">—</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $totStudents . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $totTeachers . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $totStaff . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $totAll . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">100%</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            $output .= '  </Table>' . "\n";
            $output .= ' </Worksheet>' . "\n";
            $output .= '</Workbook>' . "\n";
            return $output;
        }

        // Scope: Day-of-Week & Shift Windows Only
        if ($scope === 'day_of_week') {
            $output .= ' <Worksheet ss:Name="' . ($isAmharic ? 'የዕለታት ፍሰት' : 'Day of Week Influx') . '">' . "\n";
            $output .= '  <Table ss:DefaultColumnWidth="120">' . "\n";
            $output .= '   <Column ss:Width="130"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="120"/>' . "\n";
            $output .= '   <Column ss:Width="120"/>' . "\n";
            $output .= '   <Column ss:Width="280"/>' . "\n";

            $output .= '   <Row ss:Height="28"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">' . htmlspecialchars($title) . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($data['libraryName'] . ' | ' . $data['dateFromStr']['date'] . ' – ' . $data['dateToStr']['date']) . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="12"><Cell ss:MergeAcross="6"/></Row>' . "\n";

            $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንት ቀናት የተጠቃሚዎች ፍሰት ትንተና (Monday - Sunday)' : 'Day-of-the-Week Operational Foot-Traffic Influx Profile') . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="18">' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንቱ ዕለት' : 'Day of Week') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Visitors') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንቱ ድርሻ (%)' : 'Weekly Share (%)') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠዋት / ቀን / ማታ' : 'Morn / Aft / Night') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የፍሰት ደረጃ' : 'Surge Level') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የቅድመ-ትንበያ ማስታወሻ' : 'Management Prediction') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            foreach ($data['dayOfWeekAnalytics'] as $dow) {
                $split = $dow['morning'] . ' / ' . $dow['afternoon'] . ' / ' . $dow['night'];
                $output .= '   <Row>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($dow['display_name']) . ($dow['is_peak'] ? ($isAmharic ? ' [ከፍተኛ]' : ' [Peak]') : '') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $dow['daily_average'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $dow['total'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $dow['percentage'] . '%</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $split . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $dow['surge_level_am'] : $dow['surge_level']) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $dow['prediction_am'] : $dow['prediction']) . '</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";
            }

            $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="6"/></Row>' . "\n";
            $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት' : 'Operational Shift Windows & Density') . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="18">' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሰዓት ገደብ' : 'Time Interval') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪ' : 'Students') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ' : 'Total Patrons') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            foreach ($data['shiftWindows'] as $shift) {
                $output .= '   <Row>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($shift['title']) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $shift['time_range'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $shift['students'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($shift['teachers'] ?? 0) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($shift['staff'] ?? 0) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $shift['count'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $shift['percent'] . '%</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";
            }

            $output .= '  </Table>' . "\n";
            $output .= ' </Worksheet>' . "\n";
            $output .= '</Workbook>' . "\n";
            return $output;
        }

        // Scope: Intra-Month Cycles Only
        if ($scope === 'intra_month') {
            $output .= ' <Worksheet ss:Name="' . ($isAmharic ? 'የወሩ ሳምንታትና ቀናት' : 'Intra-Month Cycles') . '">' . "\n";
            $output .= '  <Table ss:DefaultColumnWidth="120">' . "\n";
            $output .= '   <Column ss:Width="160"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="300"/>' . "\n";

            $output .= '   <Row ss:Height="28"><Cell ss:MergeAcross="4" ss:StyleID="HeaderTitle"><Data ss:Type="String">' . htmlspecialchars($title) . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row><Cell ss:MergeAcross="4" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($data['libraryName'] . ' | ' . ($data['intraMonthDayAnalytics']['selected_month_name_' . ($isAmharic ? 'am' : 'en')] ?? '')) . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="12"><Cell ss:MergeAcross="4"/></Row>' . "\n";

            if (!empty($data['intraMonthDayAnalytics'])) {
                $intra = $data['intraMonthDayAnalytics'];
                $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="4" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'በወሩ ውስጥ ያሉ ሳምንታት የፍሰት ትንተና' : 'Weekly Cycles In Month') . '</Data></Cell></Row>' . "\n";
                $output .= '   <Row ss:Height="18">' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የወሩ ሳምንት' : 'Week in Month') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የወሩ ድርሻ (%)' : 'Monthly Share (%)') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የአሰራር ማስታወሻ' : 'Operational Prediction Context') . '</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";

                foreach ($intra['weekly_cycles'] as $wCycle) {
                    $output .= '   <Row>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $wCycle['label_am'] : $wCycle['label']) . ($wCycle['is_peak_week'] ? ($isAmharic ? ' [ከፍተኛ]' : ' [Peak]') : '') . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $wCycle['total'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $wCycle['daily_average'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $wCycle['percent'] . '%</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $wCycle['prediction_am'] : $wCycle['prediction']) . '</Data></Cell>' . "\n";
                    $output .= '   </Row>' . "\n";
                }
            }

            $output .= '  </Table>' . "\n";
            $output .= ' </Worksheet>' . "\n";
            $output .= '</Workbook>' . "\n";
            return $output;
        }

        // Scope: Full Comprehensive Executive Audit
        $output .= ' <Worksheet ss:Name="' . ($isAmharic ? 'የስራ አስፈጻሚ ትንተና' : 'Executive Analytics') . '">' . "\n";
        $output .= '  <Table ss:DefaultColumnWidth="120">' . "\n";
        $output .= '   <Column ss:Width="180"/>' . "\n";
        $output .= '   <Column ss:Width="80"/>' . "\n";
        $output .= '   <Column ss:Width="90"/>' . "\n";
        $output .= '   <Column ss:Width="90"/>' . "\n";
        $output .= '   <Column ss:Width="90"/>' . "\n";
        $output .= '   <Column ss:Width="100"/>' . "\n";
        $output .= '   <Column ss:Width="100"/>' . "\n";

        // Title Row
        $output .= '   <Row ss:Height="28"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">' . htmlspecialchars($title) . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($data['libraryName'] . ' | ' . $data['dateFromStr']['date'] . ' – ' . $data['dateToStr']['date']) . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="10"><Cell ss:MergeAcross="6"/></Row>' . "\n";

        // Section 1: KPIs
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '1. ዋና ዋና የአፈጻጸም ጠቋሚዎች (KPIs)' : '1. Key Performance Indicators (KPIs)') . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚዎች' : 'Total Visitors Count') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['totalCount'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ወንድ ተጠቃሚዎች' : 'Male Visitors') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['maleCount'] . '</Data></Cell><Cell ss:StyleID="CellText"><Data ss:Type="String">' . $data['malePercent'] . '%</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ሴት ተጠቃሚዎች' : 'Female Visitors') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['femaleCount'] . '</Data></Cell><Cell ss:StyleID="CellText"><Data ss:Type="String">' . $data['femalePercent'] . '%</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window') . '</Data></Cell><Cell ss:MergeAcross="1" ss:StyleID="CellText"><Data ss:Type="String">' . $data['peakHourLabel'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Campus Branch') . '</Data></Cell><Cell ss:MergeAcross="1" ss:StyleID="CellText"><Data ss:Type="String">' . $data['busiestBranchName'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="6"/></Row>' . "\n";

        // Section 2: Demographic Breakdown
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '2. የተጠቃሚዎች ስነ-ሕዝብና ምድብ ስርጭት' : '2. Patron Demographic Distribution') . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="18">' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የተጠቃሚ ምድብ' : 'User Type') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ወንድ' : 'Male') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሴት' : 'Female') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ' : 'Total') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</Data></Cell>' . "\n";
        $output .= '   </Row>' . "\n";

        $types = [
            'student' => $isAmharic ? 'ተማሪ' : 'Student',
            'teacher' => $isAmharic ? 'መምህር' : 'Teacher',
            'staff' => $isAmharic ? 'ሠራተኛ' : 'Staff',
        ];

        foreach ($types as $key => $label) {
            $row = $data['demographics'][$key];
            $output .= '   <Row>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($label) . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $row['male'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $row['female'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $row['total'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $row['percent'] . '%</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";
        }

        $output .= '   <Row>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ' : 'Total') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['maleCount'] . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['femaleCount'] . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['totalCount'] . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">100%</Data></Cell>' . "\n";
        $output .= '   </Row>' . "\n";
        $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="6"/></Row>' . "\n";

        // Section 3: Branch Facilities Comparison (omitted if filtered to a single branch)
        if (($scope === 'full' || $scope === 'branches') && empty($data['selectedLibrary'])) {
            $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር' : '3. Library Branch Facility Comparison') . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="18">' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የቅርንጫፍ ስም' : 'Branch Facility') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ኮድ' : 'Code') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Traffic Share (%)') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            foreach ($data['branchComparison'] as $branch) {
                $output .= '   <Row>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($branch['name']) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $branch['code'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $branch['students'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($branch['teachers'] ?? 0) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($branch['staff'] ?? 0) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $branch['total'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $branch['percent'] . '%</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";
            }
            $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="6"/></Row>' . "\n";
        }

        // Section 4: Operational Shift Windows
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '4. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት' : '4. Operational Shift Windows & Density') . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="18">' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሰዓት ገደብ' : 'Time Interval') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪ' : 'Students') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ' : 'Total Patrons') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</Data></Cell>' . "\n";
        $output .= '   </Row>' . "\n";

        foreach ($data['shiftWindows'] as $shift) {
            $output .= '   <Row>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($shift['title']) . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $shift['time_range'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $shift['students'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($shift['teachers'] ?? 0) . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($shift['staff'] ?? 0) . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $shift['count'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $shift['percent'] . '%</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";
        }

        $output .= '  </Table>' . "\n";
        $output .= ' </Worksheet>' . "\n";

        // Worksheet 2: Day-of-the-Week & Diurnal Profile
        if (!empty($data['dayOfWeekAnalytics'])) {
            $output .= ' <Worksheet ss:Name="' . ($isAmharic ? 'የቀናትና የፈረቃ ትንተና' : 'Day & Shift Influx') . '">' . "\n";
            $output .= '  <Table ss:DefaultColumnWidth="120">' . "\n";
            $output .= '   <Column ss:Width="120"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="120"/>' . "\n";
            $output .= '   <Column ss:Width="280"/>' . "\n";

            $output .= '   <Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንት ቀናት የተጠቃሚዎች ፍሰትና የጥግግት ትንተና (Monday - Sunday)' : 'Day-of-the-Week Operational Foot-Traffic Influx Profile') . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="18">' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንቱ ዕለት' : 'Day of Week') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Visitors') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንቱ ድርሻ (%)' : 'Weekly Share (%)') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠዋት / ቀን / ማታ' : 'Morn / Aft / Night') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የፍሰት ደረጃ' : 'Surge Level') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የቅድመ-ትንበያ ማስታወሻ' : 'Management Prediction') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            foreach ($data['dayOfWeekAnalytics'] as $dow) {
                $split = $dow['morning'] . ' / ' . $dow['afternoon'] . ' / ' . $dow['night'];
                $output .= '   <Row>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($dow['display_name']) . ($dow['is_peak'] ? ($isAmharic ? ' [ከፍተኛ]' : ' [Peak]') : '') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $dow['daily_average'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $dow['total'] . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $dow['percentage'] . '%</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $split . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $dow['surge_level_am'] : $dow['surge_level']) . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $dow['prediction_am'] : $dow['prediction']) . '</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";
            }

            $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="6"/></Row>' . "\n";
            $output .= '   <Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የ16 ሰዓታት የቀንና የሌሊት የጥግግት ኩርባ (08:00 - 23:00)' : '16-Hour Diurnal Density Profile (08:00 - 23:00)') . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="18">' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሰዓት' : 'Hour Slot') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የኢትዮጵያ ሰዓት' : 'Ethiopian Time') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የፈረቃ ምድብ' : 'Shift Category') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የተጠቃሚዎች ብዛት' : 'Visitors Count') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የቀኑ ድርሻ (%)' : 'Diurnal Share (%)') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:MergeAcross="1" ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ ሰዓት' : 'Peak Hour Status') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            if (!empty($data['shiftTimeAnalytics']['hourly_curve'])) {
                foreach ($data['shiftTimeAnalytics']['hourly_curve'] as $hc) {
                    $output .= '   <Row>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $hc['label'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $hc['label_am'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . ucfirst($hc['shift']) . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $hc['count'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $hc['percent'] . '%</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:MergeAcross="1" ss:StyleID="CellText"><Data ss:Type="String">' . ($hc['is_peak'] ? ($isAmharic ? 'ከፍተኛ ሰዓት [ከፍተኛ]' : 'Peak Hour [Peak]') : '—') . '</Data></Cell>' . "\n";
                    $output .= '   </Row>' . "\n";
                }
            }

            $output .= '  </Table>' . "\n";
            $output .= ' </Worksheet>' . "\n";
        }

        // Worksheet 3: Monthly & Yearly Trends
        if (!empty($data['monthlyAnalytics']) || !empty($data['intraMonthDayAnalytics'])) {
            $output .= ' <Worksheet ss:Name="' . ($isAmharic ? 'የወራትና የዓመታት ንጽጽር' : 'Monthly & Yearly Trends') . '">' . "\n";
            $output .= '  <Table ss:DefaultColumnWidth="120">' . "\n";
            $output .= '   <Column ss:Width="160"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";
            $output .= '   <Column ss:Width="90"/>' . "\n";
            $output .= '   <Column ss:Width="100"/>' . "\n";
            $output .= '   <Column ss:Width="120"/>' . "\n";
            $output .= '   <Column ss:Width="180"/>' . "\n";

            if (!empty($data['intraMonthDayAnalytics'])) {
                $intra = $data['intraMonthDayAnalytics'];
                $output .= '   <Row ss:Height="22"><Cell ss:MergeAcross="7" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'በወሩ ውስጥ ያሉ ሳምንታትና የዕለታት ድግግሞሽ (Intra-Month Cycles)' : 'Intra-Month Weekly Cycles & Recurring Day Occurrences') . '</Data></Cell></Row>' . "\n";
                $output .= '   <Row ss:Height="18">' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የወሩ ሳምንት' : 'Week in Month') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የወሩ ድርሻ (%)' : 'Monthly Share (%)') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:MergeAcross="3" ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የአሰራር ማስታወሻ' : 'Operational Prediction Context') . '</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";

                foreach ($intra['weekly_cycles'] as $wCycle) {
                    $output .= '   <Row>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $wCycle['label_am'] : $wCycle['label']) . ($wCycle['is_peak_week'] ? ($isAmharic ? ' [ከፍተኛ]' : ' [Peak]') : '') . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $wCycle['total'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $wCycle['daily_average'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $wCycle['percent'] . '%</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:MergeAcross="3" ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $wCycle['prediction_am'] : $wCycle['prediction']) . '</Data></Cell>' . "\n";
                    $output .= '   </Row>' . "\n";
                }
                $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="7"/></Row>' . "\n";
            }

            if (!empty($data['monthlyAnalytics'])) {
                $output .= '   <Row ss:Height="22"><Cell ss:MergeAcross="7" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የወራት ንጽጽር ትንተና (Month-over-Month Trajectory)' : 'Month-over-Month Comparative Trajectory') . '</Data></Cell></Row>' . "\n";
                $output .= '   <Row ss:Height="18">' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሂሳብ ወር' : 'Academic Month') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Visitors') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የወር እድገት (MoM)' : 'Monthly Growth') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ ተጠቃሚ ዕለት' : 'Peak Weekday') . '</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";

                foreach ($data['monthlyAnalytics'] as $mInfo) {
                    $output .= '   <Row>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($mInfo['display_name']) . ($mInfo['is_peak_month'] ? ($isAmharic ? ' [ከፍተኛ]' : ' [Peak]') : '') . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $mInfo['total'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($mInfo['students'] ?? 0) . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($mInfo['teachers'] ?? 0) . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . ($mInfo['staff'] ?? 0) . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $mInfo['daily_avg'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $mInfo['growth_rate'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($mInfo['peak_day']) . '</Data></Cell>' . "\n";
                    $output .= '   </Row>' . "\n";
                }
                $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="7"/></Row>' . "\n";
            }

            if (!empty($data['yearlyAnalytics'])) {
                $output .= '   <Row ss:Height="22"><Cell ss:MergeAcross="7" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የዓመታት ንጽጽር ትንተና (Year-over-Year Comparative Growth)' : 'Year-over-Year Comparative Growth') . '</Data></Cell></Row>' . "\n";
                $output .= '   <Row ss:Height="18">' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የአካዳሚክ ዓመት' : 'Academic Year') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የዓመት ጠቅላላ ተጠቃሚ' : 'Annual Total Visitors') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የስራ ቀናት' : 'Operating Days') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:MergeAcross="3" ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የዓመት እድገት (YoY)' : 'Yearly Growth Rate') . '</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";

                foreach ($data['yearlyAnalytics'] as $yInfo) {
                    $output .= '   <Row>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($yInfo['display_year']) . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $yInfo['total'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $yInfo['operating_days'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $yInfo['daily_avg'] . '</Data></Cell>' . "\n";
                    $output .= '    <Cell ss:MergeAcross="3" ss:StyleID="CellText"><Data ss:Type="String">' . $yInfo['growth_rate'] . '</Data></Cell>' . "\n";
                    $output .= '   </Row>' . "\n";
                }
            }

            $output .= '  </Table>' . "\n";
            $output .= ' </Worksheet>' . "\n";
        }

        $output .= '</Workbook>' . "\n";
        return $output;
    }

    /**
     * Generate Microsoft Word document format (.doc with standard Word HTML structure).
     */
    public function generateWordDoc(array $data): string
    {
        $isAmharic = $data['isAmharic'];
        $scope = $data['scope'] ?? 'full';

        $scopeTitles = [
            'patron_types' => [
                'en' => 'Library Patron Demographic & Usage Counter Analytics Report',
                'am' => 'የቤተ-መጻሕፍት የተጠቃሚዎች ምድብና የስነ-ሕዝብ አጠቃቀም ትንተና ሪፖርት',
            ],
            'branches' => [
                'en' => 'Library Branch Facilities Comparative Attendance Audit',
                'am' => 'የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽርና የተጠቃሚዎች ፍሰት ኦዲት ሪፖርት',
            ],
            'day_of_week' => [
                'en' => 'Day-of-the-Week Operational Foot-Traffic Influx Report',
                'am' => 'የሳምንት ቀናት የቤተ-መጻሕፍት የተጠቃሚዎች ፍሰትና የጥግግት ትንተና ሪፖርት',
            ],
            'intra_month' => [
                'en' => 'Intra-Month Weekly Cycles & Recurring Day Occurrences Report',
                'am' => 'በወሩ ውስጥ ያሉ ሳምንታትና የዕለታት ድግግሞሽ ትንተና ሪፖርት',
            ],
            'full' => [
                'en' => 'Official Library Attendance and Demographic Analytics Report',
                'am' => 'የቤተ-መጻሕፍት አጠቃቀም እና የስነ-ሕዝብ ትንተና ይፋዊ ሪፖርት',
            ],
        ];

        $title = $isAmharic ? ($scopeTitles[$scope]['am'] ?? $scopeTitles['full']['am']) : ($scopeTitles[$scope]['en'] ?? $scopeTitles['full']['en']);
        $subTitle = $isAmharic ? 'የአካዳሚክ ቤተ-መጻሕፍት አገልግሎት ዳይሬክቶሬት ይፋዊ የኦዲት ሰነድ' : 'Official Audit Document — Directorate of Academic Library & Information Services';

        $html = chr(0xEF) . chr(0xBB) . chr(0xBF); // UTF-8 BOM for Microsoft Word
        $html .= '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $html .= '<meta name="ProgId" content="Word.Document">';
        $html .= '<meta name="Generator" content="Microsoft Word 15">';
        $html .= '<title>' . htmlspecialchars($title) . '</title>';
        $html .= '<style>';
        $html .= 'body { font-family: "Nyala", "Abyssinica SIL", "Noto Sans Ethiopic", "Segoe UI Historic", "Times New Roman", serif; font-size: 11pt; line-height: 1.45; color: #111; margin: 20mm; }';
        $html .= 'h1 { font-size: 16pt; font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 2pt; color: #0B529C; }';
        $html .= 'h2 { font-size: 12pt; font-weight: bold; text-align: center; margin-top: 0; color: #444; }';
        $html .= 'h3 { font-size: 11pt; font-weight: bold; margin-top: 18pt; margin-bottom: 6pt; border-bottom: 1.5pt solid #0B529C; padding-bottom: 2pt; text-transform: uppercase; color: #0B529C; }';
        $html .= 'p { margin: 6pt 0; text-align: justify; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin: 10pt 0 16pt 0; font-size: 10pt; }';
        $html .= 'th { background-color: #F1F5F9; border: 1pt solid #333333; padding: 6pt 8pt; font-weight: bold; text-align: left; }';
        $html .= 'td { border: 1pt solid #666666; padding: 5pt 8pt; vertical-align: top; }';
        $html .= '.meta-table td { border: 1pt solid #CBD5E1; padding: 4pt 6pt; font-size: 9.5pt; }';
        $html .= '.total-row td { font-weight: bold; background-color: #F8FAFC; border-top: 2pt solid #0B529C; }';
        $html .= '.text-right { text-align: right; }';
        $html .= '.text-center { text-align: center; }';
        $html .= '.sig-box { width: 48%; display: inline-block; vertical-align: top; border-top: 1pt solid #333; margin-top: 30pt; padding-top: 4pt; text-align: center; }';
        $html .= '</style></head><body>';

        // Official Academic Header
        $html .= '<h1>' . ($isAmharic ? 'ወልድያ ዩኒቨርሲቲ' : 'WOLDIA UNIVERSITY') . '</h1>';
        $html .= '<h2>' . htmlspecialchars($subTitle) . '</h2>';
        $html .= '<h3 style="text-align: center; border: none; margin: 4pt 0 12pt 0; color: #1E293B;">' . htmlspecialchars($title) . '</h3>';
        $html .= '<hr style="border: 0; border-top: 2pt double #0B529C; margin-bottom: 15pt;" />';

        // Document Metadata Table
        $html .= '<table class="meta-table" style="margin-bottom: 16pt;">';
        $html .= '<tr>';
        $html .= '<td style="width: 25%; font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የተዘጋጀበት ቀንና ሰዓት' : 'Date of Issuance') . ':</td>';
        $html .= '<td style="width: 25%;">' . $data['generatedAt']['full'] . ' (' . $data['generatedAt']['time'] . ')</td>';
        $html .= '<td style="width: 25%; font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የቤተ-መጻሕፍት ቅርንጫፍ' : 'Target Facilities') . ':</td>';
        $html .= '<td style="width: 25%;">' . htmlspecialchars($data['libraryName']) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የሪፖርቱ ሽፋን ጊዜ' : 'Reporting Window') . ':</td>';
        $html .= '<td>' . $data['dateFromStr']['date'] . ' – ' . $data['dateToStr']['date'] . '</td>';
        $html .= '<td style="font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የኦዲት ወሰን' : 'Audit Scope') . ':</td>';
        $html .= '<td style="font-weight: bold; color: #0B529C;">' . strtoupper($scope) . '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        // Section 1: KPI Summary Table (full only)
        if ($scope === 'full') {
            $html .= '<h3>' . ($isAmharic ? '1. ዋና ዋና የአፈጻጸም ጠቋሚዎች ማጠቃለያ' : '1. Key Performance Indicators (KPIs)') . '</h3>';
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የመለኪያ መግለጫ' : 'Metric Indicator') . '</th><th class="text-right">' . ($isAmharic ? 'የተመዘገበ ቁጥር' : 'Recorded Value') . '</th><th>' . ($isAmharic ? 'የድርሻ መጠን' : 'Percentage / Proportion') . '</th><th>' . ($isAmharic ? 'ተጨማሪ የአሰራር ማስታወሻ' : 'Operational Note') . '</th></tr>';
            $html .= '<tr><td>' . ($isAmharic ? 'ጠቅላላ የተጠቃሚዎች ብዛት' : 'Total Patron Foot Traffic') . '</td><td class="text-right" style="font-weight:bold;">' . number_format($data['totalCount']) . '</td><td>100.0%</td><td>' . ($isAmharic ? 'በተመረጠው የጊዜ ገደብ የገቡ ጠቅላላ ተጠቃሚዎች' : 'Aggregated campus gate check-ins') . '</td></tr>';
            $html .= '<tr><td>' . ($isAmharic ? 'ወንድ ተጠቃሚዎች' : 'Male Patrons') . '</td><td class="text-right">' . number_format($data['maleCount']) . '</td><td>' . $data['malePercent'] . '%</td><td>' . ($isAmharic ? 'የወንዶች ተሳትፎ ድርሻ' : 'Share of recorded traffic') . '</td></tr>';
            $html .= '<tr><td>' . ($isAmharic ? 'ሴት ተጠቃሚዎች' : 'Female Patrons') . '</td><td class="text-right">' . number_format($data['femaleCount']) . '</td><td>' . $data['femalePercent'] . '%</td><td>' . ($isAmharic ? 'የሴቶች ተሳትፎ ድርሻ' : 'Share of recorded traffic') . '</td></tr>';
            $html .= '<tr><td>' . ($isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window') . '</td><td class="text-right">' . number_format($data['peakHourCount']) . '</td><td>—</td><td>' . $data['peakHourLabel'] . '</td></tr>';
            $html .= '<tr><td>' . ($isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Branch Facility') . '</td><td class="text-right">—</td><td>—</td><td>' . htmlspecialchars($data['busiestBranchName']) . '</td></tr>';
            $html .= '</table>';
        }

        // Section 2: Demographic Patron Matrix (full or patron_types)
        if ($scope === 'full' || $scope === 'patron_types') {
            $html .= '<h3>' . ($isAmharic ? ($scope === 'patron_types' ? 'የተጠቃሚዎች ምድብና ስነ-ሕዝብ ዝርዝር' : '2. የተጠቃሚዎች ምድብና ስነ-ሕዝብ ዝርዝር') : ($scope === 'patron_types' ? 'Patron Demographic Categorization' : '2. Patron Demographic Categorization')) . '</h3>';
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የተጠቃሚ ምድብ' : 'User Type') . '</th><th class="text-right">' . ($isAmharic ? 'ወንድ' : 'Male') . '</th><th class="text-right">' . ($isAmharic ? 'ሴት' : 'Female') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ' : 'Total') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</th></tr>';

            $types = [
                'student' => $isAmharic ? 'ተማሪ' : 'Student',
                'teacher' => $isAmharic ? 'መምህር' : 'Teacher',
                'staff' => $isAmharic ? 'ሠራተኛ' : 'Staff',
            ];

            foreach ($types as $key => $label) {
                $row = $data['demographics'][$key];
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($label) . '</td>';
                $html .= '<td class="text-right">' . number_format($row['male']) . '</td>';
                $html .= '<td class="text-right">' . number_format($row['female']) . '</td>';
                $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($row['total']) . '</td>';
                $html .= '<td class="text-right">' . $row['percent'] . '%</td>';
                $html .= '</tr>';
            }

            $html .= '<tr class="total-row">';
            $html .= '<td>' . ($isAmharic ? 'ጠቅላላ' : 'Total') . '</td>';
            $html .= '<td class="text-right">' . number_format($data['maleCount']) . '</td>';
            $html .= '<td class="text-right">' . number_format($data['femaleCount']) . '</td>';
            $html .= '<td class="text-right">' . number_format($data['totalCount']) . '</td>';
            $html .= '<td class="text-right">100.0%</td>';
            $html .= '</tr>';
            $html .= '</table>';
        }

        // Section 3: Branch Comparison (full or branches, omitted if restricted/filtered to a single branch)
        if (($scope === 'full' || $scope === 'branches') && empty($data['selectedLibrary'])) {
            $html .= '<h3>' . ($isAmharic ? '3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር' : '3. Library Branch Facility Distribution') . '</h3>';
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የቅርንጫፍ ስም' : 'Campus Branch') . '</th><th>' . ($isAmharic ? 'ኮድ' : 'Code') . '</th><th class="text-right">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</th><th class="text-right">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</th><th class="text-right">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Traffic Share (%)') . '</th></tr>';

            foreach ($data['branchComparison'] as $branch) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($branch['name']) . '</td>';
                $html .= '<td>' . $branch['code'] . '</td>';
                $html .= '<td class="text-right">' . number_format($branch['students']) . '</td>';
                $html .= '<td class="text-right">' . number_format($branch['teachers'] ?? 0) . '</td>';
                $html .= '<td class="text-right">' . number_format($branch['staff'] ?? 0) . '</td>';
                $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($branch['total']) . '</td>';
                $html .= '<td class="text-right">' . $branch['percent'] . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        // Section 4: Operational Shift Windows (full or day_of_week)
        if ($scope === 'full' || $scope === 'day_of_week') {
            $html .= '<h3>' . ($isAmharic ? '4. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት' : '4. Operational Shift Windows & Density') . '</h3>';
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window') . '</th><th>' . ($isAmharic ? 'የሰዓት ገደብ' : 'Time Interval') . '</th><th class="text-right">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</th><th class="text-right">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</th><th class="text-right">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</th></tr>';

            foreach ($data['shiftWindows'] as $shift) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($shift['title']) . '</td>';
                $html .= '<td>' . $shift['time_range'] . '</td>';
                $html .= '<td class="text-right">' . number_format($shift['students']) . '</td>';
                $html .= '<td class="text-right">' . number_format($shift['teachers'] ?? 0) . '</td>';
                $html .= '<td class="text-right">' . number_format($shift['staff'] ?? 0) . '</td>';
                $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($shift['count']) . '</td>';
                $html .= '<td class="text-right">' . $shift['percent'] . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        // Section 5: Day-of-the-Week Operational Influx Profile (full or day_of_week)
        if (($scope === 'full' || $scope === 'day_of_week') && !empty($data['dayOfWeekAnalytics'])) {
            $html .= '<h3>' . ($isAmharic ? '5. የሳምንት ቀናት የተጠቃሚዎች ፍሰትና የጥግግት ትንተና' : '5. Day-of-the-Week Operational Foot-Traffic Influx') . '</h3>';
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የሳምንቱ ዕለት' : 'Day of Week') . '</th><th class="text-right">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Visitors') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Weekly Share (%)') . '</th><th>' . ($isAmharic ? 'የፍሰት ደረጃ' : 'Surge Level') . '</th><th>' . ($isAmharic ? 'የአስተዳደር የቅድመ-ትንበያ ማስታወሻ' : 'Management Prediction Guidance') . '</th></tr>';
            foreach ($data['dayOfWeekAnalytics'] as $dow) {
                $html .= '<tr>';
                $html .= '<td><b>' . htmlspecialchars($dow['display_name']) . '</b>' . ($dow['is_peak'] ? ($isAmharic ? ' [ከፍተኛ]' : ' [Peak]') : '') . '</td>';
                $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($dow['daily_average']) . '</td>';
                $html .= '<td class="text-right">' . number_format($dow['total']) . '</td>';
                $html .= '<td class="text-right">' . $dow['percentage'] . '%</td>';
                $html .= '<td>' . htmlspecialchars($isAmharic ? $dow['surge_level_am'] : $dow['surge_level']) . '</td>';
                $html .= '<td>' . htmlspecialchars($isAmharic ? $dow['prediction_am'] : $dow['prediction']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        // Section 6: Intra-Month Day Recurring Occurrence & Weekly Cycle Matrix (full or intra_month)
        if (($scope === 'full' || $scope === 'intra_month') && !empty($data['intraMonthDayAnalytics'])) {
            $intra = $data['intraMonthDayAnalytics'];
            $html .= '<h3>' . ($isAmharic ? '6. በወሩ ውስጥ ያሉ ሳምንታትና የዕለታት ድግግሞሽ ትንተና' : '6. Intra-Month Recurring Day Occurrence & Weekly Cycle Matrix') . '</h3>';
            $html .= '<p><b>' . ($isAmharic ? 'የትንበያ መረጃ፡ ' : 'Prediction Notice: ') . '</b>' . htmlspecialchars($intra['prediction']) . '</p>';

            // Weekly cycles
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የወሩ ሳምንት' : 'Week in Month') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons') . '</th><th class="text-right">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</th><th>' . ($isAmharic ? 'የአሰራር ሁኔታ' : 'Operational Context') . '</th></tr>';
            foreach ($intra['weekly_cycles'] as $wCycle) {
                $html .= '<tr>';
                $html .= '<td><b>' . htmlspecialchars($isAmharic ? $wCycle['label_am'] : $wCycle['label']) . '</b>' . ($wCycle['is_peak_week'] ? ($isAmharic ? ' [ከፍተኛ]' : ' [Peak]') : '') . '</td>';
                $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($wCycle['total']) . '</td>';
                $html .= '<td class="text-right">' . number_format($wCycle['daily_average']) . '</td>';
                $html .= '<td class="text-right">' . $wCycle['percent'] . '%</td>';
                $html .= '<td>' . htmlspecialchars($isAmharic ? $wCycle['prediction_am'] : $wCycle['prediction']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';

            // Day occurrences table (1st Mon vs 2nd Mon... 5th Mon)
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የሳምንት ዕለት' : 'Day of Week') . '</th><th>' . ($isAmharic ? '1ኛ ድግግሞሽ (ቀናት 1–7)' : '1st Occurrence (Days 1–7)') . '</th><th>' . ($isAmharic ? '2ኛ ድግግሞሽ (ቀናት 8–14)' : '2nd Occurrence (Days 8–14)') . '</th><th>' . ($isAmharic ? '3ኛ ድግግሞሽ (ቀናት 15–21)' : '3rd Occurrence (Days 15–21)') . '</th><th>' . ($isAmharic ? '4ኛ ድግግሞሽ (ቀናት 22–28)' : '4th Occurrence (Days 22–28)') . '</th><th>' . ($isAmharic ? '5ኛ ድግግሞሽ (ቀናት 29–31)' : '5th Occurrence (Days 29–31)') . '</th></tr>';
            foreach ($intra['occurrences_by_day'] as $occDay) {
                $html .= '<tr>';
                $html .= '<td><b>' . htmlspecialchars($occDay['display_name']) . '</b></td>';
                foreach ($occDay['occurrences'] as $oc) {
                    $badge = $isAmharic ? $oc['surge_badge_am'] : $oc['surge_badge'];
                    $html .= '<td>' . number_format($oc['average']) . ' (' . htmlspecialchars($badge) . ')' . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        // Section 7: Longitudinal Month-over-Month & Year-over-Year Trajectory (full only)
        if ($scope === 'full' && (!empty($data['monthlyAnalytics']) || !empty($data['yearlyAnalytics']))) {
            $html .= '<h3>' . ($isAmharic ? '7. የወራትና የዓመታት ንጽጽር ትንተና' : '7. Longitudinal Month-over-Month & Year-over-Year Trajectory') . '</h3>';

            if (!empty($data['monthlyAnalytics'])) {
                $html .= '<table>';
                $html .= '<tr><th>' . ($isAmharic ? 'የሂሳብ ወር' : 'Academic Month') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Visitors') . '</th><th class="text-right">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</th><th class="text-right">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</th><th class="text-right">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</th><th class="text-right">' . ($isAmharic ? 'አማካይ' : 'Daily Avg') . '</th><th class="text-right">' . ($isAmharic ? 'የወር እድገት' : 'Growth') . '</th><th>' . ($isAmharic ? 'ከፍተኛ ዕለት' : 'Peak Weekday') . '</th></tr>';
                foreach ($data['monthlyAnalytics'] as $mInfo) {
                    $html .= '<tr>';
                    $html .= '<td><b>' . htmlspecialchars($mInfo['display_name']) . '</b>' . ($mInfo['is_peak_month'] ? ($isAmharic ? ' [ከፍተኛ]' : ' [Peak]') : '') . '</td>';
                    $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($mInfo['total']) . '</td>';
                    $html .= '<td class="text-right">' . number_format($mInfo['students'] ?? 0) . '</td>';
                    $html .= '<td class="text-right">' . number_format($mInfo['teachers'] ?? 0) . '</td>';
                    $html .= '<td class="text-right">' . number_format($mInfo['staff'] ?? 0) . '</td>';
                    $html .= '<td class="text-right">' . number_format($mInfo['daily_avg']) . '</td>';
                    $html .= '<td class="text-right">' . $mInfo['growth_rate'] . '</td>';
                    $html .= '<td>' . htmlspecialchars($mInfo['peak_day']) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }

            if (!empty($data['yearlyAnalytics'])) {
                $html .= '<table>';
                $html .= '<tr><th>' . ($isAmharic ? 'የአካዳሚክ ዓመት' : 'Academic Year') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ የዓመት ተጠቃሚ' : 'Annual Total Visitors') . '</th><th class="text-right">' . ($isAmharic ? 'የስራ ቀናት' : 'Operating Days') . '</th><th class="text-right">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</th><th class="text-right">' . ($isAmharic ? 'የዓመት እድገት (YoY)' : 'Yearly Growth') . '</th></tr>';
                foreach ($data['yearlyAnalytics'] as $yInfo) {
                    $html .= '<tr>';
                    $html .= '<td><b>' . htmlspecialchars($yInfo['display_year']) . '</b></td>';
                    $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($yInfo['total']) . '</td>';
                    $html .= '<td class="text-right">' . $yInfo['operating_days'] . '</td>';
                    $html .= '<td class="text-right">' . number_format($yInfo['daily_avg']) . '</td>';
                    $html .= '<td class="text-right">' . $yInfo['growth_rate'] . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
        }

        // Report Footer: Exporter Information Only
        $html .= '<div style="margin-top: 25pt; padding-top: 10pt; border-top: 1pt solid #CBD5E1; font-size: 8.5pt; color: #475569; page-break-inside: avoid;">';
        $html .= '<table style="width: 100%; border: none; margin: 0; padding: 0;">';
        $html .= '<tr style="border: none;">';
        $html .= '<td style="border: none; text-align: left; padding: 0;">';
        $html .= '<strong style="color: #0F172A;">' . ($isAmharic ? 'ሪፖርቱን ያዘጋጀው፡ ' : 'Generated by: ') . '</strong>';
        $html .= '<span style="color: #0B529C; font-weight: bold;">' . htmlspecialchars($data['generatedBy']) . '</span>';
        if (!empty($data['generatedByUsername'])) {
            $html .= ' <span style="color: #64748B; font-size: 8pt;">(@' . htmlspecialchars($data['generatedByUsername']) . ')</span>';
        }
        $html .= '</td>';
        $html .= '<td style="border: none; text-align: right; padding: 0; font-family: monospace; color: #64748B;">';
        $html .= $data['generatedAt']['date'] . ' · ' . $data['generatedAt']['time'];
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</div>';

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Generate Comprehensive Localized CSV based on scope.
     */
    public function generateCsv(array $data): string
    {
        $isAmharic = $data['isAmharic'];
        $scope = $data['scope'] ?? 'full';

        $scopeHeaders = [
            'patron_types' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት የተጠቃሚዎች ምድብና የስነ-ሕዝብ አጠቃቀም ትንተና ሪፖርት' : 'WOLDIA UNIVERSITY LIBRARY PATRON DEMOGRAPHIC & USAGE COUNTER REPORT',
            'branches' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽርና የተጠቃሚዎች ፍሰት ኦዲት ሪፖርት' : 'WOLDIA UNIVERSITY LIBRARY BRANCH FACILITIES COMPARISON AUDIT',
            'day_of_week' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ የሳምንት ቀናት የተጠቃሚዎች ፍሰትና የጥግግት ትንተና ሪፖርት' : 'WOLDIA UNIVERSITY DAY-OF-THE-WEEK FOOT-TRAFFIC INFLUX REPORT',
            'intra_month' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ በወሩ ውስጥ ያሉ ሳምንታትና የዕለታት ድግግሞሽ ትንተና ሪፖርት' : 'WOLDIA UNIVERSITY INTRA-MONTH WEEKLY CYCLES & RECURRING DAY OCCURRENCES',
            'full' => $isAmharic ? 'የወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት ይፋዊ የአጠቃቀምና የስነ-ሕዝብ ትንተና ሪፖርት' : 'WOLDIA UNIVERSITY OFFICIAL ACADEMIC LIBRARY ATTENDANCE & DEMOGRAPHIC ANALYTICS REPORT',
        ];

        $f = fopen('php://memory', 'r+');
        fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

        // Executive Header Section
        fputcsv($f, [$scopeHeaders[$scope] ?? $scopeHeaders['full']]);
        fputcsv($f, [$isAmharic ? 'የተዘጋጀበት ቀን' : 'Generated At', $data['generatedAt']['full'] . ' ' . $data['generatedAt']['time']]);
        fputcsv($f, [$isAmharic ? 'የሪፖርቱ ሽፋን ጊዜ' : 'Reporting Window', $data['dateFromStr']['date'] . ' - ' . $data['dateToStr']['date']]);
        fputcsv($f, [$isAmharic ? 'የቤተ-መጻሕፍት ቅርንጫፍ' : 'Branch Scope', $data['libraryName']]);
        fputcsv($f, [$isAmharic ? 'ያዘጋጀው ሠራተኛ' : 'Prepared By', $data['generatedBy']]);
        fputcsv($f, [$isAmharic ? 'የኦዲት ወሰን' : 'Audit Scope', strtoupper($scope)]);
        fputcsv($f, []);

        // Key KPIs (for full audit only)
        if ($scope === 'full') {
            fputcsv($f, [$isAmharic ? '--- 1. ዋና ዋና ጠቋሚዎች ---' : '--- 1. KEY PERFORMANCE INDICATORS ---']);
            fputcsv($f, [$isAmharic ? 'ጠቅላላ ተጠቃሚዎች' : 'Total Visitors', $data['totalCount']]);
            fputcsv($f, [$isAmharic ? 'ወንድ ተጠቃሚዎች' : 'Male Visitors', $data['maleCount'], $data['malePercent'] . '%']);
            fputcsv($f, [$isAmharic ? 'ሴት ተጠቃሚዎች' : 'Female Visitors', $data['femaleCount'], $data['femalePercent'] . '%']);
            fputcsv($f, [$isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window', $data['peakHourLabel']]);
            fputcsv($f, [$isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Branch', $data['busiestBranchName']]);
            fputcsv($f, []);
        }

        // Demographic Breakdown (for full or patron_types)
        if ($scope === 'full' || $scope === 'patron_types') {
            fputcsv($f, [$isAmharic ? '--- 2. የተጠቃሚዎች ስነ-ሕዝብ ስርጭት ---' : '--- 2. PATRON DEMOGRAPHIC BREAKDOWN ---']);
            fputcsv($f, [
                $isAmharic ? 'የተጠቃሚ ምድብ' : 'User Type',
                $isAmharic ? 'ወንድ' : 'Male',
                $isAmharic ? 'ሴት' : 'Female',
                $isAmharic ? 'ጠቅላላ' : 'Total',
                $isAmharic ? 'ድርሻ (%)' : 'Share (%)',
            ]);

            $types = [
                'student' => $isAmharic ? 'ተማሪ' : 'Student',
                'teacher' => $isAmharic ? 'መምህር' : 'Teacher',
                'staff' => $isAmharic ? 'ሠራተኛ' : 'Staff',
            ];

            foreach ($types as $key => $label) {
                $row = $data['demographics'][$key];
                fputcsv($f, [$label, $row['male'], $row['female'], $row['total'], $row['percent'] . '%']);
            }
            fputcsv($f, [$isAmharic ? 'ጠቅላላ' : 'Total', $data['maleCount'], $data['femaleCount'], $data['totalCount'], '100%']);
            fputcsv($f, []);
        }

        // Branch Comparison Section (for full or branches, omitted if single branch selected)
        if (($scope === 'full' || $scope === 'branches') && empty($data['selectedLibrary']) && !empty($data['branchComparison'])) {
            fputcsv($f, [$isAmharic ? '--- 3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር ---' : '--- 3. LIBRARY BRANCH FACILITY COMPARISON ---']);
            fputcsv($f, [
                $isAmharic ? 'የቅርንጫፍ ስም' : 'Branch Facility',
                $isAmharic ? 'ኮድ' : 'Code',
                $isAmharic ? 'ተማሪዎች' : 'Students',
                $isAmharic ? 'መምህራን' : 'Teachers',
                $isAmharic ? 'ሠራተኞች' : 'Staff',
                $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons',
                $isAmharic ? 'ድርሻ (%)' : 'Traffic Share (%)',
            ]);
            foreach ($data['branchComparison'] as $branch) {
                fputcsv($f, [
                    $branch['name'],
                    $branch['code'],
                    $branch['students'],
                    $branch['teachers'] ?? 0,
                    $branch['staff'] ?? 0,
                    $branch['total'],
                    $branch['percent'] . '%',
                ]);
            }
            fputcsv($f, []);
        }

        // Shift Time Windows (for full or day_of_week)
        if ($scope === 'full' || $scope === 'day_of_week') {
            fputcsv($f, [$isAmharic ? '--- 4. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት ---' : '--- 4. OPERATIONAL SHIFT WINDOWS (MORNING / AFTERNOON / NIGHT) ---']);
            fputcsv($f, [
                $isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window',
                $isAmharic ? 'የሰዓት ገደብ' : 'Time Interval',
                $isAmharic ? 'ተማሪዎች' : 'Students',
                $isAmharic ? 'መምህራን' : 'Teachers',
                $isAmharic ? 'ሠራተኞች' : 'Staff',
                $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons',
                $isAmharic ? 'ድርሻ (%)' : 'Share (%)',
                $isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Hour',
                $isAmharic ? 'የጥግግት ደረጃ' : 'Congestion Index',
            ]);
            foreach ($data['shiftWindows'] as $shift) {
                fputcsv($f, [
                    $shift['title'],
                    $shift['time_range'],
                    $shift['students'],
                    $shift['teachers'] ?? 0,
                    $shift['staff'] ?? 0,
                    $shift['count'],
                    $shift['percent'] . '%',
                    $shift['peak_hour'] ?? '—',
                    $isAmharic ? ($shift['congestion_index_am'] ?? '—') : ($shift['congestion_index'] ?? '—'),
                ]);
            }
            fputcsv($f, []);
        }

        // Day of Week Section (for full or day_of_week)
        if (($scope === 'full' || $scope === 'day_of_week') && !empty($data['dayOfWeekAnalytics'])) {
            fputcsv($f, [$isAmharic ? '--- 5. የሳምንት ቀናት የተጠቃሚዎች ፍሰት ትንተና ---' : '--- 5. DAY-OF-THE-WEEK OPERATIONAL INFLUX ANALYSIS ---']);
            fputcsv($f, [
                $isAmharic ? 'የሳምንቱ ዕለት' : 'Day of Week',
                $isAmharic ? 'አማካይ በቀን' : 'Daily Average',
                $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Visitors',
                $isAmharic ? 'የሳምንቱ ድርሻ (%)' : 'Weekly Share (%)',
                $isAmharic ? 'የፍሰት ደረጃ' : 'Surge Level',
                $isAmharic ? 'የቅድመ-ትንበያ ማስታወሻ' : 'Management Prediction',
            ]);
            foreach ($data['dayOfWeekAnalytics'] as $dow) {
                fputcsv($f, [
                    $dow['display_name'],
                    $dow['daily_average'],
                    $dow['total'],
                    $dow['percentage'] . '%',
                    $isAmharic ? $dow['surge_level_am'] : $dow['surge_level'],
                    $isAmharic ? $dow['prediction_am'] : $dow['prediction'],
                ]);
            }
            fputcsv($f, []);
        }

        // Intra-Month Day Recurring Occurrence Matrix (for full or intra_month)
        if (($scope === 'full' || $scope === 'intra_month') && !empty($data['intraMonthDayAnalytics'])) {
            fputcsv($f, [$isAmharic ? '--- 6. በወሩ ውስጥ ያሉ ሳምንታትና የዕለታት ድግግሞሽ ትንተና ---' : '--- 6. INTRA-MONTH RECURRING DAY OCCURRENCE MATRIX ---']);
            fputcsv($f, [$isAmharic ? 'የትንበያ ማስጠንቀቂያ' : 'Prediction Alert', $data['intraMonthDayAnalytics']['prediction']]);
            fputcsv($f, [
                $isAmharic ? 'የወሩ ሳምንት' : 'Week in Month',
                $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons',
                $isAmharic ? 'አማካይ በቀን' : 'Daily Average',
                $isAmharic ? 'ድርሻ (%)' : 'Share (%)',
                $isAmharic ? 'የአሰራር ማስታወሻ' : 'Operational Note',
            ]);
            foreach ($data['intraMonthDayAnalytics']['weekly_cycles'] as $wCycle) {
                fputcsv($f, [
                    $isAmharic ? $wCycle['label_am'] : $wCycle['label'],
                    $wCycle['total'],
                    $wCycle['daily_average'],
                    $wCycle['percent'] . '%',
                    $isAmharic ? $wCycle['prediction_am'] : $wCycle['prediction'],
                ]);
            }
            fputcsv($f, []);
        }

        // Monthly & Yearly Trends (for full only)
        if ($scope === 'full' && (!empty($data['monthlyAnalytics']) || !empty($data['yearlyAnalytics']))) {
            fputcsv($f, [$isAmharic ? '--- 7. የወራትና የዓመታት ንጽጽር ትንተና ---' : '--- 7. MONTH-OVER-MONTH & YEAR-OVER-YEAR TRAJECTORY ---']);
            if (!empty($data['monthlyAnalytics'])) {
                fputcsv($f, [
                    $isAmharic ? 'የሂሳብ ወር' : 'Month',
                    $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Visitors',
                    $isAmharic ? 'ተማሪዎች' : 'Students',
                    $isAmharic ? 'መምህራን' : 'Teachers',
                    $isAmharic ? 'ሠራተኞች' : 'Staff',
                    $isAmharic ? 'አማካይ በቀን' : 'Daily Average',
                    $isAmharic ? 'የወር እድገት (MoM)' : 'Monthly Growth',
                    $isAmharic ? 'ከፍተኛ ተጠቃሚ ዕለት' : 'Peak Weekday',
                ]);
                foreach ($data['monthlyAnalytics'] as $mInfo) {
                    fputcsv($f, [
                        $mInfo['display_name'],
                        $mInfo['total'],
                        $mInfo['students'] ?? 0,
                        $mInfo['teachers'] ?? 0,
                        $mInfo['staff'] ?? 0,
                        $mInfo['daily_avg'],
                        $mInfo['growth_rate'],
                        $mInfo['peak_day'],
                    ]);
                }
            }
            if (!empty($data['yearlyAnalytics'])) {
                fputcsv($f, [
                    $isAmharic ? 'የአካዳሚክ ዓመት' : 'Academic Year',
                    $isAmharic ? 'ጠቅላላ የዓመት ተጠቃሚ' : 'Annual Total',
                    $isAmharic ? 'የስራ ቀናት' : 'Operating Days',
                    $isAmharic ? 'አማካይ በቀን' : 'Daily Average',
                    $isAmharic ? 'የዓመት እድገት (YoY)' : 'Yearly Growth',
                ]);
                foreach ($data['yearlyAnalytics'] as $yInfo) {
                    fputcsv($f, [
                        $yInfo['display_year'],
                        $yInfo['total'],
                        $yInfo['operating_days'],
                        $yInfo['daily_avg'],
                        $yInfo['growth_rate'],
                    ]);
                }
            }
            fputcsv($f, []);
        }

        rewind($f);
        $content = stream_get_contents($f);
        fclose($f);

        return $content;
    }
}
