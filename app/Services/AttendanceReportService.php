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
            $bFacultyStaff = $bTotal - $bStudents;

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
                'faculty' => 0,
                'percent' => 0.0,
            ],
            'afternoon' => [
                'code' => 'afternoon',
                'title' => $isAmharic ? 'የቀን ፈረቃ (12:00–17:00)' : 'Afternoon Window (12:00–17:00)',
                'time_range' => '12:00 – 17:00 (06:00 – 11:00)',
                'count' => 0,
                'students' => 0,
                'faculty' => 0,
                'percent' => 0.0,
            ],
            'night' => [
                'code' => 'night',
                'title' => $isAmharic ? 'የማታ ፈረቃ (17:00–23:00)' : 'Night Shift Window (17:00–23:00)',
                'time_range' => '17:00 – 23:00 (11:00 – 05:00)',
                'count' => 0,
                'students' => 0,
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
                else $shiftWindows['morning']['faculty'] += $log->visitor_count;
            } elseif ($h >= 12 && $h < 17) {
                $shiftWindows['afternoon']['count'] += $log->visitor_count;
                if ($log->user_type === 'student') $shiftWindows['afternoon']['students'] += $log->visitor_count;
                else $shiftWindows['afternoon']['faculty'] += $log->visitor_count;
            } elseif ($h >= 17 && $h <= 23) {
                $shiftWindows['night']['count'] += $log->visitor_count;
                if ($log->user_type === 'student') $shiftWindows['night']['students'] += $log->visitor_count;
                else $shiftWindows['night']['faculty'] += $log->visitor_count;
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

        // Date & Time formatting
        $fromFormatted = EthiopianDateService::formatDT($dateFrom, $isAmharic);
        $toFormatted = EthiopianDateService::formatDT($dateTo, $isAmharic);
        $generatedAt = EthiopianDateService::formatDT(now(), $isAmharic);

        $libraryName = $selectedLibrary
            ? ($isAmharic ? ($selectedLibrary->name_am ?? $selectedLibrary->name) : $selectedLibrary->name)
            : ($isAmharic ? 'ሁሉም የዩኒቨርሲቲው ቤተ-መጻሕፍት ቅርንጫፎች' : 'All University Library Branches (Central System)');

        // Reference Number
        $hashSeed = ($selectedLibrary?->id ?? 'ALL') . $dateFromStr . $dateToStr . $totalCount;
        $refNumber = 'AAU-LIB-' . date('Y') . '-' . strtoupper(substr(md5($hashSeed), 0, 8));

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
        $descriptionEn = "This official periodic attendance and patron demographic analytics report is compiled by the Directorate of University Library and Information Services in accordance with the Academic Governance and Library Utilization Policy. The audit encompasses physical gate foot-traffic, patron categorization (undergraduate and postgraduate students, teaching and research faculty, and administrative staff), and gender distribution across campus library facilities ({$libraryName}). The reporting window spans from {$fromFormatted['full']} to {$toFormatted['full']}. The primary objective is to evaluate campus learning resource utilization, monitor shift peak occupancy, maintain institutional audit transparency, and support data-driven allocation of facility infrastructure, study desk seating, and catalog staffing.";

        $descriptionAm = "ይህ ይፋዊ የቤተ-መጻሕፍት አጠቃቀም እና የተጠቃሚዎች ስነ-ሕዝብ ትንተና ሪፖርት በዩኒቨርሲቲው የቤተ-መጻሕፍት እና መረጃ አገልግሎት ዳይሬክቶሬት የአካዳሚክ አስተዳደርና የቤተ-መጻሕፍት አጠቃቀም መመሪያን መሠረት በማድረግ የተዘጋጀ ነው። ሪፖርቱ በዩኒቨርሲቲው የቤተ-መጻሕፍት ቅርንጫፎች ({$libraryName}) የተመዘገቡ የቀጥታ ጎብኝዎችን፣ የተጠቃሚዎችን የትምህርትና የስራ ደረጃ (የቅድመ-ምረቃና ድህረ-ምረቃ ተማሪዎች፣ መምህራንና ተመራማሪዎች፣ የአስተዳደርና ቴክኒክ ሠራተኞች) እንዲሁም የጾታ ስርጭት ትንተናን በዝርዝር ያካትታል። የሪፖርቱ ሽፋን ጊዜ ከ {$fromFormatted['full']} እስከ {$toFormatted['full']} ነው። የዚህ ሪፖርት ዋነኛ ዓላማ የቤተ-መጻሕፍት ሀብት አጠቃቀምን መገምገም፣ የፈረቃ ተረኝነትንና ከፍተኛ የተጠቃሚዎች ሰዓትን መለየት፣ የተቋማዊ ቁጥጥር ግልጽነትን ማስጠበቅ እና በመረጃ ላይ የተመሠረተ የሀብትና የሰው ኃይል ድልድል ማከናወን ነው።";

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
        $output .= '   <Column ss:Width="160"/>' . "\n";
        $output .= '   <Column ss:Width="100"/>' . "\n";
        $output .= '   <Column ss:Width="100"/>' . "\n";
        $output .= '   <Column ss:Width="100"/>' . "\n";
        $output .= '   <Column ss:Width="100"/>' . "\n";

        // Title Row
        $output .= '   <Row ss:Height="28"><Cell ss:MergeAcross="4" ss:StyleID="HeaderTitle"><Data ss:Type="String">' . htmlspecialchars($title) . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:MergeAcross="4" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($data['libraryName'] . ' | ' . $data['dateFromStr']['date'] . ' – ' . $data['dateToStr']['date']) . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="10"><Cell ss:MergeAcross="4"/></Row>' . "\n";

        // Section 1: KPIs
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="4" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '1. ዋና ዋና የአፈጻጸም ጠቋሚዎች (KPIs)' : '1. Key Performance Indicators (KPIs)') . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ጎብኝዎች' : 'Total Visitors Count') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['totalCount'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ወንድ ጎብኝዎች' : 'Male Visitors') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['maleCount'] . '</Data></Cell><Cell ss:StyleID="CellText"><Data ss:Type="String">' . $data['malePercent'] . '%</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ሴት ጎብኝዎች' : 'Female Visitors') . '</Data></Cell><Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $data['femaleCount'] . '</Data></Cell><Cell ss:StyleID="CellText"><Data ss:Type="String">' . $data['femalePercent'] . '%</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window') . '</Data></Cell><Cell ss:MergeAcross="1" ss:StyleID="CellText"><Data ss:Type="String">' . $data['peakHourLabel'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row><Cell ss:StyleID="CellText"><Data ss:Type="String">' . ($isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Campus Branch') . '</Data></Cell><Cell ss:MergeAcross="1" ss:StyleID="CellText"><Data ss:Type="String">' . $data['busiestBranchName'] . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="4"/></Row>' . "\n";

        // Section 2: Demographic Breakdown
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="4" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '2. የተጠቃሚዎች ስነ-ሕዝብና ምድብ ስርጭት' : '2. Patron Demographic Distribution') . '</Data></Cell></Row>' . "\n";
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
        $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="4"/></Row>' . "\n";

        // Section 3: Branch Facilities Comparison
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="4" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር' : '3. Library Branch Facility Comparison') . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="18">' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የቅርንጫፍ ስም' : 'Branch Facility') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ኮድ' : 'Code') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Traffic Share (%)') . '</Data></Cell>' . "\n";
        $output .= '   </Row>' . "\n";

        foreach ($data['branchComparison'] as $branch) {
            $output .= '   <Row>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($branch['name']) . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $branch['code'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $branch['students'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $branch['total'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $branch['percent'] . '%</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";
        }
        $output .= '   <Row ss:Height="14"><Cell ss:MergeAcross="4"/></Row>' . "\n";

        // Section 4: Operational Shift Windows
        $output .= '   <Row ss:Height="20"><Cell ss:MergeAcross="4" ss:StyleID="SectionHeader"><Data ss:Type="String">' . ($isAmharic ? '4. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት' : '4. Operational Shift Windows & Density') . '</Data></Cell></Row>' . "\n";
        $output .= '   <Row ss:Height="18">' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'የሰዓት ገደብ' : 'Time Interval') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ተማሪ' : 'Students') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ጠቅላላ' : 'Total Patrons') . '</Data></Cell>' . "\n";
        $output .= '    <Cell ss:StyleID="TableHead"><Data ss:Type="String">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</Data></Cell>' . "\n";
        $output .= '   </Row>' . "\n";

        foreach ($data['shiftWindows'] as $shift) {
            $output .= '   <Row>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . htmlspecialchars($shift['title']) . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellText"><Data ss:Type="String">' . $shift['time_range'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $shift['students'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">' . $shift['count'] . '</Data></Cell>' . "\n";
            $output .= '    <Cell ss:StyleID="CellNumber"><Data ss:Type="String">' . $shift['percent'] . '%</Data></Cell>' . "\n";
            $output .= '   </Row>' . "\n";
        }

        $output .= '  </Table>' . "\n";
        $output .= ' </Worksheet>' . "\n";

        // Worksheet 2: Detailed Entries Audit Registry
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
        $html .= '<h1>' . ($isAmharic ? 'አዲስ አበባ ዩኒቨርሲቲ' : 'ADDIS ABABA UNIVERSITY') . '</h1>';
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
        $html .= '<tr><th>' . ($isAmharic ? 'የቅርንጫፍ ስም' : 'Campus Branch') . '</th><th>' . ($isAmharic ? 'ኮድ' : 'Code') . '</th><th class="text-right">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Traffic Share (%)') . '</th></tr>';

        foreach ($data['branchComparison'] as $branch) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($branch['name']) . '</td>';
            $html .= '<td>' . $branch['code'] . '</td>';
            $html .= '<td class="text-right">' . number_format($branch['students']) . '</td>';
            $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($branch['total']) . '</td>';
            $html .= '<td class="text-right">' . $branch['percent'] . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        // Section 4: Operational Shift Windows
        $html .= '<h3>' . ($isAmharic ? '4. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት' : '4. Operational Shift Windows & Density') . '</h3>';
        $html .= '<table>';
        $html .= '<tr><th>' . ($isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window') . '</th><th>' . ($isAmharic ? 'የሰዓት ገደብ' : 'Time Interval') . '</th><th class="text-right">' . ($isAmharic ? 'ተማሪዎች' : 'Students') . '</th><th class="text-right">' . ($isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons') . '</th><th class="text-right">' . ($isAmharic ? 'ድርሻ (%)' : 'Share (%)') . '</th></tr>';

        foreach ($data['shiftWindows'] as $shift) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($shift['title']) . '</td>';
            $html .= '<td>' . $shift['time_range'] . '</td>';
            $html .= '<td class="text-right">' . number_format($shift['students']) . '</td>';
            $html .= '<td class="text-right" style="font-weight:bold;">' . number_format($shift['count']) . '</td>';
            $html .= '<td class="text-right">' . $shift['percent'] . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

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
        fputcsv($f, [$isAmharic ? 'የዩኒቨርሲቲ ቤተ-መጻሕፍት ይፋዊ የአጠቃቀምና የስነ-ሕዝብ ትንተና ሪፖርት' : 'OFFICIAL ACADEMIC LIBRARY ATTENDANCE & DEMOGRAPHIC ANALYTICS REPORT']);
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

        // Granular Records
        fputcsv($f, [$isAmharic ? '--- 3. የቀጥታ ምዝገባዎች ዝርዝር መዝገብ ---' : '--- 3. DETAILED ENTRIES REGISTRY ---']);
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
