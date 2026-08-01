<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiHelper
{
    /**
     * Multi-API Key Rotation + Model Fallback Strategy
     *
     * Algorithm:
     * 1. Coba KEY 1 → models (lite dulu, lalu standard)
     * 2. Jika Key 1 habis kuota → coba KEY 2 dengan model terbaik
     * 3. Jika Key 2 habis kuota → coba KEY 3 dengan model terbaik
     * 4. Jika Key 3 habis kuota → coba KEY 4 dengan model terbaik
     * 5. Jika semua Keys habis → return null (ScanController akan fallback ke DeepSeek)
     */
    public static function generateContent($prompt, $inlineData = null, $mimeType = null, $timeout = 15)
    {
        // ─── Ambil semua API Key dari .env ───
        $keys = array_filter([
            env('GEMINI_API_KEY'),
            env('GEMINI_API_KEY_2'),
            env('GEMINI_API_KEY_3'),
            env('GEMINI_API_KEY_4'),
        ]);

        if (empty($keys)) {
            Log::warning("[GEMINI-HELPER] Tidak ada GEMINI_API_KEY yang dikonfigurasi.");
            return null;
        }

        // ─── Model Priority: Lite model dulu (kuota lebih besar), lalu standard ───
        $models = [
            'gemini-2.0-flash-lite',  // Kuota terbesar, paling cepat
            'gemini-2.0-flash',       // Standar
            'gemini-2.5-flash',       // Terbaru tapi kuota lebih kecil
        ];

        // ─── Susun payload ───
        $parts = [["text" => $prompt]];

        if ($inlineData && $mimeType) {
            $parts[] = [
                "inlineData" => [
                    "mimeType" => $mimeType,
                    "data"     => $inlineData,
                ]
            ];
        }

        $payload = [
            "contents"         => [["parts" => $parts]],
            "generationConfig" => ["responseMimeType" => "application/json"],
        ];

        // ─── Algoritma: Iterasi KEY dulu, lalu MODEL ───
        foreach (array_values($keys) as $keyIndex => $apiKey) {
            $keyLabel = "Key #" . ($keyIndex + 1);

            foreach ($models as $model) {
                try {
                    Log::info("[GEMINI-HELPER] Mencoba {$keyLabel} + model {$model}...");

                    $response = Http::timeout($timeout)->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                        $payload
                    );

                    if ($response->successful()) {
                        $text = $response->json('candidates.0.content.parts.0.text');
                        if (!empty($text)) {
                            Log::info("[GEMINI-HELPER] ✅ Sukses dengan {$keyLabel} + {$model}");
                            return preg_replace('/^```json\s*|\s*```$/m', '', trim($text));
                        }
                        // Response OK tapi kosong → coba model berikutnya
                        Log::warning("[GEMINI-HELPER] {$keyLabel}+{$model}: Response 200 tapi content kosong.");
                        continue;
                    }

                    $status = $response->status();
                    $body   = $response->body();

                    // ─── 429: Key ini habis untuk model ini → coba model berikutnya di key yang sama ───
                    if ($status === 429 || str_contains($body, 'RESOURCE_EXHAUSTED')) {
                        Log::warning("[GEMINI-HELPER] ⚠️ {$keyLabel}+{$model} QUOTA EXCEEDED (429). Coba model berikutnya di key ini...");
                        continue; // lanjut ke model berikutnya dalam key ini
                    }

                    // ─── 404: Model tidak tersedia → lewati saja ───
                    if ($status === 404 || str_contains($body, 'NOT_FOUND')) {
                        Log::warning("[GEMINI-HELPER] {$keyLabel}+{$model} NOT FOUND (404). Skip model ini.");
                        continue;
                    }

                    // Error lain → log dan coba model berikutnya
                    Log::warning("[GEMINI-HELPER] {$keyLabel}+{$model} Error {$status}: " . substr($body, 0, 200));
                    continue;

                } catch (\Exception $e) {
                    Log::warning("[GEMINI-HELPER] Exception {$keyLabel}+{$model}: " . $e->getMessage());
                    continue;
                }
            }

            // ─── Semua model pada key ini habis → pindah ke key berikutnya ───
            Log::info("[GEMINI-HELPER] ❌ Semua model pada {$keyLabel} habis kuota. Auto-switching ke key berikutnya...");
        }

        // ─── Semua 4 Key habis ───
        Log::warning("[GEMINI-HELPER] ❌ Semua Gemini API Keys habis kuota. Returning null → DeepSeek akan mengambil alih.");
        return null;
    }
}
