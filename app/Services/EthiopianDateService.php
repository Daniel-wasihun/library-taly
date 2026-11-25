<?php

namespace App\Services;

use Carbon\Carbon;

class EthiopianDateService
{
    protected const ETHIOPIC_EPOCH = 1724221;

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
        0 => 'እሑድ',
        1 => 'ሰኞ',
        2 => 'ማክሰኞ',
        3 => 'ረቡዕ',
        4 => 'ሐሙስ',
        5 => 'ዓርብ',
        6 => 'ቅዳሜ',
    ];

    public static function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = (int) floor((14 - $month) / 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;
        return $day + (int) floor((153 * $m + 2) / 5) + 365 * $y + (int) floor($y / 4) - (int) floor($y / 100) + (int) floor($y / 400) - 32045;
    }

    public static function jdnToGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = (int) floor((4 * $a + 3) / 146097);
        $c = $a - (int) floor((146097 * $b) / 4);
        $d = (int) floor((4 * $c + 3) / 1461);
        $e = $c - (int) floor((1461 * $d) / 4);
        $m = (int) floor((5 * $e + 2) / 153);
        $day = $e - (int) floor((153 * $m + 2) / 5) + 1;
        $month = $m + 3 - 12 * (int) floor($m / 10);
        $year = 100 * $b + $d - 4800 + (int) floor($m / 10);
        return [$year, $month, $day];
    }

    public static function ethiopianToJdn(int $year, int $month, int $day): int
    {
        return self::ETHIOPIC_EPOCH + ($year - 1) * 365 + (int) floor($year / 4) + ($month - 1) * 30 + $day - 1;
    }

    public static function jdnToEthiopian(int $jdn): array
    {
        $daysSinceEpoch = $jdn - self::ETHIOPIC_EPOCH;
        $cycle = (int) floor($daysSinceEpoch / 1461);
        $rem = $daysSinceEpoch % 1461;

        if ($rem < 365) {
            $yInCycle = 0;
            $dayInYear = $rem;
        } elseif ($rem < 730) {
            $yInCycle = 1;
            $dayInYear = $rem - 365;
        } elseif ($rem < 1096) {
            $yInCycle = 2;
            $dayInYear = $rem - 730;
        } else {
            $yInCycle = 3;
            $dayInYear = $rem - 1096;
        }

        $year = $cycle * 4 + $yInCycle + 1;
        $month = (int) floor($dayInYear / 30) + 1;
        $day = ($dayInYear % 30) + 1;

        return [$year, $month, $day];
    }

    /**
     * Convert Gregorian Calendar [Y, M, D] to Ethiopian Calendar [Y, M, D].
     */
    public static function toEthiopian(int $year, int $month, int $date): array
    {
        $jdn = self::gregorianToJdn($year, $month, $date);
        return self::jdnToEthiopian($jdn);
    }

    /**
     * Convert Ethiopian Calendar [Y, M, D] to Gregorian Calendar [Y, M, D].
     */
    public static function toGregorian(int $year, int $month, int $date): array
    {
        $jdn = self::ethiopianToJdn($year, $month, $date);
        return self::jdnToGregorian($jdn);
    }

    public static function formatDT(Carbon|string|null $dateInput, bool $isAmharic = false): array
    {
        if (!$dateInput) {
            return ['time' => '—', 'date' => '—', 'full' => '—'];
        }

        $date = ($dateInput instanceof Carbon ? $dateInput->copy() : Carbon::parse($dateInput))->setTimezone('Africa/Addis_Ababa');

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
