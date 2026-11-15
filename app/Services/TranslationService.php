<?php

namespace App\Services;

use App\Translation\Front\Amharic as FrontAmharic;
use App\Translation\Front\English as FrontEnglish;

class TranslationService
{
    protected array $frontClasses = [
        'en' => FrontEnglish::class,
        'am' => FrontAmharic::class,
    ];

    public function getAvailableLanguages(): array
    {
        return [
            ['key' => 'en', 'name' => 'English', 'label' => 'English', 'icon' => '🇬🇧'],
            ['key' => 'am', 'name' => 'Amharic', 'label' => 'አማርኛ', 'icon' => '🇪🇹'],
        ];
    }

    public function getFrontTranslations(string $lang = 'en'): array
    {
        $lang = strtolower($lang);
        if (!isset($this->frontClasses[$lang])) {
            $lang = 'en';
        }

        $base = FrontEnglish::get();
        if ($lang === 'en') {
            return $base;
        }

        $class = $this->frontClasses[$lang];
        $specific = $class::get();

        return array_merge($base, $specific);
    }
}
