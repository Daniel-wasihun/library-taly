<?php

namespace App\Services;

class EthiopianHolidayService
{
    /**
     * Fixed Ethiopian National & Religious Holidays (Month => [Day => Holiday Metadata])
     * Month is 1 to 13 (1 = Meskerem, 2 = Tikimt, ... 13 = Pagume)
     */
    public const FIXED_HOLIDAYS = [
        // Meskerem (1)
        1 => [
            1 => [
                'name_en' => 'Enkutatash (Ethiopian New Year)',
                'name_am' => 'እንቁጣጣሽ (አዲስ ዓመት)',
                'description' => 'First day of the Ethiopian New Year.',
                'type' => 'national',
            ],
            17 => [
                'name_en' => 'Meskel (Finding of the True Cross)',
                'name_am' => 'መስቀል (ደመራ)',
                'description' => 'UNESCO-registered Feast of the Finding of the True Cross.',
                'type' => 'religious',
            ],
        ],

        // Tikimt (2)
        2 => [
            // Floating or special cultural commemorations if any
        ],

        // Hidar (3)
        3 => [
            12 => [
                'name_en' => 'Hidar Zion (Commemoration)',
                'name_am' => 'ኅዳር ጽዮን',
                'description' => 'Major historical commemoration.',
                'type' => 'cultural',
            ],
        ],

        // Tahsas (4)
        4 => [
            29 => [
                'name_en' => 'Genna (Ethiopian Christmas)',
                'name_am' => 'ገና (የገና በዓል)',
                'description' => 'Ethiopian Orthodox Christmas celebration (Tahsas 28 in leap years).',
                'type' => 'religious',
            ],
        ],

        // Tir (5)
        5 => [
            11 => [
                'name_en' => 'Timkat (Ethiopian Epiphany)',
                'name_am' => 'ጥምቀት',
                'description' => 'UNESCO-registered Ethiopian Epiphany celebration.',
                'type' => 'religious',
            ],
        ],

        // Yakatit (6)
        6 => [
            23 => [
                'name_en' => 'Victory of Adwa Day',
                'name_am' => 'የአድዋ ድል በዓል',
                'description' => 'Historic national victory of Adwa (March 1/2 G.C.).',
                'type' => 'national',
            ],
        ],

        // Megabit (7)
        7 => [
            // Variable Easter/Good Friday sometimes falls in late Megabit
        ],

        // Miazia (8)
        8 => [
            23 => [
                'name_en' => 'International Labour Day',
                'name_am' => 'የሠራተኞች ቀን',
                'description' => 'Workers Day (May 1 G.C. corresponds to Miazia 23 E.C.).',
                'type' => 'national',
            ],
            27 => [
                'name_en' => "Patriots' Victory Day",
                'name_am' => 'የአርበኞች ቀን',
                'description' => 'Liberation of Ethiopia from fascist occupation (May 5 G.C.).',
                'type' => 'national',
            ],
        ],

        // Ginbot (9)
        9 => [
            20 => [
                'name_en' => 'Ginbot 20 National Day (Downfall of Derg)',
                'name_am' => 'ግንቦት 20 (የደርግ ውድቀት)',
                'description' => 'National commemoration of the defeat of the Derg regime.',
                'type' => 'national',
            ],
        ],

        // Sene (10)
        10 => [],

        // Hamle (11)
        11 => [],

        // Nehase (12)
        12 => [
            16 => [
                'name_en' => 'Buhe / Debre Tabor',
                'name_am' => 'ደብረ ታቦር (ቡሄ)',
                'description' => 'Transfiguration festival.',
                'type' => 'cultural',
            ],
        ],

        // Pagume (13)
        13 => [],
    ];

    /**
     * Get list of holidays for a specific Ethiopian year and month.
     *
     * @param int $year Ethiopian year (e.g. 2019)
     * @param int $month Ethiopian month (1 to 13)
     * @return array<int, array{day: int, name_en: string, name_am: string, description: string, type: string}>
     */
    public static function getHolidaysForMonth(int $year, int $month): array
    {
        $holidays = [];
        $monthHolidays = self::FIXED_HOLIDAYS[$month] ?? [];

        // Check Genna leap year rule: If Ethiopian year is leap (year % 4 === 3), Genna is Tahsas 28 instead of 29
        $isLeapYear = ($year % 4 === 3);

        foreach ($monthHolidays as $day => $meta) {
            $actualDay = $day;
            if ($month === 4 && $day === 29 && $isLeapYear) {
                $actualDay = 28;
            }

            $holidays[$actualDay] = array_merge(['day' => $actualDay], $meta);
        }

        return $holidays;
    }

    /**
     * Check if a specific Ethiopian day is an official holiday.
     */
    public static function isHoliday(int $year, int $month, int $day): ?array
    {
        $holidays = self::getHolidaysForMonth($year, $month);
        return $holidays[$day] ?? null;
    }
}
