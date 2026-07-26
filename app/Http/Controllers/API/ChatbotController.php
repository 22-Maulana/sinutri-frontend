<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        set_time_limit(90);

        $request->validate([
            'message' => 'required|string',
        ]);

        $user = $request->user();
        $profile = $user->userProfile;

        $geminiKey = env('GEMINI_API_KEY');
        $pineconeKey = env('PINECONE_API_KEY');
        $pineconeHost = rtrim(env('PINECONE_HOST'), '/');

        $userMessage = $request->message;

        // Step 1: Embedding user question
        $embedResponse = Http::timeout(30)->withHeaders([
            'Content-Type' => 'application/json'
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent?key={$geminiKey}", [
            "model" => "models/gemini-embedding-2",
            "content" => [
                "parts" => [
                    ["text" => $userMessage]
                ]
            ]
        ]);

        $ragContext = "";
        if ($embedResponse->successful()) {
            $embedding = $embedResponse->json('embedding.values');

            // Query Pinecone for relevant knowledge (diabetes, GI, nutrition)
            $pineconeResponse = Http::timeout(30)->withHeaders([
                'Api-Key' => $pineconeKey,
                'Content-Type' => 'application/json'
            ])->post($pineconeHost . '/query', [
                "namespace" => "knowledge-diabetes", // Namespace khusus untuk edukasi diabetes, GI, AKG, TKPI
                "vector" => $embedding,
                "topK" => 5,
                "includeMetadata" => true
            ]);

            if ($pineconeResponse->successful()) {
                $matches = $pineconeResponse->json('matches');
                if (!empty($matches)) {
                    $contextParts = [];
                    foreach ($matches as $match) {
                        $meta = $match['metadata'] ?? [];
                        if (isset($meta['text'])) {
                            $contextParts[] = $meta['text'];
                        } elseif (isset($meta['content'])) {
                            $contextParts[] = $meta['content'];
                        }
                    }
                    $ragContext = implode("\n\n", $contextParts);
                }
            }
        }

        // Build system prompt for NutriBot
        $profileInfo = "";
        if ($profile) {
            $diabetesStatusMap = [
                'dm_type_1' => 'Diabetes Mellitus Tipe 1',
                'dm_type_2' => 'Diabetes Mellitus Tipe 2',
                'prediabetes' => 'Prediabetes',
                'not_diagnosed' => 'Belum Terdiagnosis DM'
            ];
            $profileInfo = "\n\nPROFIL PENGGUNA:\nNama: {$profile->name}, Usia: {$profile->age} tahun, Status Diabetes: {$diabetesStatusMap[$profile->diabetes_status]}, BMI: {$profile->bmi}.";
        }

        $systemPrompt = "Anda adalah NutriBot, asisten virtual ahli gizi spesialis Diabetes Mellitus dan pangan lokal Indonesia.

Anda dapat menjawab pertanyaan tentang:
1. Edukasi Diabetes Mellitus (DM Tipe 1, DM Tipe 2, Prediabetes)
2. Indeks Glikemik (GI) dan Glycemic Load (GL)
3. Informasi Makanan dan nutrisi berdasarkan TKPI (Tabel Komposisi Pangan Indonesia)
4. Angka Kecukupan Gizi (AKG) Indonesia
5. Pangan lokal Indonesia yang sehat untuk penderita diabetes
6. Saran pola makan dan gaya hidup sehat

Gunakan pengetahuan dari database TKPI, AKG Indonesia, dan referensi medis terpercaya.{$profileInfo}

KNOWLEDGE BASE (dari RAG):
{$ragContext}

Jawab dalam Bahasa Indonesia dengan ramah, informatif, dan berdasarkan evidence-based nutrition science. Jika pertanyaan di luar topik, ingatkan pengguna bahwa Anda adalah spesialis nutrisi dan diabetes.";

        try {
            $response = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $systemPrompt . "\n\nPERTANYAAN PENGGUNA:\n" . $userMessage]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error("NutriBot Gemini Error", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return response()->json([
                    'message' => 'Gagal menghubungi AI server.',
                    'error' => $response->json()
                ], $response->status());
            }

            $data = $response->json();
            
            $reply = 'Maaf, saya tidak dapat menjawab saat ini.';
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $reply = $data['candidates'][0]['content']['parts'][0]['text'];
            }

            return response()->json([
                'reply' => trim($reply)
            ]);

        } catch (\Exception $e) {
            Log::error("NutriBot Exception", ['message' => $e->getMessage()]);
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
