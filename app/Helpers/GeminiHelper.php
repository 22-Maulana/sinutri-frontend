<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiHelper
{
    /**
     * Call Gemini API with automatic model fallback to avoid HTTP 429 Quota Exceeded errors.
     * Models tried in order: gemini-2.0-flash -> gemini-1.5-flash -> gemini-2.5-flash
     */
    public static function generateContent($prompt, $inlineData = null, $mimeType = null, $timeout = 15)
    {
        $geminiKey = env('GEMINI_API_KEY');
        if (empty($geminiKey)) {
            Log::warning("[GEMINI-HELPER] GEMINI_API_KEY is not set.");
            return null;
        }

        $models = [
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-2.5-flash',
        ];

        $parts = [
            ["text" => $prompt]
        ];

        if ($inlineData && $mimeType) {
            $parts[] = [
                "inlineData" => [
                    "mimeType" => $mimeType,
                    "data" => $inlineData
                ]
            ];
        }

        $payload = [
            "contents" => [
                [
                    "parts" => $parts
                ]
            ],
            "generationConfig" => [
                "responseMimeType" => "application/json"
            ]
        ];

        foreach ($models as $model) {
            try {
                $response = Http::timeout($timeout)->withHeaders([
                    'Content-Type' => 'application/json'
                ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}", $payload);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    if (!empty($text)) {
                        Log::info("[GEMINI-HELPER] Success using model: {$model}");
                        return preg_replace('/^```json\s*|\s*```$/m', '', trim($text));
                    }
                } else {
                    $status = $response->statusCode();
                    $body = $response->body();
                    Log::warning("[GEMINI-HELPER] Model {$model} returned {$status}: {$body}");

                    // If quota exceeded (429) or model unavailable, try next model in list
                    if ($status == 429 || str_contains($body, 'RESOURCE_EXHAUSTED')) {
                        Log::info("[GEMINI-HELPER] Quota exceeded for {$model}. Falling back to next model...");
                        continue;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[GEMINI-HELPER] Exception with {$model}: " . $e->getMessage());
            }
        }

        return null;
    }
}
