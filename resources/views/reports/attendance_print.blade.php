<!DOCTYPE html>
<html lang="{{ $isAmharic ? 'am' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $isAmharic ? 'የቤተ-መጻሕፍት አጠቃቀም እና የስነ-ሕዝብ ትንተና ይፋዊ ሪፖርት' : 'Official Library Attendance and Demographic Analytics Report' }} — {{ $libraryName }}</title>
    <style>
        @media print {
            @page {
                margin: 15mm 12mm 15mm 12mm;
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Nyala", "Noto Sans Ethiopic", Arial, sans-serif;
            color: #111827;
            background: #f8fafc;
            margin: 0;
            padding: 24px;
            font-size: 11px;
            line-height: 1.45;
        }

        .report-page {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #d1d5db;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            padding: 32px 36px;
        }

        /* Classical Institutional University Header */
        .inst-header {
            border-bottom: 2px solid #0b529c;
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .inst-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .inst-logo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: #0b529c;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: bold;
            border: 2px solid #073b70;
        }

        .inst-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #0b529c;
            letter-spacing: -0.3px;
            text-transform: uppercase;
        }

        .inst-sub {
            margin: 2px 0 0;
            font-size: 11px;
            color: #4b5563;
            font-weight: 600;
        }

        .inst-office {
            margin: 1px 0 0;
            font-size: 10px;
            color: #6b7280;
            font-weight: 500;
        }

        .ref-box {
            text-align: right;
            border-left: 2px solid #e5e7eb;
            padding-left: 14px;
        }

        .ref-pill {
            display: inline-block;
            padding: 3px 8px;
            background: #eff6ff;
            color: #0b529c;
            font-size: 9.5px;
            font-weight: 700;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .ref-id {
            font-family: monospace;
            font-size: 10px;
            font-weight: 700;
            color: #374151;
            margin-top: 4px;
        }

        /* Classical Document Metadata Table */
        .doc-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 10.5px;
        }

        .doc-meta-table td {
            border: 1px solid #d1d5db;
            padding: 6px 10px;
        }

        .doc-meta-table td.label {
            background: #f3f4f6;
            color: #374151;
            font-weight: 700;
            width: 22%;
            text-transform: uppercase;
            font-size: 9px;
        }

        .doc-meta-table td.val {
            color: #111827;
            font-weight: 600;
            width: 28%;
        }

        /* Executive Description Box */
        .desc-box {
            background: #f9fafb;
            border-left: 3px solid #0b529c;
            border-top: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 14px;
            margin-bottom: 20px;
        }

        .desc-title {
            font-size: 10px;
            font-weight: 800;
            color: #0b529c;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .desc-text {
            margin: 0;
            font-size: 10.5px;
            color: #374151;
            text-align: justify;
            line-height: 1.5;
        }

        /* Section Headings */
        .section-header {
            font-size: 11px;
            font-weight: 800;
            color: #0b529c;
            text-transform: uppercase;
            margin: 18px 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 1.5px solid #0b529c;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Formal Data Tables */
        table.formal-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 10.5px;
        }

        table.formal-table th {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #94a3b8;
            padding: 6px 8px;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            text-align: left;
        }

        table.formal-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            color: #1e293b;
        }

        table.formal-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        table.formal-table tr.total-row td {
            background: #f1f5f9;
            font-weight: 800;
            border-top: 2px solid #0b529c;
            color: #0b529c;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* KPI Grid Table */
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .kpi-cell {
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            vertical-align: top;
            width: 33.33%;
            background: #fcfcfd;
        }

        .kpi-cell.highlight {
            background: #eff6ff;
            border-color: #93c5fd;
        }

        .kpi-tag {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
        }

        .kpi-val {
            font-size: 20px;
            font-weight: 800;
            color: #0b529c;
            margin-top: 2px;
            line-height: 1.1;
        }

        .kpi-sub {
            font-size: 9.5px;
            color: #4b5563;
            margin-top: 3px;
        }

        /* Formal Signature Section */
        .sig-container {
            margin-top: 32px;
            padding-top: 14px;
            border-top: 1px solid #d1d5db;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            page-break-inside: avoid;
        }

        .sig-block {
            border-top: 1px dashed #6b7280;
            padding-top: 6px;
            text-align: center;
        }

        .sig-role {
            font-size: 10px;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
        }

        .sig-name {
            font-size: 9.5px;
            color: #4b5563;
            margin-top: 2px;
        }

        .sig-date {
            font-size: 8.5px;
            color: #9ca3af;
            margin-top: 2px;
            font-family: monospace;
        }

        /* Floating Toolbar for Browser Interaction */
        .toolbar {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.25);
            border-radius: 50px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
        }

        .btn-action {
            background: #0b529c;
            color: #ffffff;
            border: none;
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-sec {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Interactive Print & Export Bar (Screen Only) -->
    <div class="toolbar no-print">
        <span style="font-weight: 700; font-size: 11px; color: #0b529c;">
            {{ $isAmharic ? 'ይፋዊ ሰነድ ዝግጁ ነው' : 'Official Document Ready' }}
        </span>
        <button class="btn-action" onclick="window.print()">
            {{ $isAmharic ? '🖨️ አትም / በፒዲኤፍ አስቀምጥ' : '🖨️ Print / Save as PDF' }}
        </button>
        <button class="btn-sec" onclick="window.close()">
            {{ $isAmharic ? 'ዝጋ' : 'Close Window' }}
        </button>
    </div>

    <div class="report-page">
        <!-- University Academic Central Header -->
        <div class="inst-header">
            <div class="inst-brand">
                <div class="inst-logo">📚</div>
                <div>
                    <h1 class="inst-title">{{ $isAmharic ? 'አዲስ አበባ ዩኒቨርሲቲ' : 'ADDIS ABABA UNIVERSITY' }}</h1>
                    <div class="inst-sub">{{ $isAmharic ? 'የዩኒቨርሲቲ ቤተ-መጻሕፍት እና መረጃ አገልግሎት ዳይሬክቶሬት' : 'Directorate of University Library & Information Services' }}</div>
                    <div class="inst-office">{{ $isAmharic ? 'የቀጥታ አጠቃቀም ቆጣሪና የስነ-ሕዝብ ትንተና ይፋዊ የኦዲት ሪፖርት' : 'Central Attendance Desk & Patron Demographic Analytics Registry' }}</div>
                </div>
            </div>
            <div class="ref-box">
                <div class="ref-pill">{{ $isAmharic ? 'ይፋዊ ሰነድ' : 'Official Audit' }}</div>
                <div class="ref-id">{{ $refNumber }}</div>
            </div>
        </div>

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
                <td class="label">{{ $isAmharic ? 'የሰነዱ ደረጃ' : 'Audit Classification' }}</td>
                <td class="val">{{ $isAmharic ? 'ይፋዊ የተቋም ኦዲት' : 'Academic Administrative Audit' }}</td>
            </tr>
        </table>

        <!-- Executive Narrative Description -->
        <div class="desc-box">
            <div class="desc-title">{{ $isAmharic ? 'የስራ አስፈጻሚ ማጠቃለያና መግለጫ' : 'Executive Context & Regulatory Scope' }}</div>
            <p class="desc-text">{{ $description }}</p>
        </div>

        <!-- Section 1: KPI Highlights Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? '1. ዋና ዋና የአፈጻጸም ጠቋሚዎች (KPIs)' : '1. Key Performance Indicators (KPIs)' }}</span>
            <span style="font-weight: 500; font-size: 9px; color: #64748b;">{{ $isAmharic ? 'ማጠቃለያ ቁጥሮች' : 'Executive Overview' }}</span>
        </div>
        <table class="kpi-table">
            <tr>
                <td class="kpi-cell highlight">
                    <div class="kpi-tag">{{ $isAmharic ? 'ጠቅላላ የጎብኝዎች ብዛት' : 'Total Patron Traffic' }}</div>
                    <div class="kpi-val">{{ number_format($totalCount) }}</div>
                    <div class="kpi-sub">{{ $isAmharic ? 'በተመረጠው የጊዜ ገደብ የገቡ' : 'Aggregated campus visits' }}</div>
                </td>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ወንድ ጎብኝዎች' : 'Male Patrons' }}</div>
                    <div class="kpi-val">{{ number_format($maleCount) }}</div>
                    <div class="kpi-sub">{{ $malePercent }}% {{ $isAmharic ? 'የጠቅላላው ድርሻ' : 'of recorded traffic' }}</div>
                </td>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ሴት ጎብኝዎች' : 'Female Patrons' }}</div>
                    <div class="kpi-val">{{ number_format($femaleCount) }}</div>
                    <div class="kpi-sub">{{ $femalePercent }}% {{ $isAmharic ? 'የጠቅላላው ድርሻ' : 'of recorded traffic' }}</div>
                </td>
            </tr>
            <tr>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ከፍተኛ የተጠቃሚዎች ሰዓት' : 'Peak Traffic Window' }}</div>
                    <div class="kpi-val" style="font-size: 16px;">{{ $peakHourLabel }}</div>
                    <div class="kpi-sub">{{ number_format($peakHourCount) }} {{ $isAmharic ? 'ጎብኝዎች በሰዓት' : 'peak visitors' }}</div>
                </td>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ከፍተኛ ተጠቃሚ ቅርንጫፍ' : 'Busiest Branch' }}</div>
                    <div class="kpi-val" style="font-size: 16px;">{{ $busiestBranchName }}</div>
                    <div class="kpi-sub">{{ $isAmharic ? 'ከፍተኛ የተማሪዎች ቁጥር' : 'Highest density facility' }}</div>
                </td>
                <td class="kpi-cell">
                    <div class="kpi-tag">{{ $isAmharic ? 'ዋነኛ የተጠቃሚ ምድብ' : 'Dominant Patron Group' }}</div>
                    <div class="kpi-val" style="font-size: 16px;">{{ $dominantPatronLabel }}</div>
                    <div class="kpi-sub">{{ $demographics['student']['percent'] }}% {{ $isAmharic ? 'የጠቅላላው ድርሻ' : 'overall share' }}</div>
                </td>
            </tr>
        </table>

        <!-- Section 2: Patron Category Demographic Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? '2. የተጠቃሚዎች ምድብና ስነ-ሕዝብ ዝርዝር' : '2. Patron Demographic Categorization' }}</span>
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
                    <td class="text-right" style="font-weight: 700;">{{ number_format($demographics['student']['total']) }}</td>
                    <td class="text-right">{{ $demographics['student']['percent'] }}%</td>
                </tr>
                <tr>
                    <td><b>{{ $isAmharic ? 'መምህራንና ተመራማሪዎች' : 'Faculty & Research Scholars' }}</b></td>
                    <td class="text-right">{{ number_format($demographics['teacher']['male']) }}</td>
                    <td class="text-right">{{ number_format($demographics['teacher']['female']) }}</td>
                    <td class="text-right" style="font-weight: 700;">{{ number_format($demographics['teacher']['total']) }}</td>
                    <td class="text-right">{{ $demographics['teacher']['percent'] }}%</td>
                </tr>
                <tr>
                    <td><b>{{ $isAmharic ? 'የአስተዳደርና ቴክኒክ ሠራተኞች' : 'Administrative & Technical Staff' }}</b></td>
                    <td class="text-right">{{ number_format($demographics['staff']['male']) }}</td>
                    <td class="text-right">{{ number_format($demographics['staff']['female']) }}</td>
                    <td class="text-right" style="font-weight: 700;">{{ number_format($demographics['staff']['total']) }}</td>
                    <td class="text-right">{{ $demographics['staff']['percent'] }}%</td>
                </tr>
                <tr class="total-row">
                    <td><b>{{ $isAmharic ? 'አጠቃላይ ድምር' : 'Grand Total' }}</b></td>
                    <td class="text-right">{{ number_format($maleCount) }}</td>
                    <td class="text-right">{{ number_format($femaleCount) }}</td>
                    <td class="text-right" style="font-size: 12px;">{{ number_format($totalCount) }}</td>
                    <td class="text-right">100.0%</td>
                </tr>
            </tbody>
        </table>

        <!-- Section 3: Branch Facilities Comparison Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? '3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር' : '3. Library Branch Facility Distribution' }}</span>
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 32%;">{{ $isAmharic ? 'የቅርንጫፍ ስም' : 'Campus Branch' }}</th>
                    <th style="width: 10%;">{{ $isAmharic ? 'ኮድ' : 'Code' }}</th>
                    <th class="text-right" style="width: 14%;">{{ $isAmharic ? 'ተማሪዎች' : 'Students' }}</th>
                    <th class="text-right" style="width: 14%;">{{ $isAmharic ? 'መምህራን/ሰራተኛ' : 'Faculty/Staff' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'የድርሻ መጠን (%)' : 'Traffic Share (%)' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($branchComparison as $branch)
                    <tr>
                        <td><b>{{ $branch['name'] }}</b></td>
                        <td><span style="font-family: monospace; font-weight: bold;">{{ $branch['code'] }}</span></td>
                        <td class="text-right">{{ number_format($branch['students']) }}</td>
                        <td class="text-right">{{ number_format($branch['faculty_staff']) }}</td>
                        <td class="text-right" style="font-weight: 700;">{{ number_format($branch['total']) }}</td>
                        <td class="text-right">{{ $branch['percent'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 10px; color: #6b7280;">{{ $isAmharic ? 'መረጃ አልተገኘም' : 'No branch data recorded' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Section 4: Operational Shift Windows Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? '4. የፈረቃና የጊዜ ክፍለ-ጊዜ ስርጭት' : '4. Operational Shift Windows & Density' }}</span>
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 30%;">{{ $isAmharic ? 'የፈረቃ ክፍለ-ጊዜ' : 'Shift Window' }}</th>
                    <th style="width: 25%;">{{ $isAmharic ? 'የሰዓት ገደብ' : 'Time Interval' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'ተማሪዎች' : 'Students' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'ጠቅላላ ጎብኝ' : 'Total Patrons' }}</th>
                    <th class="text-right" style="width: 15%;">{{ $isAmharic ? 'የድርሻ መጠን (%)' : 'Share (%)' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($shiftWindows as $shift)
                    <tr>
                        <td><b>{{ $shift['title'] }}</b></td>
                        <td style="font-family: monospace;">{{ $shift['time_range'] }}</td>
                        <td class="text-right">{{ number_format($shift['students']) }}</td>
                        <td class="text-right" style="font-weight: 700;">{{ number_format($shift['count']) }}</td>
                        <td class="text-right">{{ $shift['percent'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Section 5: Duty Staff Attendant Audit Trail Table -->
        <div class="section-header">
            <span>{{ $isAmharic ? '5. የተረኛ ሠራተኞች የምዝገባ እንቅስቃሴ' : '5. Attendant Duty Desk Registry Log' }}</span>
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 35%;">{{ $isAmharic ? 'የሠራተኛው ስም' : 'Staff Name' }}</th>
                    <th style="width: 25%;">{{ $isAmharic ? 'የተመደበበት ቅርንጫፍ' : 'Branch Facility' }}</th>
                    <th class="text-right" style="width: 20%;">{{ $isAmharic ? 'የምዝገባዎች ብዛት' : 'Log Entries' }}</th>
                    <th class="text-right" style="width: 20%;">{{ $isAmharic ? 'የተስተናገደ ጎብኝ' : 'Visitors Handled' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse(array_slice($staffActivity, 0, 8) as $staff)
                    <tr>
                        <td><b>{{ $staff['name'] }}</b> ({{ $staff['username'] }})</td>
                        <td>{{ $staff['branch'] }}</td>
                        <td class="text-right">{{ number_format($staff['entries_count']) }}</td>
                        <td class="text-right" style="font-weight: 700;">{{ number_format($staff['visitors_handled']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center" style="padding: 10px; color: #6b7280;">{{ $isAmharic ? 'የሠራተኛ መረጃ አልተገኘም' : 'No staff log activity' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Section 6: Granular Recorded Entries Table (Sample Audit Trail) -->
        <div class="section-header">
            <span>{{ $isAmharic ? '6. የቀጥታ ምዝገባዎች ዝርዝር መዝገብ' : '6. Granular Entry Audit Trail' }}</span>
            <span style="font-weight: 500; font-size: 9px; color: #64748b;">{{ $isAmharic ? 'የመጨረሻዎቹ መዝገቦች' : 'Recent Registry' }}</span>
        </div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 16%;">{{ $isAmharic ? 'ቀንና ሰዓት' : 'Timestamp' }}</th>
                    <th style="width: 22%;">{{ $isAmharic ? 'ቅርንጫፍ' : 'Branch' }}</th>
                    <th style="width: 18%;">{{ $isAmharic ? 'የተጠቃሚ ዓይነት' : 'Patron' }}</th>
                    <th style="width: 14%;">{{ $isAmharic ? 'ጾታ' : 'Gender' }}</th>
                    <th class="text-right" style="width: 10%;">{{ $isAmharic ? 'ብዛት' : 'Count' }}</th>
                    <th style="width: 20%;">{{ $isAmharic ? 'መዝጋቢ ሠራተኛ' : 'Staff' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances->take(50) as $log)
                    @php
                        $dt = \App\Services\EthiopianDateService::formatDT($log->entry_at, $isAmharic);
                        $branchTitle = $isAmharic ? ($log->library->name_am ?? $log->library->name ?? '—') : ($log->library->name ?? '—');
                        $patronTitle = match ($log->user_type) {
                            'student' => $isAmharic ? 'ተማሪ' : 'Student',
                            'teacher' => $isAmharic ? 'መምህር' : 'Faculty',
                            'staff' => $isAmharic ? 'ሰራተኛ' : 'Staff',
                            default => ucfirst($log->user_type),
                        };
                        $genderTitle = match ($log->gender) {
                            'male' => $isAmharic ? 'ወንድ' : 'Male',
                            'female' => $isAmharic ? 'ሴት' : 'Female',
                            default => ucfirst($log->gender),
                        };
                    @endphp
                    <tr>
                        <td style="font-family: monospace;">{{ $dt['time'] }} · {{ $dt['date'] }}</td>
                        <td>{{ $branchTitle }}</td>
                        <td><b>{{ $patronTitle }}</b></td>
                        <td>{{ $genderTitle }}</td>
                        <td class="text-right" style="font-weight: 800;">+{{ $log->visitor_count }}</td>
                        <td>{{ $log->staff->name ?? 'Desk Attendant' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 16px; color: #6b7280;">
                            {{ $isAmharic ? 'በዚህ የጊዜ ገደብ ውስጥ የተመዘገበ የቀጥታ መረጃ የለም።' : 'No attendance entries found for the selected range.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Formal Administrative Clearance & Seal Blocks -->
        <div class="sig-container">
            <div class="sig-block">
                <div class="sig-role">{{ $isAmharic ? 'የመዝጋቢው ቤተ-መጻሕፍት ሠራተኛ' : 'Duty Desk Librarian' }}</div>
                <div class="sig-name">{{ $generatedBy }}</div>
                <div class="sig-date">{{ $generatedAt['date'] }}</div>
            </div>
            <div class="sig-block">
                <div class="sig-role">{{ $isAmharic ? 'የቅርንጫፍ አስተባባሪ' : 'Branch Supervisor' }}</div>
                <div class="sig-name">{{ $isAmharic ? 'የተረጋገጠ ፊርማ' : 'Certified & Inspected' }}</div>
                <div class="sig-date">{{ $generatedAt['date'] }}</div>
            </div>
            <div class="sig-block">
                <div class="sig-role">{{ $isAmharic ? 'የዋና ቤተ-መጻሕፍት ኃላፊ ማህተም' : 'Chief Librarian Seal' }}</div>
                <div class="sig-name">{{ $isAmharic ? 'ይፋዊ የአካዳሚክ ማህተም' : 'Authorized Academic Registry' }}</div>
                <div class="sig-date">{{ $refNumber }}</div>
            </div>
        </div>
    </div>

    <script>
        // Auto-trigger print prompt if format=pdf or auto_print query is set
        if (window.location.search.includes('auto_print=1') || window.location.search.includes('format=pdf')) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 400);
            };
        }
    </script>
</body>
</html>
