@php
    $scope = $scope ?? 'full';
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
    $pageTitle = $isAmharic ? ($scopeTitles[$scope]['am'] ?? $scopeTitles['full']['am']) : ($scopeTitles[$scope]['en'] ?? $scopeTitles['full']['en']);
    $fontRegular = public_path('fonts/NotoSansEthiopic-Regular.ttf');
    $fontBold = public_path('fonts/NotoSansEthiopic-Bold.ttf');
    if (!file_exists($fontRegular)) {
        $fontRegular = '/usr/share/fonts/truetype/noto/NotoSansEthiopic-Regular.ttf';
    }
    if (!file_exists($fontBold)) {
        $fontBold = '/usr/share/fonts/truetype/noto/NotoSansEthiopic-Bold.ttf';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $isAmharic ? 'am' : 'en' }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <title>{{ $pageTitle }} - {{ $libraryName }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;600;700&display=swap');

        @font-face {
            font-family: 'NotoSansEthiopic';
            font-style: normal;
            font-weight: normal;
            src: url('{{ $fontRegular }}') format('truetype');
        }
        @font-face {
            font-family: 'NotoSansEthiopic';
            font-style: normal;
            font-weight: bold;
            src: url('{{ $fontBold }}') format('truetype');
        }

        @media print {
            @page {
                margin: 12mm 10mm 12mm 10mm;
                size: A4 portrait;
            }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
        }

        body {
            font-family: "NotoSansEthiopic", "Nyala", "Abyssinica SIL", "Segoe UI Historic", "DejaVu Sans", Arial, sans-serif;
            color: #111827;
            background: #f8fafc;
            margin: 0;
            padding: 20px;
            font-size: 10.5px;
            line-height: 1.4;
        }

        .report-page {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #d1d5db;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            padding: 28px 32px;
        }

        /* Classical Institutional University Header using Table for DomPDF compatibility */
        .inst-header-table {
            width: 100%;
            border-bottom: 2.5px solid #0b529c;
            padding-bottom: 12px;
            margin-bottom: 14px;
            border-collapse: collapse;
        }

        .inst-header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .inst-logo-cell {
            width: 52px;
            text-align: center;
        }

        .inst-logo {
            width: 44px;
            height: 44px;
            line-height: 44px;
            border-radius: 6px;
            background: #0b529c;
            color: #ffffff;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            display: inline-block;
        }

        .inst-title {
            margin: 0;
            font-size: 15px;
            font-weight: bold;
            color: #0b529c;
            text-transform: uppercase;
            letter-spacing: -0.2px;
        }

        .inst-sub {
            margin: 2px 0 0;
            font-size: 10.5px;
            color: #374151;
            font-weight: bold;
        }

        .inst-office {
            margin: 1px 0 0;
            font-size: 9.5px;
            color: #6b7280;
            font-weight: normal;
        }

        .ref-box {
            text-align: right;
            border-left: 2px solid #e5e7eb;
            padding-left: 12px;
        }

        .ref-pill {
            display: inline-block;
            padding: 2px 8px;
            background: #eff6ff;
            color: #0b529c;
            font-size: 9px;
            font-weight: bold;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .ref-id {
            font-family: monospace;
            font-size: 10px;
            font-weight: bold;
            color: #374151;
            margin-top: 3px;
        }

        /* Classical Document Metadata Table */
        .doc-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 10px;
        }

        .doc-meta-table td {
            border: 1px solid #d1d5db;
            padding: 5px 8px;
        }

        .doc-meta-table td.label {
            background: #f3f4f6;
            color: #374151;
            font-weight: bold;
            width: 22%;
            text-transform: uppercase;
            font-size: 8.5px;
        }

        .doc-meta-table td.val {
            color: #111827;
            font-weight: normal;
            width: 28%;
        }

        /* Section Headings */
        .section-header {
            font-size: 10.5px;
            font-weight: bold;
            color: #0b529c;
            text-transform: uppercase;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #0b529c;
        }

        /* Formal Data Tables */
        table.formal-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 10px;
        }

        table.formal-table th {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #94a3b8;
            padding: 5px 7px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
        }

        table.formal-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 7px;
            color: #1e293b;
        }

        table.formal-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        table.formal-table tr.total-row td {
            background: #f1f5f9;
            font-weight: bold;
            border-top: 2px solid #0b529c;
            color: #0b529c;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* KPI Grid Table */
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .kpi-cell {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            vertical-align: top;
            width: 33.33%;
            background: #fcfcfd;
        }

        .kpi-cell.highlight {
            background: #eff6ff;
            border-color: #93c5fd;
        }

        .kpi-tag {
            font-size: 8.5px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }

        .kpi-val {
            font-size: 18px;
            font-weight: bold;
            color: #0b529c;
            line-height: 1.1;
        }

        .kpi-sub {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Signature Blocks using Table for DomPDF compatibility */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 28px;
            page-break-inside: avoid;
        }

        .sig-table td {
            border: none;
            padding: 0 8px;
            vertical-align: top;
            width: 33.33%;
        }

        .sig-card {
            border-top: 1.5px solid #374151;
            padding-top: 6px;
            text-align: center;
        }

        .sig-role {
            font-size: 9px;
            font-weight: bold;
            color: #374151;
            text-transform: uppercase;
        }

        .sig-name {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
            margin: 2px 0;
        }

        .sig-date {
            font-size: 8.5px;
            color: #6b7280;
        }

        /* Screen Toolbar */
        .toolbar {
            max-width: 900px;
            margin: 0 auto 14px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 10px 16px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }

        button {
            font-family: inherit;
        }

        .btn-action {
            background: #0b529c;
            color: #ffffff;
            border: none;
            padding: 7px 14px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
        }

        .btn-sec {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 7px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            margin-left: 8px;
            font-family: inherit;
        }
    </style>
</head>
<body>
    @if(empty($isPdf))
    <!-- Interactive Print & Export Bar (Screen Only) -->
    <div class="toolbar no-print">
        <span style="font-weight: bold; font-size: 11px; color: #0b529c;">
            {{ $isAmharic ? 'ይፋዊ ሰነድ ዝግጁ ነው' : 'Official Document Ready' }} - {{ $pageTitle }}
        </span>
        <div>
            <button class="btn-action" onclick="window.print()">
                {{ $isAmharic ? 'አትም / በፒዲኤፍ አስቀምጥ (Print / PDF)' : 'Print / Save as PDF' }}
            </button>
            <button class="btn-sec" onclick="window.close()">
                {{ $isAmharic ? 'ዝጋ' : 'Close' }}
            </button>
        </div>
    </div>
    @endif

    <div class="report-page">
        <!-- University Academic Central Header -->
        <table class="inst-header-table">
            <tr>
                <td class="inst-logo-cell">
                    <div class="inst-logo" style="background:#0b529c; border-radius:6px; width:44px; height:44px; text-align:center; line-height:44px; color:#ffffff; font-size:15px; font-weight:bold; font-family:Arial,sans-serif; letter-spacing:0.5px;">WDU</div>
                </td>
                <td style="padding-left: 10px;">
                    <h1 class="inst-title">{{ $isAmharic ? 'ወልድያ ዩኒቨርሲቲ' : 'WOLDIA UNIVERSITY' }}</h1>
                    <div class="inst-sub">{{ $isAmharic ? 'የዩኒቨርሲቲ ቤተ-መጻሕፍት እና መረጃ አገልግሎት ዳይሬክቶሬት' : 'Directorate of University Library & Information Services' }}</div>
                    <div class="inst-office">{{ $pageTitle }}</div>
                </td>
                <td class="ref-box" style="width: 170px;">
                    <div class="ref-pill">{{ $isAmharic ? 'ይፋዊ ሰነድ' : 'Official Audit' }}</div>
                    <div class="ref-id">{{ $refNumber }}</div>
                </td>
            </tr>
        </table>

        <!-- Document Metadata Table -->
        <table class="doc-meta-table">
            <tr>
                <td class="label">{{ $isAmharic ? 'የሰነድ መለያ ቁጥር' : 'Document Ref No' }}</td>
                <td class="val" style="font-family: monospace;">{{ $refNumber }}</td>
                <td class="label">{{ $isAmharic ? 'የተዘጋጀበት ቀንና ሰዓት' : 'Issuance Date' }}</td>
                <td class="val">{{ $generatedAt['date'] }} · {{ $generatedAt['time'] }}</td>
            </tr>
            <tr>
                <td class="label">{{ $isAmharic ? 'የሪፖርቱ ሽፋን ጊዜ' : 'Reporting Window' }}</td>
                <td class="val">
                    @if($isSingleDay)
                        {{ $dateFromStr['full'] }}
                    @else
                        {{ $dateFromStr['date'] }} – {{ $dateToStr['date'] }}
                    @endif
                </td>
                <td class="label">{{ $isAmharic ? 'የቤተ-መጻሕፍት ቅርንጫፍ' : 'Facility Scope' }}</td>
                <td class="val">{{ $libraryName }}</td>
            </tr>
            <tr>
                <td class="label">{{ $isAmharic ? 'ያዘጋጀው ኃላፊ/ሠራተኛ' : 'Reporting Officer' }}</td>
                <td class="val">{{ $generatedBy }} ({{ $generatedByUsername }})</td>
                <td class="label">{{ $isAmharic ? 'የኦዲት ወሰን' : 'Audit Scope' }}</td>
                <td class="val" style="color: #0b529c; font-weight: bold;">{{ strtoupper($scope) }}</td>
            </tr>
        </table>

        @if($scope === 'full')
        <!-- Section 1: KPI Highlights Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? '1. ዋና ዋና የአፈጻጸም ጠቋሚዎች (KPIs)' : '1. Key Performance Indicators (KPIs)' }}</span>
        </div>
        <table class="kpi-table">
            <tr>
                <td class="kpi-cell highlight">
                    <div class="kpi-tag">{{ $isAmharic ? 'ጠቅላላ የተጠቃሚዎች ብዛት' : 'Total Patron Traffic' }}</div>
                    <div class="kpi-val">{{ number_format($totalCount) }}</div>
                    <div class="kpi-sub">{{ $isAmharic ? 'በተመረጠው የጊዜ ገደብ የገቡ' : 'Aggregated campus visits' }}</div>
                </td>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ወንድ ተጠቃሚዎች' : 'Male Patrons' }}</div>
                    <div class="kpi-val">{{ number_format($maleCount) }}</div>
                    <div class="kpi-sub">{{ $malePercent }}% {{ $isAmharic ? 'የጠቅላላው ድርሻ' : 'of recorded traffic' }}</div>
                </td>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ሴት ተጠቃሚዎች' : 'Female Patrons' }}</div>
                    <div class="kpi-val">{{ number_format($femaleCount) }}</div>
                    <div class="kpi-sub">{{ $femalePercent }}% {{ $isAmharic ? 'የጠቅላላው ድርሻ' : 'of recorded traffic' }}</div>
                </td>
            </tr>
            <tr>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window' }}</div>
                    <div class="kpi-val" style="font-size: 15px;">{{ $peakHourLabel }}</div>
                    <div class="kpi-sub">{{ number_format($peakHourCount) }} {{ $isAmharic ? 'ተጠቃሚዎች በሰዓት' : 'peak visitors' }}</div>
                </td>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Branch' }}</div>
                    <div class="kpi-val" style="font-size: 15px;">{{ $busiestBranchName }}</div>
                    <div class="kpi-sub">{{ $isAmharic ? 'ከፍተኛ የተማሪዎች ቁጥር' : 'Highest density facility' }}</div>
                </td>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ዋነኛ የተጠቃሚ ምድብ' : 'Dominant Patron Group' }}</div>
                    <div class="kpi-val" style="font-size: 15px;">{{ $dominantPatronLabel }}</div>
                    <div class="kpi-sub">{{ $demographics['student']['percent'] }}% {{ $isAmharic ? 'የጠቅላላው ድርሻ' : 'overall share' }}</div>
                </td>
            </tr>
        </table>
        @endif

        @if($scope === 'full' || $scope === 'patron_types')
        <!-- Section 2: Patron Category Demographic Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? ($scope === 'patron_types' ? 'የተጠቃሚዎች ምድብና ስነ-ሕዝብ ዝርዝር' : '2. የተጠቃሚዎች ምድብና ስነ-ሕዝብ ዝርዝር') : ($scope === 'patron_types' ? 'Patron Demographic Categorization' : '2. Patron Demographic Categorization') }}</span>
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 45%;">{{ $isAmharic ? 'የተጠቃሚ ምድብ' : 'Patron Category' }}</th>
                    <th class="text-right" style="width: 12%;">{{ $isAmharic ? 'ወንድ' : 'Male' }}</th>
                    <th class="text-right" style="width: 12%;">{{ $isAmharic ? 'ሴት' : 'Female' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'ጠቅላላ ድምር' : 'Subtotal' }}</th>
                    <th class="text-right" style="width: 16%;">{{ $isAmharic ? 'የድርሻ መጠን (%)' : 'Traffic Share (%)' }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><b>{{ $isAmharic ? 'ተማሪዎች (ቅድመ-ምረቃና ድህረ-ምረቃ)' : 'Students (Undergraduate & Postgraduate)' }}</b></td>
                    <td class="text-right">{{ number_format($demographics['student']['male']) }}</td>
                    <td class="text-right">{{ number_format($demographics['student']['female']) }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($demographics['student']['total']) }}</td>
                    <td class="text-right">{{ $demographics['student']['percent'] }}%</td>
                </tr>
                <tr>
                    <td><b>{{ $isAmharic ? 'መምህራንና ተመራማሪዎች' : 'Faculty & Research Scholars' }}</b></td>
                    <td class="text-right">{{ number_format($demographics['teacher']['male']) }}</td>
                    <td class="text-right">{{ number_format($demographics['teacher']['female']) }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($demographics['teacher']['total']) }}</td>
                    <td class="text-right">{{ $demographics['teacher']['percent'] }}%</td>
                </tr>
                <tr>
                    <td><b>{{ $isAmharic ? 'የአስተዳደርና ቴክኒክ ሠራተኞች' : 'Administrative & Technical Staff' }}</b></td>
                    <td class="text-right">{{ number_format($demographics['staff']['male']) }}</td>
                    <td class="text-right">{{ number_format($demographics['staff']['female']) }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($demographics['staff']['total']) }}</td>
                    <td class="text-right">{{ $demographics['staff']['percent'] }}%</td>
                </tr>
                <tr class="total-row">
                    <td><b>{{ $isAmharic ? 'አጠቃላይ ድምር' : 'Grand Total' }}</b></td>
                    <td class="text-right">{{ number_format($maleCount) }}</td>
                    <td class="text-right">{{ number_format($femaleCount) }}</td>
                    <td class="text-right" style="font-size: 11px;">{{ number_format($totalCount) }}</td>
                    <td class="text-right">100.0%</td>
                </tr>
            </tbody>
        </table>
        @endif

        @if($scope === 'full' || $scope === 'branches')
        <!-- Section 3: Branch Facilities Comparison Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? '3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር' : '3. Library Branch Facility Distribution' }}</span>
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 25%;">{{ $isAmharic ? 'የቅርንጫፍ ስም' : 'Campus Branch' }}</th>
                    <th style="width: 9%;">{{ $isAmharic ? 'ኮድ' : 'Code' }}</th>
                    <th class="text-right" style="width: 13%;">{{ $isAmharic ? 'ተማሪዎች' : 'Students' }}</th>
                    <th class="text-right" style="width: 13%;">{{ $isAmharic ? 'መምህራን' : 'Teachers' }}</th>
                    <th class="text-right" style="width: 13%;">{{ $isAmharic ? 'ሠራተኞች' : 'Staff' }}</th>
                    <th class="text-right" style="width: 14%;">{{ $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons' }}</th>
                    <th class="text-right" style="width: 13%;">{{ $isAmharic ? 'የድርሻ መጠን (%)' : 'Traffic Share (%)' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($branchComparison as $branch)
                    <tr>
                        <td><b>{{ $branch['name'] }}</b></td>
                        <td><span style="font-family: monospace; font-weight: bold;">{{ $branch['code'] }}</span></td>
                        <td class="text-right">{{ number_format($branch['students']) }}</td>
                        <td class="text-right">{{ number_format($branch['teachers'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($branch['staff'] ?? 0) }}</td>
                        <td class="text-right" style="font-weight: bold;">{{ number_format($branch['total']) }}</td>
                        <td class="text-right">{{ $branch['percent'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 10px; color: #6b7280;">{{ $isAmharic ? 'መረጃ አልተገኘም' : 'No branch data recorded' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        @if($scope === 'full' || $scope === 'day_of_week')
        <!-- Section 4: Operational Shift Windows Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? '4. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት' : '4. Operational Shift Windows & Density' }}</span>
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 20%;">{{ $isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window' }}</th>
                    <th style="width: 18%;">{{ $isAmharic ? 'የሰዓት ገደብ' : 'Time Interval' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'ተማሪዎች' : 'Students' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'መምህራን' : 'Teachers' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'ሠራተኞች' : 'Staff' }}</th>
                    <th class="text-right" style="width: 12%;">{{ $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons' }}</th>
                    <th class="text-right" style="width: 9%;">{{ $isAmharic ? 'ድርሻ (%)' : 'Share (%)' }}</th>
                    <th style="width: 11%;">{{ $isAmharic ? 'የጥግግት ደረጃ' : 'Congestion' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($shiftWindows as $shift)
                    <tr>
                        <td><b>{{ $shift['title'] }}</b></td>
                        <td>{{ $shift['time_range'] }}</td>
                        <td class="text-right">{{ number_format($shift['students']) }}</td>
                        <td class="text-right">{{ number_format($shift['teachers'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($shift['staff'] ?? 0) }}</td>
                        <td class="text-right" style="font-weight: bold;">{{ number_format($shift['count']) }}</td>
                        <td class="text-right">{{ $shift['percent'] }}%</td>
                        <td>{{ $isAmharic ? ($shift['congestion_index_am'] ?? '—') : ($shift['congestion_index'] ?? '—') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Section 5: Day-of-Week Operational Foot-Traffic Analysis Table -->
        @if(!empty($dayOfWeekAnalytics))
        <div class="section-header">
            <span>{{ $isAmharic ? '5. ሰኞ – እሑድ የሳምንት ቀናት የተጠቃሚዎች ፍሰት ትንተና' : '5. Day-of-the-Week Operational Foot-Traffic Analysis' }}</span>
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 16%;">{{ $isAmharic ? 'የሳምንቱ ዕለት' : 'Day of Week' }}</th>
                    <th class="text-right" style="width: 12%;">{{ $isAmharic ? 'አማካይ በቀን' : 'Daily Avg' }}</th>
                    <th class="text-right" style="width: 12%;">{{ $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'ድርሻ (%)' : 'Share (%)' }}</th>
                    <th style="width: 18%;">{{ $isAmharic ? 'የፈረቃ ክፍፍል (ጠ/ቀ/ማ)' : 'Morn / Aft / Night' }}</th>
                    <th style="width: 14%;">{{ $isAmharic ? 'የፍሰት ደረጃ' : 'Surge Level' }}</th>
                    <th style="width: 18%;">{{ $isAmharic ? 'የአስተዳደር ትንበያ' : 'Management Prediction' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dayOfWeekAnalytics as $dow)
                    <tr>
                        <td><b>{{ $dow['display_name'] }}</b> {{ $dow['is_peak'] ? ($isAmharic ? '[ከፍተኛ]' : '[Peak]') : '' }}</td>
                        <td class="text-right" style="font-weight: bold; color: #0b529c;">{{ number_format($dow['daily_average']) }}</td>
                        <td class="text-right">{{ number_format($dow['total']) }}</td>
                        <td class="text-right">{{ $dow['percentage'] }}%</td>
                        <td style="font-family: monospace; font-size: 8.5px;">{{ $dow['morning'] }} / {{ $dow['afternoon'] }} / {{ $dow['night'] }}</td>
                        <td><span style="font-weight: bold; color: {{ $dow['is_peak'] ? '#dc2626' : '#2563eb' }};">{{ $isAmharic ? $dow['surge_level_am'] : $dow['surge_level'] }}</span></td>
                        <td style="font-size: 8.5px;">{{ $isAmharic ? $dow['prediction_am'] : $dow['prediction'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        @endif

        @if($scope === 'full' || $scope === 'intra_month')
        <!-- Section 6: Intra-Month Day Recurring Occurrence & Weekly Cycle Matrix -->
        @if(!empty($intraMonthDayAnalytics))
        <div class="section-header">
            <span>{{ $isAmharic ? '6. በወሩ ውስጥ ያሉ ሳምንታትና የዕለታት ድግግሞሽ ትንተና' : '6. Intra-Month Recurring Day Occurrence & Weekly Cycle Matrix' }}</span>
        </div>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 10px; margin-bottom: 8px; font-size: 9.5px; color: #166534; border-radius: 3px;">
            <b>{{ $isAmharic ? 'የአስተዳደር የቅድመ-ትንበያ ማስጠንቀቂያ፡ ' : 'Management Influx Prediction: ' }}</b>
            {{ $intraMonthDayAnalytics['prediction'] }}
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 25%;">{{ $isAmharic ? 'የወሩ ሳምንታዊ ክፍለ-ጊዜ' : 'Weekly Cycle in Month' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'ጠቅላላ ተጠቃሚ' : 'Total Patrons' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'አማካይ በቀን' : 'Daily Average' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'የወሩ ድርሻ (%)' : 'Monthly Share (%)' }}</th>
                    <th style="width: 30%;">{{ $isAmharic ? 'የአሰራርና የጥናት ሁኔታ' : 'Operational Status' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($intraMonthDayAnalytics['weekly_cycles'] as $wCycle)
                    <tr>
                        <td><b>{{ $isAmharic ? $wCycle['label_am'] : $wCycle['label'] }}</b> {{ $wCycle['is_peak_week'] ? ($isAmharic ? '[ከፍተኛ]' : '[Peak]') : '' }}</td>
                        <td class="text-right" style="font-weight: bold;">{{ number_format($wCycle['total']) }}</td>
                        <td class="text-right">{{ number_format($wCycle['daily_average']) }}</td>
                        <td class="text-right">{{ $wCycle['percent'] }}%</td>
                        <td>{{ $isAmharic ? $wCycle['prediction_am'] : $wCycle['prediction'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="formal-table" style="margin-top: 6px;">
            <thead>
                <tr>
                    <th style="width: 15%;">{{ $isAmharic ? 'የሳምንት ዕለት' : 'Day of Week' }}</th>
                    <th style="width: 17%;">{{ $isAmharic ? '1ኛ ድግግሞሽ (ቀናት 1–7)' : '1st Occurrence (Days 1–7)' }}</th>
                    <th style="width: 17%;">{{ $isAmharic ? '2ኛ ድግግሞሽ (ቀናት 8–14)' : '2nd Occurrence (Days 8–14)' }}</th>
                    <th style="width: 17%;">{{ $isAmharic ? '3ኛ ድግግሞሽ (ቀናት 15–21)' : '3rd Occurrence (Days 15–21)' }}</th>
                    <th style="width: 17%;">{{ $isAmharic ? '4ኛ ድግግሞሽ (ቀናት 22–28)' : '4th Occurrence (Days 22–28)' }}</th>
                    <th style="width: 17%;">{{ $isAmharic ? '5ኛ ድግግሞሽ (ቀናት 29–31)' : '5th Occurrence (Days 29–31)' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($intraMonthDayAnalytics['occurrences_by_day'] as $occDay)
                    <tr>
                        <td><b>{{ $occDay['display_name'] }}</b></td>
                        @foreach($occDay['occurrences'] as $oc)
                            <td>
                                <b>{{ number_format($oc['average']) }}</b>
                                <span style="font-size: 8px; color: {{ $oc['is_peak'] ? '#dc2626' : '#475569' }};">({{ $isAmharic ? $oc['surge_badge_am'] : $oc['surge_badge'] }})</span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        @endif

        @if($scope === 'full')
        <!-- Section 7: Longitudinal Month-over-Month & Year-over-Year Trajectory Table -->
        @if(!empty($monthlyAnalytics) || !empty($yearlyAnalytics))
        <div class="section-header">
            <span>{{ $isAmharic ? '7. የወራትና የዓመታት ንጽጽር ትንተና' : '7. Longitudinal Month-over-Month & Year-over-Year Trajectory' }}</span>
        </div>
        @if(!empty($monthlyAnalytics))
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 20%;">{{ $isAmharic ? 'የሂሳብ ወር' : 'Academic Month' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'ጠቅላላ' : 'Total' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'ተማሪ' : 'Students' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'መምህር' : 'Teachers' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'ሠራተኛ' : 'Staff' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'አማካይ/ቀን' : 'Avg/Day' }}</th>
                    <th class="text-right" style="width: 13%;">{{ $isAmharic ? 'እድገት (MoM)' : 'Growth (MoM)' }}</th>
                    <th style="width: 17%;">{{ $isAmharic ? 'ከፍተኛ ዕለት' : 'Peak Weekday' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyAnalytics as $mInfo)
                    <tr>
                        <td><b>{{ $mInfo['display_name'] }}</b> {{ $mInfo['is_peak_month'] ? ($isAmharic ? '[ከፍተኛ]' : '[Peak]') : '' }}</td>
                        <td class="text-right" style="font-weight: bold;">{{ number_format($mInfo['total']) }}</td>
                        <td class="text-right">{{ number_format($mInfo['students'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($mInfo['teachers'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($mInfo['staff'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($mInfo['daily_avg']) }}</td>
                        <td class="text-right" style="font-weight: bold; color: {{ str_starts_with($mInfo['growth_rate'], '+') ? '#16a34a' : '#475569' }};">{{ $mInfo['growth_rate'] }}</td>
                        <td>{{ $mInfo['peak_day'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(!empty($yearlyAnalytics))
        <table class="formal-table" style="margin-top: 6px;">
            <thead>
                <tr>
                    <th style="width: 25%;">{{ $isAmharic ? 'የአካዳሚክ ዓመት' : 'Academic Year' }}</th>
                    <th class="text-right" style="width: 25%;">{{ $isAmharic ? 'ጠቅላላ የዓመት ተጠቃሚ' : 'Annual Total Visitors' }}</th>
                    <th class="text-right" style="width: 25%;">{{ $isAmharic ? 'አማካይ በቀን' : 'Daily Average' }}</th>
                    <th class="text-right" style="width: 25%;">{{ $isAmharic ? 'የዓመት እድገት (YoY)' : 'Yearly Growth Rate' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($yearlyAnalytics as $yInfo)
                    <tr>
                        <td><b>{{ $yInfo['display_year'] }}</b></td>
                        <td class="text-right" style="font-weight: bold;">{{ number_format($yInfo['total']) }}</td>
                        <td class="text-right">{{ number_format($yInfo['daily_avg']) }}</td>
                        <td class="text-right" style="font-weight: bold; color: {{ str_starts_with($yInfo['growth_rate'], '+') ? '#16a34a' : '#475569' }};">{{ $yInfo['growth_rate'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        @endif
        @endif

        <!-- Formal Administrative Clearance & Seal Blocks using Table -->
        <table class="sig-table">
            <tr>
                <td>
                    <div class="sig-card">
                        <div class="sig-role">{{ $isAmharic ? 'የመዝጋቢው ቤተ-መጻሕፍት ሠራተኛ' : 'Duty Desk Librarian' }}</div>
                        <div class="sig-name">{{ $generatedBy }}</div>
                        <div class="sig-date">{{ $generatedAt['date'] }}</div>
                    </div>
                </td>
                <td>
                    <div class="sig-card">
                        <div class="sig-role">{{ $isAmharic ? 'የቅርንጫፍ አስተባባሪ' : 'Branch Supervisor' }}</div>
                        <div class="sig-name">{{ $isAmharic ? 'የተረጋገጠ ፊርማ' : 'Certified & Inspected' }}</div>
                        <div class="sig-date">{{ $generatedAt['date'] }}</div>
                    </div>
                </td>
                <td>
                    <div class="sig-card">
                        <div class="sig-role">{{ $isAmharic ? 'የዋና ቤተ-መጻሕፍት ኃላፊ ማህተም' : 'Chief Librarian Seal' }}</div>
                        <div class="sig-name">{{ $isAmharic ? 'ይፋዊ የአካዳሚክ ማህተም' : 'Authorized Academic Registry' }}</div>
                        <div class="sig-date">{{ $refNumber }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <script>
        // Auto-trigger print prompt if auto_print query is set
        if (window.location.search.includes('auto_print=1')) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 400);
            };
        }
    </script>
</body>
</html>