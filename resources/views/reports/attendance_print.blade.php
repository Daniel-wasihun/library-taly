<!DOCTYPE html>
<html lang="{{ $isAmharic ? 'am' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $isAmharic ? 'የቤተ-መጻሕፍት አጠቃቀም ይፋዊ ሪፖርት' : 'Official Library Attendance Report' }} — {{ $libraryName }}</title>
    <style>
        :root {
            --brand-blue: #0b529c;
            --brand-yellow: #fba81c;
            --main-text: #1e293b;
            --border-color: #cbd5e1;
            --bg-light: #f8fafc;
        }

        @media print {
            @page {
                margin: 1.2cm;
                size: A4 portrait;
            }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none !important;
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Ethiopic", Arial, sans-serif;
            color: var(--main-text);
            margin: 0;
            padding: 24px;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.5;
        }

        .header-container {
            border-bottom: 3px double var(--brand-blue);
            padding-bottom: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .emblem {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--brand-blue);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }

        .org-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--brand-blue);
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .sub-title {
            margin: 2px 0 0;
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
        }

        .report-badge {
            text-align: right;
        }

        .badge-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(11, 82, 156, 0.08);
            color: var(--brand-blue);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid rgba(11, 82, 156, 0.2);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .meta-item b {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }

        .meta-item span {
            font-size: 12px;
            font-weight: 700;
            color: var(--main-text);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .kpi-card {
            border-radius: 10px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
        }

        .kpi-card.blue {
            background: rgba(11, 82, 156, 0.05);
            border-color: rgba(11, 82, 156, 0.2);
        }

        .kpi-card.yellow {
            background: rgba(251, 168, 28, 0.08);
            border-color: rgba(251, 168, 28, 0.3);
        }

        .kpi-card.neutral {
            background: var(--bg-light);
        }

        .kpi-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
        }

        .kpi-val {
            font-size: 24px;
            font-weight: 800;
            margin-top: 4px;
            line-height: 1;
        }

        .kpi-card.blue .kpi-val { color: var(--brand-blue); }
        .kpi-card.yellow .kpi-val { color: #d97706; }
        .kpi-card.neutral .kpi-val { color: var(--main-text); }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        th {
            background: #f1f5f9;
            color: #334155;
            text-align: left;
            padding: 8px 12px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }

        tr:nth-child(even) td {
            background: #fafafa;
        }

        .tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .tag.male { background: rgba(11, 82, 156, 0.1); color: var(--brand-blue); }
        .tag.female { background: rgba(251, 168, 28, 0.15); color: #b45309; }

        .tag.student { background: #ecfdf5; color: #047857; }
        .tag.teacher { background: #eff6ff; color: #1d4ed8; }
        .tag.staff { background: #faf5ff; color: #7e22ce; }

        .signature-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            page-break-inside: avoid;
        }

        .sig-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 8px;
            text-align: center;
        }

        .sig-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--main-text);
        }

        .sig-sub {
            font-size: 9px;
            color: #64748b;
        }

        .print-toolbar {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
            border-radius: 30px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
        }

        .btn-print {
            background: var(--brand-blue);
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="print-toolbar no-print">
        <span style="font-weight: 600; font-size: 11px;">{{ $isAmharic ? 'ይፋዊ ሪፖርት' : 'Official Report Ready' }}</span>
        <button class="btn-print" onclick="window.print()">
            {{ $isAmharic ? '🖨️ አትም (Print)' : '🖨️ Print / Save as PDF' }}
        </button>
    </div>

    <!-- Header -->
    <div class="header-container">
        <div class="logo-section">
            <div class="emblem">📚</div>
            <div>
                <h1 class="org-title">{{ $isAmharic ? 'የዩኒቨርሲቲ ቤተ-መጻሕፍት ፖርታል' : 'UniLibrary Academic Portal' }}</h1>
                <p class="sub-title">{{ $isAmharic ? 'የቀጥታ አጠቃቀም ቆጣሪና የስነ-ሕዝብ ትንተና ይፋዊ ሪፖርት' : 'Physical Library Attendance & Demographic Breakdown Report' }}</p>
            </div>
        </div>
        <div class="report-badge">
            <span class="badge-pill">{{ $isAmharic ? 'ይፋዊ ሰነድ' : 'Official Audit Record' }}</span>
            <div style="font-size: 9px; color: #64748b; margin-top: 4px; font-family: monospace;">
                REF-{{ strtoupper(substr(md5($generatedAt['full'] . $totalCount), 0, 10)) }}
            </div>
        </div>
    </div>

    <!-- Meta Details Grid -->
    <div class="meta-grid">
        <div class="meta-item">
            <b>{{ $isAmharic ? 'የቤተ-መጻሕፍት ቅርንጫፍ' : 'Library Branch' }}</b>
            <span>{{ $libraryName }}</span>
        </div>
        <div class="meta-item">
            <b>{{ $isAmharic ? 'የሪፖርቱ ጊዜ' : 'Reporting Window' }}</b>
            <span>
                @if($isSingleDay)
                    {{ $dateFromStr['full'] }}
                @else
                    {{ $dateFromStr['date'] }} – {{ $dateToStr['date'] }}
                @endif
            </span>
        </div>
        <div class="meta-item">
            <b>{{ $isAmharic ? 'የተዘጋጀበት ሰዓት (12hr)' : 'Generated At (12hr)' }}</b>
            <span>{{ $generatedAt['date'] }} · {{ $generatedAt['time'] }}</span>
        </div>
        <div class="meta-item">
            <b>{{ $isAmharic ? 'ያዘጋጀው ሠራተኛ' : 'Prepared By Staff' }}</b>
            <span>{{ $generatedBy }}</span>
        </div>
    </div>

    <!-- Top KPI Summary Cards -->
    <div class="kpi-grid">
        <div class="kpi-card neutral">
            <div class="kpi-label">{{ $isAmharic ? 'ጠቅላላ ጎብኝዎች' : 'Total Visitors Count' }}</div>
            <div class="kpi-val">{{ number_format($totalCount) }}</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">{{ $isAmharic ? 'በተመረጠው የጊዜ ገደብ' : 'Logged during window' }}</div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-label">{{ $isAmharic ? 'ወንድ ጎብኝዎች' : 'Male Visitors' }}</div>
            <div class="kpi-val">{{ number_format($maleCount) }}</div>
            <div style="font-size: 10px; color: var(--brand-blue); margin-top: 4px;">{{ $malePercent }}% {{ $isAmharic ? 'የጠቅላላው ድርሻ' : 'of total traffic' }}</div>
        </div>
        <div class="kpi-card yellow">
            <div class="kpi-label">{{ $isAmharic ? 'ሴት ጎብኝዎች' : 'Female Visitors' }}</div>
            <div class="kpi-val">{{ number_format($femaleCount) }}</div>
            <div style="font-size: 10px; color: #b45309; margin-top: 4px;">{{ $femalePercent }}% {{ $isAmharic ? 'የጠቅላላው ድርሻ' : 'of total traffic' }}</div>
        </div>
    </div>

    <!-- User Type Demographic Breakdown Table -->
    <h3 style="font-size: 12px; font-weight: 800; color: var(--brand-blue); text-transform: uppercase; margin-bottom: 8px;">
        {{ $isAmharic ? '1. የተጠቃሚዎች ስርጭት ትንተና' : '1. Demographic Patron Distribution' }}
    </h3>
    <table>
        <thead>
            <tr>
                <th>{{ $isAmharic ? 'የተጠቃሚ ዓይነት' : 'Patron Type' }}</th>
                <th style="text-align: right;">{{ $isAmharic ? 'ወንድ' : 'Male' }}</th>
                <th style="text-align: right;">{{ $isAmharic ? 'ሴት' : 'Female' }}</th>
                <th style="text-align: right;">{{ $isAmharic ? 'ጠቅላላ ድምር' : 'Total Subtotal' }}</th>
                <th style="text-align: right;">{{ $isAmharic ? 'መቶኛ' : 'Share (%)' }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="tag student">{{ $isAmharic ? 'ተማሪ' : 'Students' }}</span></td>
                <td style="text-align: right; font-weight: 700; color: var(--brand-blue);">{{ number_format($demographics['student']['male']) }}</td>
                <td style="text-align: right; font-weight: 700; color: #b45309;">{{ number_format($demographics['student']['female']) }}</td>
                <td style="text-align: right; font-weight: 800;">{{ number_format($demographics['student']['total']) }}</td>
                <td style="text-align: right; color: #64748b;">{{ $totalCount > 0 ? round(($demographics['student']['total'] / $totalCount) * 100, 1) : 0 }}%</td>
            </tr>
            <tr>
                <td><span class="tag teacher">{{ $isAmharic ? 'መምህር' : 'Faculty / Teachers' }}</span></td>
                <td style="text-align: right; font-weight: 700; color: var(--brand-blue);">{{ number_format($demographics['teacher']['male']) }}</td>
                <td style="text-align: right; font-weight: 700; color: #b45309;">{{ number_format($demographics['teacher']['female']) }}</td>
                <td style="text-align: right; font-weight: 800;">{{ number_format($demographics['teacher']['total']) }}</td>
                <td style="text-align: right; color: #64748b;">{{ $totalCount > 0 ? round(($demographics['teacher']['total'] / $totalCount) * 100, 1) : 0 }}%</td>
            </tr>
            <tr>
                <td><span class="tag staff">{{ $isAmharic ? 'ሰራተኛ' : 'University Staff' }}</span></td>
                <td style="text-align: right; font-weight: 700; color: var(--brand-blue);">{{ number_format($demographics['staff']['male']) }}</td>
                <td style="text-align: right; font-weight: 700; color: #b45309;">{{ number_format($demographics['staff']['female']) }}</td>
                <td style="text-align: right; font-weight: 800;">{{ number_format($demographics['staff']['total']) }}</td>
                <td style="text-align: right; color: #64748b;">{{ $totalCount > 0 ? round(($demographics['staff']['total'] / $totalCount) * 100, 1) : 0 }}%</td>
            </tr>
            <tr style="background: #f1f5f9; font-weight: 800;">
                <td style="text-transform: uppercase;">{{ $isAmharic ? 'አጠቃላይ ድምር' : 'Grand Total' }}</td>
                <td style="text-align: right; color: var(--brand-blue);">{{ number_format($maleCount) }}</td>
                <td style="text-align: right; color: #b45309;">{{ number_format($femaleCount) }}</td>
                <td style="text-align: right; color: var(--main-text); font-size: 13px;">{{ number_format($totalCount) }}</td>
                <td style="text-align: right;">100%</td>
            </tr>
        </tbody>
    </table>

    <!-- Detailed Recorded Logs Table -->
    <h3 style="font-size: 12px; font-weight: 800; color: var(--brand-blue); text-transform: uppercase; margin-bottom: 8px;">
        {{ $isAmharic ? '2. የተመዘገቡ የቀጥታ ምዝገባዎች ዝርዝር (12hr ሰዓት)' : '2. Detailed Entries Log (12-hour format)' }}
    </h3>
    <table>
        <thead>
            <tr>
                <th>{{ $isAmharic ? 'የተመዘገበበት ሰዓት' : 'Time (12-hr)' }}</th>
                <th>{{ $isAmharic ? 'ቀን' : 'Date' }}</th>
                <th>{{ $isAmharic ? 'ቅርንጫፍ' : 'Branch' }}</th>
                <th>{{ $isAmharic ? 'የተጠቃሚ ዓይነት' : 'Patron' }}</th>
                <th>{{ $isAmharic ? 'ጾታ' : 'Gender' }}</th>
                <th style="text-align: center;">{{ $isAmharic ? 'ብዛት' : 'Count' }}</th>
                <th>{{ $isAmharic ? 'መዝጋቢ ሠራተኛ' : 'Logged By' }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances->take(100) as $log)
                @php
                    $dt = \App\Services\EthiopianDateService::formatDT($log->entry_at, $isAmharic);
                @endphp
                <tr>
                    <td style="font-family: monospace; font-weight: 700;">{{ $dt['time'] }}</td>
                    <td>{{ $dt['date'] }}</td>
                    <td>{{ $isAmharic ? ($log->library->name_am ?? $log->library->name ?? '—') : ($log->library->name ?? '—') }}</td>
                    <td><span class="tag {{ $log->user_type }}">{{ $isAmharic ? ($log->user_type === 'student' ? 'ተማሪ' : ($log->user_type === 'teacher' ? 'መምህር' : 'ሰራተኛ')) : ucfirst($log->user_type) }}</span></td>
                    <td><span class="tag {{ $log->gender }}">{{ $isAmharic ? ($log->gender === 'male' ? 'ወንድ' : 'ሴት') : ucfirst($log->gender) }}</span></td>
                    <td style="text-align: center; font-weight: 800;">+{{ $log->visitor_count }}</td>
                    <td>{{ $log->staff->name ?? 'Desk Staff' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 20px;">
                        {{ $isAmharic ? 'በዚህ የጊዜ ገደብ ውስጥ የተመዘገበ መረጃ የለም።' : 'No attendance entries found for the selected range.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Signature / Official Seal Block -->
    <div class="signature-section">
        <div class="sig-box">
            <div class="sig-title">{{ $isAmharic ? 'የመዝጋቢው ቤተ-መጻሕፍት ሠራተኛ ፊርማ' : 'Duty Desk Librarian Signature' }}</div>
            <div class="sig-sub">{{ $generatedBy }} · {{ $generatedAt['date'] }}</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">{{ $isAmharic ? 'የዋና ቤተ-መጻሕፍት ኃላፊ ማረጋገጫና ማህተም' : 'Head Librarian Seal & Approval' }}</div>
            <div class="sig-sub">{{ $isAmharic ? 'ይፋዊ ማህተም' : 'Authorized Academic Registry' }}</div>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 400);
        };
    </script>
</body>
</html>
