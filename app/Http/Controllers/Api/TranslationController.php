<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;

class TranslationController extends Controller
{
    public function __construct(
        protected TranslationService $translationService
    ) {}

    public function getLanguages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->translationService->getAvailableLanguages(),
        ]);
    }

    public function getFront(string $lang = 'en'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'locale' => $lang,
            'translations' => $this->translationService->getFrontTranslations($lang),
        ]);
    }
}
