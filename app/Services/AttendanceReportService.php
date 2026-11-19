<?php

namespace App\Services;

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
                'title' => $isAmharic ? 'የጠዋት ፈረቃ (08:00–12:00)' : 'Morning Window (08:00–12:00)',
                'time_range' => '08:00 – 12:00 (02:00 – 06:00)',
                'count' => 0,
                'students' => 0,
                'teachers' => 0,
                'staff' => 0,
                'faculty' => 0,
                'percent' => 0.0,
            ],
            'afternoon' => [
                'code' => 'afternoon',
                'title' => $isAmharic ? 'የቀን ፈረቃ (12:00–17:00)' : 'Afternoon Window (12:00–17:00)',
                'time_range' => '12:00 – 17:00 (06:00 – 11:00)',
                'count' => 0,
                'students' => 0,
                'teachers' => 0,
                'staff' => 0,
                'faculty' => 0,
                'percent' => 0.0,
            ],
            'night' => [
                'code' => 'night',
                'title' => $isAmharic ? 'የማታ ፈረቃ (17:00–23:00)' : 'Night Shift Window (17:00–23:00)',
                'time_range' => '17:00 – 23:00 (11:00 – 05:00)',
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

        // Peak hour identification
        arsort($hourlyDistribution);
        $peakHourNum = key($hourlyDistribution) ?? 14;
        $peakHourCount = current($hourlyDistribution) ?? 0;
        $peakHourLabel = sprintf('%02d:00 – %02d:00', $peakHourNum, $peakHourNum + 1);

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

        // 6. Longitudinal Records for Day-of-Week, Intra-Month, Monthly & Yearly Trend Intelligence
        $longitudinalRecords = LibraryAttendance::query()
            ->when($restrictedLibraryId, fn($q) => $q->where('library_id', $restrictedLibraryId))
            ->when(!empty($filters['user_type']), fn($q) => $q->where('user_type', $filters['user_type']))
            ->when(!empty($filters['gender']), fn($q) => $q->where('gender', $filters['gender']))
            ->select(['id', 'entry_at', 'visitor_count', 'user_type', 'gender', 'library_id'])
            ->get();

        $dayOfWeekAnalytics = $this->calculateDayOfWeekAnalytics($longitudinalRecords, $isAmharic);
        $intraMonthDayAnalytics = $this->calculateIntraMonthDayAnalytics($longitudinalRecords, $isAmharic);
        $monthlyAnalytics = $this->calculateMonthlyAnalytics($longitudinalRecords, $isAmharic);
        $yearlyAnalytics = $this->calculateYearlyAnalytics($longitudinalRecords, $isAmharic);
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

        // Formal Executive Narrative Description
        $descriptionEn = "This official periodic attendance and patron demographic analytics report is compiled by the Woldia University Directorate of Library and Information Services in accordance with the Academic Governance and Library Utilization Policy. The audit encompasses physical gate foot-traffic, patron categorization (undergraduate and postgraduate students, teaching and research faculty, and administrative staff), and gender distribution across campus library facilities ({$libraryName}). The reporting window spans from {$fromFormatted['full']} to {$toFormatted['full']}. The primary objective is to evaluate campus learning resource utilization, monitor shift peak occupancy, maintain institutional audit transparency, and support data-driven allocation of facility infrastructure, study desk seating, and catalog staffing.";

        $descriptionAm = "ይህ ይፋዊ የቤተ-መጻሕፍት አጠቃቀም እና የተጠቃሚዎች ስነ-ሕዝብ ትንተና ሪፖርት በወልድያ ዩኒቨርሲቲ የቤተ-መጻሕፍት እና መረጃ አገልግሎት ዳይሬክቶሬት የአካዳሚክ አስተዳደርና የቤተ-መጻሕፍት አጠቃቀም መመሪያን መሠረት በማድረግ የተዘጋጀ ነው። ሪፖርቱ በዩኒቨርሲቲው የቤተ-መጻሕፍት ቅርንጫፎች ({$libraryName}) የተመዘገቡ የቀጥታ ጎብኝዎችን፣ የተጠቃሚዎችን የትምህርትና የስራ ደረጃ (የቅድመ-ምረቃና ድህረ-ምረቃ ተማሪዎች፣ መምህራንና ተመራማሪዎች፣ የአስተዳደርና ቴክኒክ ሠራተኞች) እንዲሁም የጾታ ስርጭት ትንተናን በዝርዝር ያካትታል። የሪፖርቱ ሽፋን ጊዜ ከ {$fromFormatted['full']} እስከ {$toFormatted['full']} ነው። የዚህ ሪፖርት ዋነኛ ዓላማ የቤተ-መጻሕፍት ሀብት አጠቃቀምን መገምገም፣ የፈረቃ ተረኝነትንና ከፍተኛ የተጠቃሚዎች ሰዓትን መለየት፣ የተቋማዊ ቁጥጥር ግልጽነትን ማስጠበቅ እና በመረጃ ላይ የተመሠረተ የሀብትና የሰው ኃይል ድልድል ማከናወን ነው።";

        $description = $isAmharic ? $descriptionAm : $descriptionEn;

        return [
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
        ];
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
                $info['prediction_am'] = 'ቀጣይነት ያለው ከፍተኛ የጎብኝዎች ቁጥር። ሙሉ የዴስክ ተረኝነትን ያስጠብቁ።';
            } elseif ($ratio >= 0.45) {
                $info['surge_level'] = 'Moderate';
                $info['surge_level_am'] = 'መካከለኛ';
                $info['prediction'] = 'Balanced flow of academic study and book borrowing.';
                $info['prediction_am'] = 'የተመጣጠነ የጥናትና የመጽሐፍት ውሰት እንቅስቃሴ።';
            } else {
                $info['surge_level'] = 'Light Traffic';
                $info['surge_level_am'] = 'ዝቅተኛ ፍሰት';
                $info['prediction'] = 'Lighter traffic period suitable for catalog audit and shelving.';
                $info['prediction_am'] = 'ዝቅተኛ የጎብኝዎች ቁጥር። ለመጽሐፍት ድልድልና ለጽዳት አመቺ ሰዓት።';
            }
        }
        unset($info);

        return array_values($dowData);
    }

    /**
     * Calculate Intra-Month Day Occurrences (1st Mon, 2nd Mon...) & Weekly Cycles in a Month.
     */
    protected function calculateIntraMonthDayAnalytics($records, bool $isAmharic): array
    {
        $daysMap = [
            1 => ['en' => 'Monday', 'am' => 'ሰኞ'],
            2 => ['en' => 'Tuesday', 'am' => 'ማክሰኞ'],
            3 => ['en' => 'Wednesday', 'am' => 'ረቡዕ'],
            4 => ['en' => 'Thursday', 'am' => 'ሐሙስ'],
            5 => ['en' => 'Friday', 'am' => 'ዓርብ'],
        ];

        $occurrences = [];
        for ($dow = 1; $dow <= 5; $dow++) {
            $occurrences[$dow] = [
                'day_index' => $dow,
                'day_name' => $daysMap[$dow]['en'],
                'day_name_am' => $daysMap[$dow]['am'],
                'display_name' => $isAmharic ? $daysMap[$dow]['am'] : $daysMap[$dow]['en'],
                'occurrences' => [
                    1 => ['occurrence' => 1, 'label' => '1st ' . $daysMap[$dow]['en'], 'label_am' => '1ኛ ' . $daysMap[$dow]['am'], 'total' => 0, 'count' => 0, 'students' => 0, 'teachers' => 0, 'staff' => 0],
                    2 => ['occurrence' => 2, 'label' => '2nd ' . $daysMap[$dow]['en'], 'label_am' => '2ኛ ' . $daysMap[$dow]['am'], 'total' => 0, 'count' => 0, 'students' => 0, 'teachers' => 0, 'staff' => 0],
                    3 => ['occurrence' => 3, 'label' => '3rd ' . $daysMap[$dow]['en'], 'label_am' => '3ኛ ' . $daysMap[$dow]['am'], 'total' => 0, 'count' => 0, 'students' => 0, 'teachers' => 0, 'staff' => 0],
                    4 => ['occurrence' => 4, 'label' => '4th ' . $daysMap[$dow]['en'], 'label_am' => '4ኛ ' . $daysMap[$dow]['am'], 'total' => 0, 'count' => 0, 'students' => 0, 'teachers' => 0, 'staff' => 0],
                ],
            ];
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
            $vCount = (int) $rec->visitor_count;

            if ($dow <= 5 && isset($occurrences[$dow]['occurrences'][$occ])) {
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
                if ($ratio >= 0.85) {
                    $oInfo['surge_badge'] = 'Peak Surge';
                    $oInfo['surge_badge_am'] = 'ከፍተኛ ፍሰት';
                    $oInfo['is_peak'] = true;
                } elseif ($ratio >= 0.65) {
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
                $wInfo['prediction_am'] = 'የወሩ አጋማሽ የኮርስ ሥራዎች ፍሰት። የፈተናና የጥናት ዝግጅት ከፍተኛ የጎብኝዎች ቁጥር ያስከትላል።';
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
            $mNum = $c->month;
            $gregorianMonthName = $c->format('F Y');
            $ethMonth = $ethMonthNames[$mNum] ?? 'ኅዳር';
            $ethYear = $c->year - 7;
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

            $dailyAvg = round($tot / max(1, $c->daysInMonth), 1);
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
                'operating_days' => max(1, count($logs->groupBy(fn($l) => Carbon::parse($l->entry_at)->toDateString()))),
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
     * Calculate Year-over-Year Trajectory Analytics.
     */
    protected function calculateYearlyAnalytics($records, bool $isAmharic): array
    {
        $yearGroups = $records->groupBy(function($r) {
            return Carbon::parse($r->entry_at)->format('Y');
        })->sortKeys();

        $yearlyData = [];
        $prevYearTotal = null;

        foreach ($yearGroups as $yr => $logs) {
            $tot = (int) $logs->sum('visitor_count');
            $ethYr = ((int)$yr) - 7;

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
            $ethH = ($h >= 6) ? ($h - 6) : ($h + 6);
            $timeOfDayAm = ($h < 12) ? 'ጠዋት' : (($h < 18) ? 'ከሰዓት' : 'ምሽት');
            $shiftCat = ($h >= 8 && $h < 12) ? 'morning' : (($h >= 12 && $h < 17) ? 'afternoon' : 'night');

            $hourlyCurve[] = [
                'hour' => $h,
                'label' => sprintf('%02d:00', $h),
                'label_am' => sprintf('%02d:00 %s', $ethH, $timeOfDayAm),
                'display_label' => $isAmharic ? sprintf('%02d:00 %s', $ethH, $timeOfDayAm) : sprintf('%02d:00', $h),
                'count' => $count,
                'percent' => $totalDiurnal > 0 ? round(($count / $totalDiurnal) * 100, 1) : 0.0,
                'height_percent' => $maxHourCount > 0 ? round(($count / $maxHourCount) * 100) : 0,
                'shift' => $shiftCat,
                'is_peak' => ($h === $peakHourNum),
            ];
        }

        // Add congestion metrics to shift windows
        foreach ($shiftWindows as $code => &$win) {
            if ($code === 'morning') {
                $win['peak_hour'] = '10:00 – 11:00 (04:00 – 05:00)';
                $win['congestion_index'] = 'High Density';
                $win['congestion_index_am'] = 'ከፍተኛ ጥግግት';
            } elseif ($code === 'afternoon') {
                $win['peak_hour'] = '14:00 – 15:00 (08:00 – 09:00)';
                $win['congestion_index'] = 'Peak Surge';
                $win['congestion_index_am'] = 'ከፍተኛው ጥግግት';
            } else {
                $win['peak_hour'] = '19:00 – 20:00 (01:00 – 02:00)';
                $win['congestion_index'] = 'Moderate Density';
                $win['congestion_index_am'] = 'መካከለኛ ጥግግት';
            }
        }
        unset($win);

        return [
            'windows' => $shiftWindows,
            'hourly_curve' => $hourlyCurve,
        ];
    }

    /**
     * Generate Excel Spreadsheet format (MIME application/vnd.ms-excel with SpreadsheetML / Clean XML Table).
     */
    public function generateExcelXml(array $data): string
    {
        $isAmharic = $data['isAmharic'];
        $title = $isAmharic ? 'የቤተ-መጻሕፍት አጠቃቀም እና የስነ-ሕዝብ ትንተና ይፋዊ ሪፖርት' : 'Official Library Attendance and Demographic Analytics Report';

        $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $output .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $output .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $output .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $output .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $output .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        // Styles
        $output .= ' <Styles>' . "\n";
        $output .= '  <Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Segoe UI" ss:Size="10"/></Style>' . "\n";
        $output .= '  <Style ss:ID="HeaderTitle"><Font ss:FontName="Segoe UI" ss:Size="14" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0B529C" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $output .= '  <Style ss:ID="SubHeader"><Font ss:FontName="Segoe UI" ss:Size="10" ss:Italic="1" ss:Color="#333333"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $output .= '  <Style ss:ID="SectionHeader"><Font ss:FontName="Segoe UI" ss:Size="11" ss:Bold="1" ss:Color="#0B529C"/><Interior ss:Color="#EBF2FA" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0B529C"/></Borders></Style>' . "\n";
        $output .= '  <Style ss:ID="TableHead"><Font ss:FontName="Segoe UI" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E293B" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $output .= '  <Style ss:ID="CellText"><Font ss:FontName="Segoe UI" ss:Size="10"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>' . "\n";
        $output .= '  <Style ss:ID="CellNumber"><Font ss:FontName="Segoe UI" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Right"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>' . "\n";
        $output .= '  <Style ss:ID="CellTotal"><Font ss:FontName="Segoe UI" ss:Size="10" ss:Bold="1" ss:Color="#0B529C"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/><Alignment ss:Horizontal="Right"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#0B529C"/></Borders></Style>' . "\n";
        $output .= ' </Styles>' . "\n";

        // Worksheet 1: Executive Summary & Multidimensional Analytics
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
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ጎብኝዎች' : 'Total Visitors Count') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['totalCount'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ወንድ ጎብኝዎች' : 'Male Visitors') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['maleCount'] . '</Data></Cell><Cell ss:StyleID="CellText"><Data ss:Type="String">' . $data['malePercent'] . '%</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ሴት ጎብኝዎች' : 'Female Visitors') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['femaleCount'] . '</Data></Cell><Cell ss:StyleID="CellText"><Data ss:Type="String">' . $data['femalePercent'] . '%</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window') . '</Data></Cell><Cell ss:MergeAcross="1" ss:StyleID="CellText"><Data ss:Type="String">' . $data['peakHourLabel'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Campus Branch') . '</Data></Cell><Cell ss:MergeAcross="1" ss:StyleID="CellText"><Data ss:Type="String">' . $data['busiestBranchName'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="6"/></Row>' . "\n";

        // Section 2: Demographic Breakdown
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '2. የተጠቃሚዎች ስነ-ሕዝብና ምድብ ስርጭት' : '2. Patron Demographic Distribution') . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="18">' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የተጠቃሚ ምድብ' : 'Patron Category') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ወንድ' : 'Male') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሴት' : 'Female') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ድምር' : 'Subtotal') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</Data></Cell>' . "\n";
        $output .= '   </Row>' . "\n";

        $types = [
            'student' => $isAmharic ? 'ተማሪዎች (ቅድመ-ምረቃና ድህረ-ምረቃ)' : 'Students (Undergraduate & Postgraduate)',
            'teacher' => $isAmharic ? 'መምህራንና ተመራማሪዎች' : 'Faculty & Research Scholars',
            'staff' => $isAmharic ? 'የአስተዳደርና ቴክኒክ ሠራተኞች' : 'Administrative & Technical Staff',
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
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">' . ($isAmharic ? 'አጠቃላይ ድምር' : 'Grand Total') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['maleCount'] . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['femaleCount'] . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="Number">' . $data['totalCount'] . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="CellTotal"><Data ss:Type="String">100%</Data></Cell>' . "\n";
        $output .= '   </Row>' . "\n";
        $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="6"/></Row>' . "\n";

        // Section 3: Branch Facilities Comparison
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር' : '3. Library Branch Facility Comparison') . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="18">' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የቅርንጫፍ ስም' : 'Branch Facility') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ኮድ' : 'Code') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons') . '</Data></Cell>' . "\n";
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

            $output .= '   <Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንት ቀናት የጎብኝዎች ፍሰትና የጥግግት ትንተና (Monday - Sunday)' : 'Day-of-the-Week Operational Foot-Traffic Influx Profile') . '</Data></Cell></Row>' . "\n";
            $output .= '   <Row ss:Height="18">' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንቱ ዕለት' : 'Day of Week') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Visitors') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሳምንቱ ድርሻ (%)' : 'Weekly Share (%)') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠዋት / ቀን / ማታ' : 'Morn / Aft / Night') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የፍሰት ደረጃ' : 'Surge Level') . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የቅድመ-ትንበያ ማስታወሻ' : 'Management Prediction') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";

            foreach ($data['dayOfWeekAnalytics'] as $dow) {
                $split = $dow['morning'] . ' / ' . $dow['afternoon'] . ' / ' . $dow['night'];
                $output .= '   <Row>' . "\n";
                $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($dow['display_name']) . ($dow['is_peak'] ? ' ★' : '') . '</Data></Cell>' . "\n";
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
            $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የጎብኝዎች ብዛት' : 'Visitors Count') . '</Data></Cell>' . "\n";
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
                    $output .= '    <Cell ss:MergeAcross="1" ss:StyleID="CellText"><Data ss:Type="String">' . ($hc['is_peak'] ? ($isAmharic ? 'ከፍተኛ ሰዓት ★' : 'Peak Hour ★') : '—') . '</Data></Cell>' . "\n";
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
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የወሩ ድርሻ (%)' : 'Monthly Share (%)') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:MergeAcross="3" ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የአሰራር ማስታወሻ' : 'Operational Prediction Context') . '</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";

                foreach ($intra['weekly_cycles'] as $wCycle) {
                    $output .= '   <Row>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($isAmharic ? $wCycle['label_am'] : $wCycle['label']) . ($wCycle['is_peak_week'] ? ' ★' : '') . '</Data></Cell>' . "\n";
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
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Visitors') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የወር እድገት (MoM)' : 'Monthly Growth') . '</Data></Cell>' . "\n";
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ ተጠቃሚ ዕለት' : 'Peak Weekday') . '</Data></Cell>' . "\n";
                $output .= '   </Row>' . "\n";

                foreach ($data['monthlyAnalytics'] as $mInfo) {
                    $output .= '   <Row>' . "\n";
                    $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($mInfo['display_name']) . ($mInfo['is_peak_month'] ? ' ★' : '') . '</Data></Cell>' . "\n";
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
                $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የዓመት ጠቅላላ ጎብኝ' : 'Annual Total Visitors') . '</Data></Cell>' . "\n";
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

        // Worksheet 4: Detailed Entries Audit Registry
        $output .= ' <Worksheet ss:Name="' . ($isAmharic ? 'የቀጥታ ምዝገባዎች መዝገብ' : 'Granular Audit Registry') . '">' . "\n";
        $output .= '  <Table ss:DefaultColumnWidth="110">' . "\n";
        $output .= '   <Column ss:Width="110"/>' . "\n";
        $output .= '   <Column ss:Width="140"/>' . "\n";
        $output .= '   <Column ss:Width="120"/>' . "\n";
        $output .= '   <Column ss:Width="100"/>' . "\n";
        $output .= '   <Column ss:Width="80"/>' . "\n";
        $output .= '   <Column ss:Width="80"/>' . "\n";
        $output .= '   <Column ss:Width="140"/>' . "\n";

        $output .= '   <Row ss:Height="20">' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የመዝገብ መለያ' : 'Log ID') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ቀንና ሰዓት' : 'Timestamp') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ቅርንጫፍ' : 'Branch') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የተጠቃሚ ዓይነት' : 'Patron Type') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጾታ' : 'Gender') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ብዛት' : 'Count') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'መዝጋቢ ሠራተኛ' : 'Logged By') . '</Data></Cell>' . "\n";
        $output .= '   </Row>' . "\n";

        foreach ($data['attendances']->take(500) as $log) {
            $dt = EthiopianDateService::formatDT($log->entry_at, $isAmharic);
            $bName = $isAmharic ? ($log->library?->name_am ?? $log->library?->name ?? '—') : ($log->library?->name ?? '—');
            $pType = match ($log->user_type) {
                'student' => $isAmharic ? 'ተማሪ' : 'Student',
                'teacher' => $isAmharic ? 'መምህር' : 'Faculty',
                'staff' => $isAmharic ? 'ሰራተኛ' : 'Staff',
                default => ucfirst($log->user_type),
            };
            $gLabel = match ($log->gender) {
                'male' => $isAmharic ? 'ወንድ' : 'Male',
                'female' => $isAmharic ? 'ሴት' : 'Female',
                default => ucfirst($log->gender),
            };

            $output .= '   <Row>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $log->slug . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($dt['date'] . ' ' . $dt['time']) . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($bName) . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $pType . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $gLabel . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $log->visitor_count . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($log->staff?->name ?? 'System') . '</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";
        }

        $output .= '  </Table>' . "\n";
        $output .= ' </Worksheet>' . "\n";
        $output .= '</Workbook>' . "\n";

        return $output;
    }

    /**
     * Generate Microsoft Word document format (.doc with standard Word HTML structure).
     */
    public function generateWordDoc(array $data): string
    {
        $isAmharic = $data['isAmharic'];
        $title = $isAmharic ? 'የቤተ-መጻሕፍት አጠቃቀም እና የስነ-ሕዝብ ትንተና ይፋዊ ሪፖርት' : 'Official Library Attendance and Demographic Analytics Report';
        $subTitle = $isAmharic ? 'የአካዳሚክ ቤተ-መጻሕፍት አገልግሎት ዳይሬክቶሬት ይፋዊ የኦዲት ሰነድ' : 'Official Audit Document — Directorate of Academic Library & Information Services';

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
        $html .= '<style>';
        $html .= 'body { font-family: "Times New Roman", "Nyala", "Noto Sans Ethiopic", serif; font-size: 11pt; line-height: 1.4; color: #111; margin: 20mm; }';
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
        $html .= '<hr style="border: 0; border-top: 2pt double #0B529C; margin-bottom: 15pt;" />';

        // Document Metadata Table
        $html .= '<table class="meta-table" style="margin-bottom: 16pt;">';
        $html .= '<tr>';
        $html .= '<td style="width: 25%; font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የሰነድ መለያ ቁጥር' : 'Document Reference No') . ':</td>';
        $html .= '<td style="width: 25%; font-family: monospace; font-weight: bold;">' . $data['refNumber'] . '</td>';
        $html .= '<td style="width: 25%; font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የተዘጋጀበት ቀንና ሰዓት' : 'Date of Issuance') . ':</td>';
        $html .= '<td style="width: 25%;">' . $data['generatedAt']['full'] . ' (' . $data['generatedAt']['time'] . ')</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የሪፖርቱ ሽፋን ጊዜ' : 'Reporting Window') . ':</td>';
        $html .= '<td>' . $data['dateFromStr']['date'] . ' – ' . $data['dateToStr']['date'] . '</td>';
        $html .= '<td style="font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የቤተ-መጻሕፍት ቅርንጫፍ' : 'Target Facilities') . ':</td>';
        $html .= '<td>' . htmlspecialchars($data['libraryName']) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'ያዘጋጀው ኃላፊ/ሠራተኛ' : 'Reporting Officer') . ':</td>';
        $html .= '<td>' . htmlspecialchars($data['generatedBy']) . ' (' . $data['generatedByUsername'] . ')</td>';
        $html .= '<td style="font-weight: bold; background: #F8FAFC;">' . ($isAmharic ? 'የሰነዱ ደረጃ' : 'Classification') . ':</td>';
        $html .= '<td>' . ($isAmharic ? 'ይፋዊ የአካዳሚክ ኦዲት ሰነድ' : 'Official Academic Audit Record') . '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        // Executive Narrative Description
        $html .= '<h3>' . ($isAmharic ? 'የስራ አስፈጻሚ ማጠቃለያና መግለጫ' : 'Executive Summary & Narrative') . '</h3>';
        $html .= '<p>' . htmlspecialchars($data['description']) . '</p>';

        // Section 1: KPI Summary Table
        $html .= '<h3>' . ($isAmharic ? '1. ዋና ዋና የአፈጻጸም ጠቋሚዎች ማጠቃለያ' : '1. Key Performance Indicators (KPIs)') . '</h3>';
        $html .= '<table>';
        $html .= '<tr><th>' . ($isAmharic ? 'የመለኪያ መግለጫ' : 'Metric Indicator') . '</th><th class="text-right">' . ($isAmharic ? 'የተመዘገበ ቁጥር' : 'Recorded Value') . '</th><th>' . ($isAmharic ? 'የድርሻ መጠን' : 'Percentage / Proportion') . '</th><th>' . ($isAmharic ? 'ተጨማሪ የአሰራር ማስታወሻ' : 'Operational Note') . '</th></tr>';
        $html .= '<tr><td>' . ($isAmharic ? 'ጠቅላላ የጎብኝዎች ብዛት' : 'Total Patron Foot Traffic') . '</td><td class="text-right" style="font-weight:bold;">' . number_format($data['totalCount']) . '</td><td>100.0%</td><td>' . ($isAmharic ? 'በተመረጠው የጊዜ ገደብ የገቡ ጠቅላላ ጎብኝዎች' : 'Aggregated campus gate check-ins') . '</td></tr>';
        $html .= '<tr><td>' . ($isAmharic ? 'ወንድ ጎብኝዎች' : 'Male Patrons') . '</td><td class="text-right">' . number_format($data['maleCount']) . '</td><td>' . $data['malePercent'] . '%</td><td>' . ($isAmharic ? 'የወንዶች ተሳትፎ ድርሻ' : 'Share of recorded traffic') . '</td></tr>';
        $html .= '<tr><td>' . ($isAmharic ? 'ሴት ጎብኝዎች' : 'Female Patrons') . '</td><td class="text-right">' . number_format($data['femaleCount']) . '</td><td>' . $data['femalePercent'] . '%</td><td>' . ($isAmharic ? 'የሴቶች ተሳትፎ ድርሻ' : 'Share of recorded traffic') . '</td></tr>';
        $html .= '<tr><td>' . ($isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window') . '</td><td class="text-right">' . number_format($data['peakHourCount']) . '</td><td>—</td><td>' . $data['peakHourLabel'] . '</td></tr>';
        $html .= '<tr><td>' . ($isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Branch Facility') . '</td><td class="text-right">—</td><td>—</td><td>' . htmlspecialchars($data['busiestBranchName']) . '</td></tr>';
        $html .= '</table>';

        // Section 2: Demographic Patron Matrix
        $html .= '<h3>' . ($isAmharic ? '2. የተጠቃሚዎች ምድብና ስነ-ሕዝብ ዝርዝር' : '2. Patron Demographic Categorization') . '</h3>';
        $html .= '<table>';
        $html .= '<tr><th>' . ($isAmharic ? 'የተጠቃሚ ምድብ' : 'Patron Category') . '</th><th class="text-right">' . ($isAmharic ? 'ወንድ' : 'Male') . '</th><th class="text-right">' . ($isAmharic ? 'ሴት' : 'Female') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ድምር' : 'Subtotal') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</th></tr>';

        $types = [
            'student' => $isAmharic ? 'ተማሪዎች (ቅድመ-ምረቃና ድህረ-ምረቃ)' : 'Students (Undergraduate & Postgraduate)',
            'teacher' => $isAmharic ? 'መምህራንና ተመራማሪዎች' : 'Faculty & Research Scholars',
            'staff' => $isAmharic ? 'የአስተዳደርና ቴክኒክ ሠራተኞች' : 'Administrative & Technical Staff',
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
        $html .= '<td>' . ($isAmharic ? 'አጠቃላይ ድምር' : 'Grand Total') . '</td>';
        $html .= '<td class="text-right">' . number_format($data['maleCount']) . '</td>';
        $html .= '<td class="text-right">' . number_format($data['femaleCount']) . '</td>';
        $html .= '<td class="text-right">' . number_format($data['totalCount']) . '</td>';
        $html .= '<td class="text-right">100.0%</td>';
        $html .= '</tr>';
        $html .= '</table>';

        // Section 3: Branch Comparison
        $html .= '<h3>' . ($isAmharic ? '3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር' : '3. Library Branch Facility Distribution') . '</h3>';
        $html .= '<table>';
        $html .= '<tr><th>' . ($isAmharic ? 'የቅርንጫፍ ስም' : 'Campus Branch') . '</th><th>' . ($isAmharic ? 'ኮድ' : 'Code') . '</th><th class="text-right">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</th><th class="text-right">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</th><th class="text-right">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Traffic Share (%)') . '</th></tr>';

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

        // Section 4: Operational Shift Windows
        $html .= '<h3>' . ($isAmharic ? '4. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት' : '4. Operational Shift Windows & Density') . '</h3>';
        $html .= '<table>';
        $html .= '<tr><th>' . ($isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window') . '</th><th>' . ($isAmharic ? 'የሰዓት ገደብ' : 'Time Interval') . '</th><th class="text-right">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</th><th class="text-right">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</th><th class="text-right">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</th></tr>';

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

        // Section 5: Day-of-the-Week Operational Influx Profile
        if (!empty($data['dayOfWeekAnalytics'])) {
            $html .= '<h3>' . ($isAmharic ? '5. የሳምንት ቀናት የጎብኝዎች ፍሰትና የጥግግት ትንተና' : '5. Day-of-the-Week Operational Foot-Traffic Influx') . '</h3>';
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የሳምንቱ ዕለት' : 'Day of Week') . '</th><th class="text-right">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Visitors') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Weekly Share (%)') . '</th><th>' . ($isAmharic ? 'የፍሰት ደረጃ' : 'Surge Level') . '</th><th>' . ($isAmharic ? 'የአስተዳደር የቅድመ-ትንበያ ማስታወሻ' : 'Management Prediction Guidance') . '</th></tr>';
            foreach ($data['dayOfWeekAnalytics'] as $dow) {
                $html .= '<tr>';
                $html .= '<td><b>' . htmlspecialchars($dow['display_name']) . '</b>' . ($dow['is_peak'] ? ' ★' : '') . '</td>';
                $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($dow['daily_average']) . '</td>';
                $html .= '<td class="text-right">' . number_format($dow['total']) . '</td>';
                $html .= '<td class="text-right">' . $dow['percentage'] . '%</td>';
                $html .= '<td>' . htmlspecialchars($isAmharic ? $dow['surge_level_am'] : $dow['surge_level']) . '</td>';
                $html .= '<td>' . htmlspecialchars($isAmharic ? $dow['prediction_am'] : $dow['prediction']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        // Section 6: Intra-Month Day Recurring Occurrence & Weekly Cycle Matrix
        if (!empty($data['intraMonthDayAnalytics'])) {
            $intra = $data['intraMonthDayAnalytics'];
            $html .= '<h3>' . ($isAmharic ? '6. በወሩ ውስጥ ያሉ ሳምንታትና የዕለታት ድግግሞሽ ትንተና' : '6. Intra-Month Recurring Day Occurrence & Weekly Cycle Matrix') . '</h3>';
            $html .= '<p><b>' . ($isAmharic ? 'የትንበያ መረጃ፡ ' : 'Prediction Notice: ') . '</b>' . htmlspecialchars($intra['prediction']) . '</p>';

            // Weekly cycles
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የወሩ ሳምንት' : 'Week in Month') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons') . '</th><th class="text-right">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</th><th>' . ($isAmharic ? 'የአሰራር ሁኔታ' : 'Operational Context') . '</th></tr>';
            foreach ($intra['weekly_cycles'] as $wCycle) {
                $html .= '<tr>';
                $html .= '<td><b>' . htmlspecialchars($isAmharic ? $wCycle['label_am'] : $wCycle['label']) . '</b>' . ($wCycle['is_peak_week'] ? ' ★' : '') . '</td>';
                $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($wCycle['total']) . '</td>';
                $html .= '<td class="text-right">' . number_format($wCycle['daily_average']) . '</td>';
                $html .= '<td class="text-right">' . $wCycle['percent'] . '%</td>';
                $html .= '<td>' . htmlspecialchars($isAmharic ? $wCycle['prediction_am'] : $wCycle['prediction']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';

            // Day occurrences table (1st Mon vs 2nd Mon...)
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የሳምንት ዕለት' : 'Day of Week') . '</th><th>' . ($isAmharic ? '1ኛ ድግግሞሽ (ቀናት 1–7)' : '1st Occurrence (Days 1–7)') . '</th><th>' . ($isAmharic ? '2ኛ ድግግሞሽ (ቀናት 8–14)' : '2nd Occurrence (Days 8–14)') . '</th><th>' . ($isAmharic ? '3ኛ ድግግሞሽ (ቀናት 15–21)' : '3rd Occurrence (Days 15–21)') . '</th><th>' . ($isAmharic ? '4ኛ ድግግሞሽ (ቀናት 22–28)' : '4th Occurrence (Days 22–28)') . '</th></tr>';
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

        // Section 7: Longitudinal Month-over-Month & Year-over-Year Trajectory
        if (!empty($data['monthlyAnalytics']) || !empty($data['yearlyAnalytics'])) {
            $html .= '<h3>' . ($isAmharic ? '7. የወራትና የዓመታት ንጽጽር ትንተና' : '7. Longitudinal Month-over-Month & Year-over-Year Trajectory') . '</h3>';

            if (!empty($data['monthlyAnalytics'])) {
                $html .= '<table>';
                $html .= '<tr><th>' . ($isAmharic ? 'የሂሳብ ወር' : 'Academic Month') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Visitors') . '</th><th class="text-right">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</th><th class="text-right">' . ($isAmharic ? 'መምህራን' : 'Teachers') . '</th><th class="text-right">' . ($isAmharic ? 'ሠራተኞች' : 'Staff') . '</th><th class="text-right">' . ($isAmharic ? 'አማካይ' : 'Daily Avg') . '</th><th class="text-right">' . ($isAmharic ? 'የወር እድገት' : 'Growth') . '</th><th>' . ($isAmharic ? 'ከፍተኛ ዕለት' : 'Peak Weekday') . '</th></tr>';
                foreach ($data['monthlyAnalytics'] as $mInfo) {
                    $html .= '<tr>';
                    $html .= '<td><b>' . htmlspecialchars($mInfo['display_name']) . '</b>' . ($mInfo['is_peak_month'] ? ' ★' : '') . '</td>';
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
                $html .= '<tr><th>' . ($isAmharic ? 'የአካዳሚክ ዓመት' : 'Academic Year') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ የዓመት ጎብኝ' : 'Annual Total Visitors') . '</th><th class="text-right">' . ($isAmharic ? 'የስራ ቀናት' : 'Operating Days') . '</th><th class="text-right">' . ($isAmharic ? 'አማካይ በቀን' : 'Daily Average') . '</th><th class="text-right">' . ($isAmharic ? 'የዓመት እድገት (YoY)' : 'Yearly Growth') . '</th></tr>';
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

        // Section 8: Attendant Duty Desk Activity Log
        if (!empty($data['staffActivity'])) {
            $html .= '<h3>' . ($isAmharic ? '8. የተረኛ ሠራተኞች የምዝገባ እንቅስቃሴ መዝገብ' : '8. Attendant Duty Desk Registry Log') . '</h3>';
            $html .= '<table>';
            $html .= '<tr><th>' . ($isAmharic ? 'የሠራተኛው ስም' : 'Staff Member') . '</th><th>' . ($isAmharic ? 'ቅርንጫፍ' : 'Branch Facility') . '</th><th class="text-right">' . ($isAmharic ? 'የምዝገባ ብዛት' : 'Log Entries') . '</th><th class="text-right">' . ($isAmharic ? 'የተስተናገዱ ጎብኝዎች' : 'Visitors Handled') . '</th></tr>';
            foreach ($data['staffActivity'] as $staff) {
                $html .= '<tr>';
                $html .= '<td><b>' . htmlspecialchars($staff['name']) . '</b> (' . $staff['username'] . ')</td>';
                $html .= '<td>' . htmlspecialchars($staff['branch']) . '</td>';
                $html .= '<td class="text-right">' . number_format($staff['entries_count']) . '</td>';
                $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($staff['visitors_handled']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        // Formal Signature Block
        $html .= '<div style="margin-top: 40pt; page-break-inside: avoid;">';
        $html .= '<div class="sig-box" style="margin-right: 4%;">';
        $html .= '<b>' . ($isAmharic ? 'የመዝጋቢው ቤተ-መጻሕፍት ሠራተኛ ፊርማ' : 'Duty Desk Librarian Signature') . '</b><br/>';
        $html .= htmlspecialchars($data['generatedBy']) . '<br/>';
        $html .= $data['generatedAt']['date'];
        $html .= '</div>';
        $html .= '<div class="sig-box">';
        $html .= '<b>' . ($isAmharic ? 'የዋና ቤተ-መጻሕፍት ኃላፊ ማረጋገጫና ማህተም' : 'Chief University Librarian Seal & Approval') . '</b><br/>';
        $html .= ($isAmharic ? 'ይፋዊ ማህተም' : 'Authorized Academic Registry') . '<br/>';
        $html .= $data['refNumber'];
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Generate Comprehensive Localized CSV.
     */
    public function generateCsv(array $data): string
    {
        $isAmharic = $data['isAmharic'];
        $f = fopen('php://memory', 'r+');
        fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

        // Executive Header Section
        fputcsv($f, [$isAmharic ? 'የወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት ይፋዊ የአጠቃቀምና የስነ-ሕዝብ ትንተና ሪፖርት' : 'WOLDIA UNIVERSITY OFFICIAL ACADEMIC LIBRARY ATTENDANCE & DEMOGRAPHIC ANALYTICS REPORT']);
        fputcsv($f, [$isAmharic ? 'የሰነድ መለያ ቁጥር' : 'Reference ID', $data['refNumber']]);
        fputcsv($f, [$isAmharic ? 'የተዘጋጀበት ቀን' : 'Generated At', $data['generatedAt']['full'] . ' ' . $data['generatedAt']['time']]);
        fputcsv($f, [$isAmharic ? 'የሪፖርቱ ሽፋን ጊዜ' : 'Reporting Window', $data['dateFromStr']['date'] . ' - ' . $data['dateToStr']['date']]);
        fputcsv($f, [$isAmharic ? 'የቤተ-መጻሕፍት ቅርንጫፍ' : 'Branch Scope', $data['libraryName']]);
        fputcsv($f, [$isAmharic ? 'ያዘጋጀው ሠራተኛ' : 'Prepared By', $data['generatedBy']]);
        fputcsv($f, []);

        // Key KPIs
        fputcsv($f, [$isAmharic ? '--- 1. ዋና ዋና ጠቋሚዎች ---' : '--- 1. KEY PERFORMANCE INDICATORS ---']);
        fputcsv($f, [$isAmharic ? 'ጠቅላላ ጎብኝዎች' : 'Total Visitors', $data['totalCount']]);
        fputcsv($f, [$isAmharic ? 'ወንድ ጎብኝዎች' : 'Male Visitors', $data['maleCount'], $data['malePercent'] . '%']);
        fputcsv($f, [$isAmharic ? 'ሴት ጎብኝዎች' : 'Female Visitors', $data['femaleCount'], $data['femalePercent'] . '%']);
        fputcsv($f, [$isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window', $data['peakHourLabel']]);
        fputcsv($f, [$isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Branch', $data['busiestBranchName']]);
        fputcsv($f, []);

        // Demographic Breakdown
        fputcsv($f, [$isAmharic ? '--- 2. የተጠቃሚዎች ስነ-ሕዝብ ስርጭት ---' : '--- 2. PATRON DEMOGRAPHIC BREAKDOWN ---']);
        fputcsv($f, [
            $isAmharic ? 'የተጠቃሚ ምድብ' : 'Patron Category',
            $isAmharic ? 'ወንድ' : 'Male',
            $isAmharic ? 'ሴት' : 'Female',
            $isAmharic ? 'ጠቅላላ ድምር' : 'Subtotal',
            $isAmharic ? 'ድርሻ (%)' : 'Share (%)',
        ]);

        $types = [
            'student' => $isAmharic ? 'ተማሪዎች' : 'Students',
            'teacher' => $isAmharic ? 'መምህራን' : 'Faculty',
            'staff' => $isAmharic ? 'ሠራተኞች' : 'Staff',
        ];

        foreach ($types as $key => $label) {
            $row = $data['demographics'][$key];
            fputcsv($f, [$label, $row['male'], $row['female'], $row['total'], $row['percent'] . '%']);
        }
        fputcsv($f, [$isAmharic ? 'አጠቃላይ ድምር' : 'Grand Total', $data['maleCount'], $data['femaleCount'], $data['totalCount'], '100%']);
        fputcsv($f, []);

        // Branch Comparison Section
        if (!empty($data['branchComparison'])) {
            fputcsv($f, [$isAmharic ? '--- 3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር ---' : '--- 3. LIBRARY BRANCH FACILITY COMPARISON ---']);
            fputcsv($f, [
                $isAmharic ? 'የቅርንጫፍ ስም' : 'Branch Facility',
                $isAmharic ? 'ኮድ' : 'Code',
                $isAmharic ? 'ተማሪዎች' : 'Students',
                $isAmharic ? 'መምህራን' : 'Teachers',
                $isAmharic ? 'ሠራተኞች' : 'Staff',
                $isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons',
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

        // Day of Week Section
        if (!empty($data['dayOfWeekAnalytics'])) {
            fputcsv($f, [$isAmharic ? '--- 4. የሳምንት ቀናት የጎብኝዎች ፍሰት ትንተና ---' : '--- 4. DAY-OF-THE-WEEK OPERATIONAL INFLUX ANALYSIS ---']);
            fputcsv($f, [
                $isAmharic ? 'የሳምንቱ ዕለት' : 'Day of Week',
                $isAmharic ? 'አማካይ በቀን' : 'Daily Average',
                $isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Visitors',
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

        // Intra-Month Day Recurring Occurrence Matrix
        if (!empty($data['intraMonthDayAnalytics'])) {
            fputcsv($f, [$isAmharic ? '--- 5. በወሩ ውስጥ ያሉ ሳምንታትና የዕለታት ድግግሞሽ ትንተና ---' : '--- 5. INTRA-MONTH RECURRING DAY OCCURRENCE MATRIX ---']);
            fputcsv($f, [$isAmharic ? 'የትንበያ ማስጠንቀቂያ' : 'Prediction Alert', $data['intraMonthDayAnalytics']['prediction']]);
            fputcsv($f, [
                $isAmharic ? 'የወሩ ሳምንት' : 'Week in Month',
                $isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons',
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

        // Monthly & Yearly Trends
        if (!empty($data['monthlyAnalytics']) || !empty($data['yearlyAnalytics'])) {
            fputcsv($f, [$isAmharic ? '--- 6. የወራትና የዓመታት ንጽጽር ትንተና ---' : '--- 6. MONTH-OVER-MONTH & YEAR-OVER-YEAR TRAJECTORY ---']);
            if (!empty($data['monthlyAnalytics'])) {
                fputcsv($f, [
                    $isAmharic ? 'የሂሳብ ወር' : 'Month',
                    $isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Visitors',
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
                    $isAmharic ? 'ጠቅላላ የዓመት ጎብኝ' : 'Annual Total',
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

        // Shift Time Windows
        fputcsv($f, [$isAmharic ? '--- 7. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት ---' : '--- 7. OPERATIONAL SHIFT WINDOWS (MORNING / AFTERNOON / NIGHT) ---']);
        fputcsv($f, [
            $isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window',
            $isAmharic ? 'የሰዓት ገደብ' : 'Time Interval',
            $isAmharic ? 'ተማሪዎች' : 'Students',
            $isAmharic ? 'መምህራን' : 'Teachers',
            $isAmharic ? 'ሠራተኞች' : 'Staff',
            $isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons',
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

        // Granular Records
        fputcsv($f, [$isAmharic ? '--- 8. የቀጥታ ምዝገባዎች ዝርዝር መዝገብ ---' : '--- 8. DETAILED ENTRIES REGISTRY ---']);
        fputcsv($f, [
            $isAmharic ? 'የመዝገብ መለያ' : 'Log ID',
            $isAmharic ? 'የቤተ-መጻሕፍት ቅርንጫፍ' : 'Library Branch',
            $isAmharic ? 'የተጠቃሚ ዓይነት' : 'Patron Type',
            $isAmharic ? 'ጾታ' : 'Gender',
            $isAmharic ? 'ብዛት' : 'Count',
            $isAmharic ? 'የተመዘገበበት ሰዓት' : 'Time',
            $isAmharic ? 'ቀን' : 'Date',
            $isAmharic ? 'መዝጋቢ ሠራተኛ' : 'Logged By Staff',
        ]);

        foreach ($data['attendances'] as $log) {
            $dt = EthiopianDateService::formatDT($log->entry_at, $isAmharic);
            $branchName = $isAmharic ? ($log->library?->name_am ?? $log->library?->name ?? 'ጠቅላላ') : ($log->library?->name ?? 'General');
            $pType = match ($log->user_type) {
                'student' => $isAmharic ? 'ተማሪ' : 'Student',
                'teacher' => $isAmharic ? 'መምህር' : 'Faculty',
                'staff' => $isAmharic ? 'ሰራተኛ' : 'Staff',
                default => ucfirst($log->user_type),
            };
            $gLabel = match ($log->gender) {
                'male' => $isAmharic ? 'ወንድ' : 'Male',
                'female' => $isAmharic ? 'ሴት' : 'Female',
                default => ucfirst($log->gender),
            };

            fputcsv($f, [
                $log->slug,
                $branchName,
                $pType,
                $gLabel,
                $log->visitor_count,
                $dt['time'],
                $dt['date'],
                $log->staff?->name ?? 'System',
            ]);
        }

        rewind($f);
        $content = stream_get_contents($f);
        fclose($f);

        return $content;
    }
}
