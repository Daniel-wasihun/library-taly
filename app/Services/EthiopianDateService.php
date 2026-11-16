<?php

namespace App\Services;

use Carbon\Carbon;

class EthiopianDateService
{
    protected static array $amMonths = [
        1 => 'መስከረም',
        2 => 'ጥቅምት',
        3 => 'ኅዳር',
        4 => 'ታኅሣሥ',
        5 => 'ጥር',
        6 => 'የካቲት',
        7 => 'መጋቢት',
        8 => 'ሚያዝያ',
        9 => 'ግንቦት',
        10 => 'ሰኔ',
        11 => 'ሐምሌ',
        12 => 'ነሐሴ',
        13 => 'ጳጉሜን',
    ];

    protected static array $amDays = [
        0 => 'እሁድ',
        1 => 'ሰኞ',
        2 => 'ማክሰኞ',
        3 => 'ረቡዕ',
        4 => 'ሐሙስ',
        5 => 'አርብ',
        6 => 'ቅዳሜ',
    ];

    protected static function startDayOfEthiopian(int $year): int
    {
        $newYearDay = (int) (floor($year / 100) - floor($year / 400) - 4);
        return (($year - 1) % 4 === 3) ? $newYearDay + 1 : $newYearDay;
    }

    public static function toEthiopian(int $year, int $month, int $date): array
    {
        $gregorianMonths = [0.0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $ethiopianMonths = [0.0, 30, 30, 30, 30, 30, 30, 30, 30, 30, 5, 30, 30, 30, 30];

        if (($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0) {
            $gregorianMonths[2] = 29;
        }

        $ethiopianYear = $year - 8;
        if ($ethiopianYear % 4 === 3) {
            $ethiopianMonths[10] = 6;
        }

        $newYearDay = self::startDayOfEthiopian($year - 8);

        $until = 0;
        for ($i = 1; $i < $month; $i++) {
            $until += $gregorianMonths[$i];
        }
        $until += $date;

        $tahissas = ($ethiopianYear % 4) === 0 ? 26 : 25;
        if ($year < 1582) {
            $ethiopianMonths[1] = 0;
            $ethiopianMonths[2] = $tahissas;
        } elseif ($until <= 277 && $year === 1582) {
            $ethiopianMonths[1] = 0;
            $ethiopianMonths[2] = $tahissas;
        } else {
            $tahissas = $newYearDay - 3;
            $ethiopianMonths[1] = $tahissas;
        }

        $m = 1;
        $ethiopianDate = 1;
        for ($m = 1; $m < count($ethiopianMonths); $m++) {
            if ($until <= $ethiopianMonths[$m]) {
                $ethiopianDate = ($m === 1 || $ethiopianMonths[$m] === 0) ? $until + (30 - $tahissas) : $until;
                break;
            } else {
                $until -= $ethiopianMonths[$m];
            }
        }

        if ($m > 10) {
            $ethiopianYear += 1;
        }

        $order = [0, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 1, 2, 3, 4];
        $ethiopianMonth = $order[$m] ?? 1;

        return [$ethiopianYear, $ethiopianMonth, $ethiopianDate];
    }

    public static function formatDT(Carbon|string|null $dateInput, bool $isAmharic = false): array
    {
        if (!$dateInput) {
            return ['time' => '—', 'date' => '—', 'full' => '—'];
        }

        $date = $dateInput instanceof Carbon ? $dateInput : Carbon::parse($dateInput);

        $h = (int) $date->format('G');
        $m = $date->format('i');

        if (!$isAmharic) {
            $time12 = $date->format('h:i A'); // 12-hour format with AM/PM
            $dateStr = $date->format('M j, Y');
            $fullStr = $date->format('l, M j, Y');

            return [
                'time' => $time12,
                'date' => $dateStr,
                'full' => $fullStr,
            ];
        }

        // Ethiopian Calendar
        [$eyear, $emonth, $edate] = self::toEthiopian(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j')
        );

        // Ethiopian 12-hour cycle (starts at 6:00 AM)
        $etH = $h >= 6 ? $h - 6 : $h + 6;
        if ($etH > 12) $etH -= 12;
        if ($etH === 0) $etH = 12;

        if ($h >= 0 && $h < 6) $period = 'ከሌሊቱ';
        elseif ($h >= 6 && $h < 12) $period = 'ጠዋት';
        elseif ($h >= 12 && $h < 18) $period = 'ከቀኑ';
        else $period = 'ከምሽቱ';

        $monthName = self::$amMonths[$emonth] ?? '';
        $dayOfWeek = (int) $date->format('w');
        $dayName = self::$amDays[$dayOfWeek] ?? '';

        return [
            'time' => "{$etH}:{$m} {$period}",
            'date' => "{$monthName} {$edate}, {$eyear}",
            'full' => "{$dayName}፣ {$monthName} {$edate} ቀን {$eyear} ዓ.ም",
        ];
    }
}
