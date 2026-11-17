<?php

namespace App\Services;

use Carbon\Carbon;

class EthiopianCalendarService
{
    public const MONTHS = [
        1 => ['am' => 'መስከረም', 'en' => 'Meskerem'],
        2 => ['am' => 'ጥቅምት', 'en' => 'Tikimt'],
        3 => ['am' => 'ኅዳር', 'en' => 'Hidar'],
        4 => ['am' => 'ታኅሣሥ', 'en' => 'Tahsas'],
        5 => ['am' => 'ጥር', 'en' => 'Tir'],
        6 => ['am' => 'የካቲት', 'en' => 'Yakatit'],
        7 => ['am' => 'መጋቢት', 'en' => 'Megabit'],
        8 => ['am' => 'ሚያዝያ', 'en' => 'Miazia'],
        9 => ['am' => 'ግንቦት', 'en' => 'Ginbot'],
        10 => ['am' => 'ሰኔ', 'en' => 'Sene'],
        11 => ['am' => 'ሐምሌ', 'en' => 'Hamle'],
        12 => ['am' => 'ነሐሴ', 'en' => 'Nehase'],
        13 => ['am' => 'ጳጉሜን', 'en' => 'Pagume'],
    ];

    public const DAYS_OF_WEEK = [
        0 => ['am' => 'እሁድ', 'en' => 'Sunday'],
        1 => ['am' => 'ሰኞ', 'en' => 'Monday'],
        2 => ['am' => 'ማክሰኞ', 'en' => 'Tuesday'],
        3 => ['am' => 'ረቡዕ', 'en' => 'Wednesday'],
        4 => ['am' => 'ሐሙስ', 'en' => 'Thursday'],
        5 => ['am' => 'አርብ', 'en' => 'Friday'],
        6 => ['am' => 'ቅዳሜ', 'en' => 'Saturday'],
    ];

    /**
     * Convert Gregorian date (year, month, day) to Ethiopian (eyear, emonth, eday).
     *
     * @return array{0: int, 1: int, 2: int} [eyear, emonth, eday]
     */
    public static function gregorianToEthiopian(int $year, int $month, int $day): array
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;
        $jdn = $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;

        // Ethiopian epoch JDN is 1723856
        $r = ($jdn - 1723856) % 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);
        $eyear = 4 * intdiv($jdn - 1723856, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $emonth = intdiv($n, 30) + 1;
        $eday = ($n % 30) + 1;

        return [$eyear, $emonth, $eday];
    }

    /**
     * Convert Ethiopian date to Gregorian date.
     *
     * @return array{0: int, 1: int, 2: int} [gyear, gmonth, gday]
     */
    public static function ethiopianToGregorian(int $eyear, int $emonth, int $eday): array
    {
        $jdn = 1723856 + 365 * ($eyear - 1) + intdiv($eyear, 4) + 30 * ($emonth - 1) + $eday - 1;

        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);
        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);

        return [$year, $month, $day];
    }

    /**
     * Get the current Ethiopian date and time payload.
     */
    public static function getCurrentEthiopianDate(?Carbon $carbon = null): array
    {
        $dt = $carbon ?? Carbon::now();
        [$eyear, $emonth, $eday] = self::gregorianToEthiopian((int)$dt->year, (int)$dt->month, (int)$dt->day);

        $monthMeta = self::MONTHS[$emonth] ?? ['am' => "ወር {$emonth}", 'en' => "Month {$emonth}"];
        $dayOfWeek = self::DAYS_OF_WEEK[$dt->dayOfWeek] ?? ['am' => '', 'en' => ''];
        $ethiopianTime = self::convert24ToEthiopianTime($dt->format('H:i'));

        return [
            'year' => $eyear,
            'month' => $emonth,
            'day' => $eday,
            'month_name_am' => $monthMeta['am'],
            'month_name_en' => $monthMeta['en'],
            'day_of_week_am' => $dayOfWeek['am'],
            'day_of_week_en' => $dayOfWeek['en'],
            'formatted_am' => "{$monthMeta['am']} {$eday} ቀን {$eyear} ዓ.ም",
            'formatted_en' => "{$monthMeta['en']} {$eday}, {$eyear} E.C.",
            'time_ethiopian' => $ethiopianTime,
        ];
    }

    /**
     * Convert 24h international time (e.g., '08:30', '17:00') to Ethiopian local time.
     * In Ethiopia, the 12-hour clock begins at sunrise (06:00 AM = 12:00 morning).
     */
    public static function convert24ToEthiopianTime(string $time24): string
    {
        $parts = explode(':', $time24);
        $h = (int)($parts[0] ?? 0);
        $m = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);

        // Ethiopian hour calculation
        $etH = $h >= 6 ? $h - 6 : $h + 6;
        if ($etH > 12) $etH -= 12;
        if ($etH === 0) $etH = 12;

        $period = 'ከቀኑ';
        if ($h >= 0 && $h < 6) {
            $period = 'ከሌሊቱ';
        } elseif ($h >= 6 && $h < 12) {
            $period = 'ጠዋት';
        } elseif ($h >= 12 && $h < 18) {
            $period = 'ከቀኑ';
        } else {
            $period = 'ከምሽቱ';
        }

        $formattedH = str_pad((string)$etH, 2, '0', STR_PAD_LEFT);
        return "{$formattedH}:{$m} {$period}";
    }

    /**
     * Get months list for selectors.
     */
    public static function getMonthsList(): array
    {
        $list = [];
        foreach (self::MONTHS as $num => $meta) {
            $list[] = [
                'number' => $num,
                'name_am' => $meta['am'],
                'name_en' => $meta['en'],
                'label' => "{$meta['am']} ({$meta['en']})",
                'days_count' => $num === 13 ? 5 : 30,
            ];
        }
        return $list;
    }
}
