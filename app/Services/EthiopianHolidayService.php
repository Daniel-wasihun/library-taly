<?php

namespace App\Services;

use App\Models\EthiopianHoliday;

class EthiopianHolidayService
{
    /**
     * Get list of manual user-inserted holidays for a specific Ethiopian year and month.
     * "the holiday must be inserted manually by the user not auto detected"
     *
     * @param int $year Ethiopian year (e.g. 2019)
     * @param int $month Ethiopian month (1 to 13)
     * @param int|null $libraryId Optional branch filter
     * @return array<int, array{id: int, day: int, name: string, name_en: string, name_am: string, description: string, library_id: int|null}>
     */
    public static function getHolidaysForMonth(int $year, int $month, ?int $libraryId = null): array
    {
        $query = EthiopianHoliday::where('ethiopian_year', $year)
            ->where('ethiopian_month', $month);

        if ($libraryId) {
            $query->where(function ($q) use ($libraryId) {
                $q->where('library_id', $libraryId)
                  ->orWhereNull('library_id');
            });
        }

        $records = $query->orderBy('ethiopian_day')->get();
        $holidays = [];

        foreach ($records as $record) {
            $day = (int)$record->ethiopian_day;
            $holidays[$day] = [
                'id' => $record->id,
                'day' => $day,
                'name' => $record->name,
                'name_en' => $record->name,
                'name_am' => $record->name_am ?: $record->name,
                'description' => $record->description ?: 'User-defined Ethiopian holiday',
                'library_id' => $record->library_id,
            ];
        }

        return $holidays;
    }

    /**
     * Check if a specific Ethiopian day is a user-defined holiday.
     */
    public static function isHoliday(int $year, int $month, int $day, ?int $libraryId = null): ?array
    {
        $holidays = self::getHolidaysForMonth($year, $month, $libraryId);
        return $holidays[$day] ?? null;
    }
}
