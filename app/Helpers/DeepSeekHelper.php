<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekHelper
{
    /**
     * Call DeepSeek AI API (deepseek-v4-flash / deepseek-chat) for RAG Reasoning & DSS Analysis.
     */
    public static function generateContent($prompt, $systemPrompt = null, $timeout = 15)
    {
        $apiKey = env('DEEPSEEK_API_KEY');
        $model = env('DEEPSEEK_MODEL', 'deepseek-chat');

        if (empty($apiKey)) {
            Log::warning("[DEEPSEEK-HELPER] DEEPSEEK_API_KEY is not set.");
            return null;
        }

        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        } else {
            $messages[] = [
                'role' => 'system', 
                'content' => 'Anda adalah ahli gizi AI spesialis Diabetes Mellitus & Nutrisi Personal Indonesia. Berikan jawaban dalam format JSON presisi.'
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $response = Http::timeout($timeout)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                if (!empty($content)) {
                    Log::info("[DEEPSEEK-HELPER] Success using model: {$model}");
                    return preg_replace('/^```json\s*|\s*```$/m', '', trim($content));
                }
            } else {
                Log::warning("[DEEPSEEK-HELPER] DeepSeek API returned " . $response->statusCode() . ": " . $response->body());
            }
        } catch (\Exception $e) {
            Log::warning("[DEEPSEEK-HELPER] Exception: " . $e->getMessage());
        }

        return null;
    }
}
