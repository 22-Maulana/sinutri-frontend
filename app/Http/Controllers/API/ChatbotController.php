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
        set_time_limit(60);
        Log::info("Chatbot endpoint hit", ['message' => $request->message]);

        $request->validate([
            'message' => 'required|string',
        ]);

        $apiKey = env('DEEPSEEK_API_KEY');
        $model = env('DEEPSEEK_MODEL', 'deepseek-v4-flash');

        $targetProfile = $request->input('target_profile', 'Saya');
        $user = $request->user();

        $systemPrompt = "Anda adalah Gizilens AI, asisten ahli gizi profesional untuk ibu hamil dan balita. 
        Saat ini Anda sedang berbicara dengan pengguna yang sedang memantau profil: {$targetProfile}.
        Nama Ibu: {$user->name}.
        
        Tugas Anda adalah menjawab pertanyaan user dengan ramah, informatif, dan berbasis data kesehatan yang valid. 
        Sesuaikan saran Anda berdasarkan siapa yang sedang ditanyakan (apakah Ibu sendiri atau anaknya).
        Jika user bertanya hal di luar gizi, kesehatan ibu, atau tumbuh kembang anak, ingatkan mereka secara halus bahwa Anda adalah spesialis gizi.";

        try {
            $response = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$apiKey}",
            ])->post("https://api.deepseek.com/chat/completions", [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $request->message]
                ],
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ]);

            if ($response->failed()) {
                Log::error("Chatbot DeepSeek AI Error Response", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return response()->json([
                    'message' => 'Gagal menghubungi DeepSeek AI Server.',
                    'error' => $response->json()
                ], $response->status());
            }

            $data = $response->json();
            
            $reply = 'Maaf, saya sedang tidak bisa menjawab saat ini.';
            if (isset($data['choices'][0]['message']['content'])) {
                $reply = $data['choices'][0]['message']['content'];
            }

            return response()->json([
                'reply' => $reply
            ]);

        } catch (\Exception $e) {
            Log::error("Chatbot Exception", ['message' => $e->getMessage()]);
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
