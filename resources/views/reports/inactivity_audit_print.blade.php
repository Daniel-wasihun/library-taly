<!DOCTYPE html>
<html lang="{{ $isAmharic ? 'am' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isAmharic ? 'የፈረቃ ሠራተኞች የሥራ ማቆም ኦዲት ሪፖርት' : 'Attendant Shift Inactivity Audit Report' }} - {{ $report['date_gregorian'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 12mm 15mm 12mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Ethiopic", Helvetica, Arial, sans-serif;
            color: #111827;
            background: #ffffff;
            font-size: 11pt;
            line-height: 1.4;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
            text-align: center;
        }
        .inst-title {
            font-size: 14pt;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #0f172a;
            text-transform: uppercase;
        }
        .inst-subtitle {
            font-size: 11pt;
            font-weight: 600;
            color: #334155;
            margin-top: 2px;
        }
        .report-title {
            font-size: 12pt;
            font-weight: 700;
            color: #b91c1c;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .meta-bar {
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 16px;
            font-size: 9.5pt;
        }
        .meta-col {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .meta-item strong {
            color: #0f172a;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .kpi-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px;
            text-align: center;
            background: #ffffff;
        }
        .kpi-card.alert {
            border-color: #fca5a5;
            background: #fef2f2;
        }
        .kpi-val {
            font-size: 14pt;
            font-weight: 800;
            color: #0f172a;
        }
        .kpi-card.alert .kpi-val {
            color: #b91c1c;
        }
        .kpi-lbl {
            font-size: 8pt;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
            margin-top: 2px;
        }
        .policy-notice {
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
            padding: 6px 10px;
            margin-bottom: 14px;
            font-size: 8.5pt;
            color: #92400e;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 9pt;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 700;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-critical {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .attendant-list {
            margin: 0;
            padding-left: 14px;
            font-size: 8.5pt;
        }
        .attendant-list li {
            margin-bottom: 2px;
        }
        .signature-section {
            margin-top: 24px;
            page-break-inside: avoid;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
        }
        .sig-box {
            text-align: center;
        }
        .sig-line {
            height: 40px;
            border-bottom: 1px solid #475569;
            margin-bottom: 6px;
        }
        .sig-label {
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #334155;
        }
        .sig-title {
            font-size: 7.5pt;
            color: #64748b;
        }
        .no-print {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 16px;
        }
        .print-btn {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 9pt;
            cursor: pointer;
        }
        .print-btn:hover {
            background: #1e293b;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="print-btn">
            🖨️ {{ $isAmharic ? 'ሪፖርቱን አትም (Print Report)' : 'Print Official Audit Report' }}
        </button>
    </div>

    <!-- Official Document Header -->
    <div class="header">
        <div class="inst-title">
            {{ $isAmharic ? 'ወልዲያ ዩኒቨርሲቲ • የቤተ-መጻሕፍትና መረጃ አገልግሎት ዳይሬክቶሬት' : 'Woldia University • Library & Information Services Directorate' }}
        </div>
        <div class="inst-subtitle">
            {{ $isAmharic ? 'የፈረቃ ሠራተኞች የሥራ አፈጻጸም እና የሥራ ማቆም ክትትል ኦዲት' : 'Staff Duty Performance & Shift Inactivity Compliance Audit' }}
        </div>
        <div class="report-title">
            ⚠️ {{ $isAmharic ? 'የፈረቃ የሥራ ማቆም ክፍተት ይፋዊ ኦዲት ሪፖርት (> 30 ደቂቃ)' : 'Official Attendant Shift Inactivity Audit Report (> 30 Mins Inactive)' }}
        </div>
    </div>

    <!-- Metadata Strip -->
    <div class="meta-bar">
        <div class="meta-col">
            <span class="meta-item">
                <strong>{{ $isAmharic ? 'የኦዲት ቀን (የፈረንጅ)' : 'Audit Date (Gregorian)' }}:</strong> {{ $report['date_gregorian_formatted'] }}
            </span>
            <span class="meta-item">
                <strong>{{ $isAmharic ? 'የኦዲት ቀን (የኢትዮጵያ)' : 'Audit Date (Ethiopian)' }}:</strong> {{ $report['date_ethiopian_formatted'] }} ({{ $report['date_ethiopian'] }})
            </span>
        </div>
        <div class="meta-col" style="text-align: right;">
            <span class="meta-item">
                <strong>{{ $isAmharic ? 'የተዘጋጀበት ሰዓት' : 'Generated At' }}:</strong> {{ $generatedAt->format('M j, Y h:i A (T)') }}
            </span>
            <span class="meta-item">
                <strong>{{ $isAmharic ? 'ያዘጋጀው ባለሥልጣን' : 'Generated By' }}:</strong> {{ $generatedBy }}
            </span>
        </div>
    </div>

    <!-- Compliance KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-val">{{ $report['summary']['total_monitored_shifts'] }}</div>
            <div class="kpi-lbl">{{ $isAmharic ? 'የተመረመሩ የቅርንጫፍ ፈረቃዎች' : 'Monitored Branch Shifts' }}</div>
        </div>
        <div class="kpi-card alert">
            <div class="kpi-val">{{ $report['summary']['total_incidents'] }}</div>
            <div class="kpi-lbl">{{ $isAmharic ? 'የሥራ ማቆም ክስተቶች (>30 ደቂቃ)' : 'Inactivity Incidents (>30m)' }}</div>
        </div>
        <div class="kpi-card alert">
            <div class="kpi-val">{{ $report['summary']['total_inactive_hours'] }}h</div>
            <div class="kpi-lbl">{{ $isAmharic ? 'አጠቃላይ የባከነ ሰዓት' : 'Total Inactive Hours' }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-val">{{ $report['summary']['compliance_rate'] }}%</div>
            <div class="kpi-lbl">{{ $isAmharic ? 'የፈረቃ ተገዢነት መጠን' : 'Compliance Rate' }}</div>
        </div>
    </div>

    <!-- Policy Statement -->
    <div class="policy-notice">
        <strong>{{ $isAmharic ? 'የተቋሙ የፈረቃ ደንብ' : 'Institutional Policy Notice' }}:</strong>
        {{ $isAmharic 
            ? 'በቤተ-መጻሕፍት የፈረቃ ደንብ መሠረት፣ በፈረቃ የተመደቡ ሠራተኞች በተመደቡበት የሥራ ሰዓት የተጠቃሚ ቆጣሪውን ሳያቋርጡ መመዝገብ አለባቸው። ከ 30 ደቂቃ በላይ ምንም ዓይነት ቆጠራ ያልተከናወነበት ክፍተት እንደ ግዴታ አለመወጣት ተመዝግቧል።'
            : 'Per university library operational regulations, assigned attendants must actively count patron visits during their active shift hours. Any interval exceeding 30 consecutive minutes without usage counter recording constitutes an operational compliance violation.'
        }}
    </div>

    <!-- Incidents Audit Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">{{ $isAmharic ? 'የክስተት መለያ' : 'Incident ID' }}</th>
                <th style="width: 14%;">{{ $isAmharic ? 'ቅርንጫፍ' : 'Branch' }}</th>
                <th style="width: 14%;">{{ $isAmharic ? 'ፈረቃና ቡድን' : 'Shift & Group' }}</th>
                <th style="width: 26%;">{{ $isAmharic ? 'በፈረቃው የተመደቡ ሠራተኞች' : 'On-Duty Attendants' }}</th>
                <th style="width: 16%;">{{ $isAmharic ? 'የማቆም ክፍተት (ከሰዓት - እስከ)' : 'Inactive Interval' }}</th>
                <th style="width: 10%;">{{ $isAmharic ? 'ቆይታ' : 'Duration' }}</th>
                <th style="width: 12%;">{{ $isAmharic ? 'ሁኔታ' : 'Status' }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['incidents'] as $inc)
                <tr>
                    <td style="font-family: monospace; font-size: 8pt; font-weight: bold;">
                        {{ $inc['incident_id'] }}
                    </td>
                    <td>
                        <strong>{{ $isAmharic ? $inc['branch_name_am'] : $inc['branch_name'] }}</strong>
                        <div style="font-size: 7.5pt; color: #64748b;">[{{ $inc['branch_code'] }}]</div>
                    </td>
                    <td>
                        <div><strong>{{ $isAmharic ? $inc['shift_name_am'] : $inc['shift_name'] }}</strong></div>
                        <div style="font-size: 7.5pt; color: #475569;">{{ $inc['shift_group_name'] }}</div>
                        <div style="font-size: 7.5pt; color: #64748b;">{{ $inc['shift_window'] }}</div>
                    </td>
                    <td>
                        <ul class="attendant-list">
                            @foreach($inc['attendants'] as $att)
                                <li>
                                    <strong>{{ $att['name'] }}</strong>
                                    <span style="color: #64748b; font-size: 7.5pt;">(@{{ $att['username'] }})</span>
                                </li>
                            @endforeach
                        </ul>
                    </td>
                    <td>
                        <div><strong>{{ $inc['interval_formatted'] }}</strong></div>
                        <div style="font-size: 7.5pt; color: #059669;">{{ $inc['interval_ethiopian'] }}</div>
                    </td>
                    <td style="font-weight: bold; color: #b91c1c;">
                        {{ $inc['duration_human'] }}
                        <div style="font-size: 7.5pt; color: #64748b;">({{ $inc['duration_minutes'] }} min)</div>
                    </td>
                    <td>
                        @if($inc['is_ongoing'])
                            <span class="badge badge-critical">
                                🔴 {{ $isAmharic ? 'አሁን አልሰሩም' : 'Active Non-Working' }}
                            </span>
                        @else
                            <span class="badge badge-warning">
                                ⚠️ {{ $isAmharic ? 'የቀድሞ ክፍተት' : 'Past Gap' }}
                            </span>
                        @endif
                        <div style="font-size: 7.5pt; color: #64748b; margin-top: 2px;">
                            {{ $inc['total_shift_entries'] }} {{ $isAmharic ? 'ተመዝግቧል' : 'logged' }}
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px; color: #059669; font-weight: bold;">
                        ✓ {{ $isAmharic ? 'በዚህ ቀን ምንም ዓይነት የሥራ ማቆም ክፍተት አልተገኘም። ሁሉም ሠራተኞች በተገቢው ሁኔታ ተመዝግበዋል።' : 'No shift inactivity incidents detected for this audit period. 100% duty compliance.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Institutional Signatures & Verification -->
    <div class="signature-section">
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">{{ $isAmharic ? 'ያዘጋጀው ኦዲተር ፊርማ' : 'Reporting Officer / Auditor' }}</div>
            <div class="sig-title">{{ $generatedBy }}</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">{{ $isAmharic ? 'የቅርንጫፍ አስተባባሪ' : 'Branch Supervisor' }}</div>
            <div class="sig-title">{{ $isAmharic ? 'ስም እና ፊርማ' : 'Name & Signature' }}</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">{{ $isAmharic ? 'የዋና ቤተ-መጻሕፍት ዳይሬክተር ማረጋገጫ' : 'Head Librarian Approval & Stamp' }}</div>
            <div class="sig-title">{{ $isAmharic ? 'ማኅተም እና ቀን' : 'Official Seal & Date' }}</div>
        </div>
    </div>

</body>
</html>
