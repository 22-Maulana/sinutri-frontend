<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\FoodLog;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ScanController extends Controller
{
    public function scan(Request $request)
    {
        set_time_limit(180);

        Log::info("[SCAN-BE] Memproses permintaan Scan Makanan.", [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
            'notes' => $request->input('notes'),
            'has_image' => $request->hasFile('image'),
        ]);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'notes' => 'nullable|string|max:1000'
        ]);

        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            Log::warning("[SCAN-BE] Scan gagal: User ID {$user->id} belum memiliki UserProfile.");
            return response()->json(['error' => 'Harap lengkapi profil kesehatan terlebih dahulu.'], 422);
        }

        $imagePath = $request->file('image')->getPathname();
        $mimeType = $request->file('image')->getMimeType();
        $base64Image = base64_encode(file_get_contents($imagePath));

        $geminiKey = env('GEMINI_API_KEY');
        $pineconeKey = env('PINECONE_API_KEY');
        $pineconeHost = rtrim(env('PINECONE_HOST'), '/');

        $userNotes = $request->input('notes', '');

        $diabetesStatusMap = [
            'dm_type_1' => 'Diabetes Mellitus Tipe 1',
            'dm_type_2' => 'Diabetes Mellitus Tipe 2',
            'prediabetes' => 'Prediabetes',
            'not_diagnosed' => 'Belum Terdiagnosis DM'
        ];

        $profileInfo = "Profil Pengguna: {$profile->name}, Usia {$profile->age} tahun, ";
        $profileInfo .= "Status Diabetes: {$diabetesStatusMap[$profile->diabetes_status]}, ";
        $profileInfo .= "Riwayat Keluarga DM: " . ($profile->family_diabetes_history ? 'Ya' : 'Tidak') . ", ";
        $profileInfo .= "BMI: {$profile->bmi}. ";

        $allergies = $profile->food_allergies ?? [];
        if (!empty($allergies)) {
            $profileInfo .= "Alergi: " . implode(', ', $allergies) . ". ";
        }

        $extractionPrompt = "Identifikasi makanan dalam gambar ini. Gabungkan dengan catatan pengguna: \"{$userNotes}\".
        Return ONLY a JSON object with 'food_name' (detected food name) and 'ingredients' (array of ingredient strings).
        Example: {\"food_name\": \"Nasi Goreng\", \"ingredients\": [\"Nasi Putih\", \"Telur\", \"Minyak Goreng\", \"Bawang Merah\"]}";

        // Tahap 1: Gemini Vision (dengan image)
        $visionText = \App\Helpers\GeminiHelper::generateContent($extractionPrompt, $base64Image, $mimeType, 15);
        $detectionData = $visionText ? json_decode($visionText, true) : null;

        // Tahap 2: Jika Gemini gagal/limit, gunakan DeepSeek untuk inferensi cerdas dari userNotes
        if (empty($detectionData) || empty($detectionData['food_name'])) {
            Log::info("[SCAN-BE] Gemini Vision gagal. Menggunakan DeepSeek AI untuk inferensi nama makanan dari catatan pengguna...");

            if (!empty($userNotes)) {
                $deepseekFoodPrompt = "Pengguna menginformasikan makanan: \"{$userNotes}\".
Identifikasi nama makanan utama dan list bahan-bahan yang biasanya terkandung dalam makanan tersebut di masakan Indonesia.
Return ONLY JSON: {\"food_name\": \"Nama Makanan\", \"ingredients\": [\"Bahan1\", \"Bahan2\", \"Bahan3\", \"Bahan4\"]}";

                $deepseekText = \App\Helpers\DeepSeekHelper::generateContent($deepseekFoodPrompt);
                if ($deepseekText) {
                    $deepseekData = json_decode($deepseekText, true);
                    if (!empty($deepseekData['food_name'])) {
                        $detectionData = $deepseekData;
                        Log::info("[SCAN-BE] DeepSeek berhasil menginferensi makanan: " . $deepseekData['food_name']);
                    }
                }
            }

            // Tahap 3: Jika masih kosong, gunakan userNotes langsung
            if (empty($detectionData) || empty($detectionData['food_name'])) {
                if (!empty($userNotes)) {
                    $foodName = ucwords(trim($userNotes));
                    $ingredients = array_map('trim', explode(',', $userNotes));
                    // Jika hanya satu item, gunakan TkpiDictionary untuk dapat bahan-bahan umum
                    if (count($ingredients) === 1) {
                        $dictData = \App\Helpers\TkpiDictionary::lookup($foodName);
                        $ingredients = [$foodName, 'Bumbu Rempah', 'Minyak Goreng', 'Garam'];
                    }
                } else {
                    $foodName = "Nasi Campur";
                    $ingredients = ["Nasi Putih", "Lauk Protein", "Sayuran", "Sambal"];
                }
                $detectionData = [
                    'food_name'   => $foodName ?? ucwords(trim($userNotes)),
                    'ingredients' => $ingredients,
                ];
            }
        }

        $foodName = $detectionData['food_name'];
        $ingredients = $detectionData['ingredients'] ?? [$foodName];
        $ingredientsString = implode(', ', $ingredients);

        // Estimasi berat: Gemini dulu, DeepSeek auto-switch jika limit
        $weightPrompt = "Makanan: {$foodName}. Bahan-bahan: [{$ingredientsString}]. Catatan: \"{$userNotes}\".
        Estimasi berat masing-masing bahan dalam gram untuk SATU PORSI standar Indonesia.
        Return ONLY a JSON object mapping ingredient name to weight in grams.
        Example: {\"Nasi Putih\": 150, \"Ayam Goreng\": 80, \"Minyak Goreng\": 10}";

        $weightText = \App\Helpers\GeminiHelper::generateContent($weightPrompt, $base64Image, $mimeType, 10);
        if (!$weightText) {
            // DeepSeek auto-switch untuk estimasi berat
            $weightText = \App\Helpers\DeepSeekHelper::generateContent($weightPrompt);
        }
        $weights = $weightText ? (json_decode($weightText, true) ?? []) : [];

        $totalCalories = 0;
        $totalProtein = 0;
        $totalFat = 0;
        $totalCarbs = 0;
        $totalFiber = 0;
        $totalSugar = 0;
        $totalWeight = 0;
        $tkpiDetails = [];
        $glycemicIndexWeighted = 0;

        foreach ($ingredients as $ingredient) {
            $weight = $weights[$ingredient] ?? 50;
            $totalWeight += $weight;

            $embedResponse = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent?key={$geminiKey}", [
                "model" => "models/gemini-embedding-2",
                "content" => [
                    "parts" => [
                        ["text" => "Nama Makanan: " . $ingredient]
                    ]
                ]
            ]);

            if ($embedResponse->successful()) {
                $embedding = $embedResponse->json('embedding.values');

                $pineconeResponse = Http::timeout(10)->withHeaders([
                    'Api-Key' => $pineconeKey,
                    'Content-Type' => 'application/json'
                ])->post($pineconeHost . '/query', [
                    "namespace" => "tkpi-indonesia",
                    "vector" => $embedding,
                    "topK" => 1,
                    "includeMetadata" => true
                ]);

                if ($pineconeResponse->successful()) {
                    $match = $pineconeResponse->json('matches.0');
                    if ($match) {
                        $meta = $match['metadata'];

                        $calVal = (double)($meta['kalori'] ?? $meta['energi'] ?? 0);
                        $protVal = (double)($meta['protein'] ?? 0);
                        $fatVal = (double)($meta['lemak'] ?? 0);
                        $carbsVal = (double)($meta['karbohidrat'] ?? 0);
                        $fiberVal = (double)($meta['serat'] ?? 0);
                        $sugarVal = (double)($meta['gula'] ?? 0);
                        $giVal = (double)($meta['glycemic_index'] ?? $meta['gi'] ?? 0);

                        $factor = $weight / 100.0;
                        $totalCalories += $calVal * $factor;
                        $totalProtein += $protVal * $factor;
                        $totalFat += $fatVal * $factor;
                        $totalCarbs += $carbsVal * $factor;
                        $totalFiber += $fiberVal * $factor;
                        $totalSugar += $sugarVal * $factor;

                        if ($giVal > 0) {
                            $glycemicIndexWeighted += ($giVal * $carbsVal * $factor);
                        }

                        $tkpiDetails[] = "- {$ingredient} ({$weight}g) -> '{$meta['nama_makanan']}': Kalori " . round($calVal * $factor, 1) . " kkal, Karbo " . round($carbsVal * $factor, 1) . "g, Protein " . round($protVal * $factor, 1) . "g, Lemak " . round($fatVal * $factor, 1) . "g, Serat " . round($fiberVal * $factor, 1) . "g, Gula " . round($sugarVal * $factor, 1) . "g" . ($giVal > 0 ? ", GI {$giVal}" : "");
                    }
                }
            }
        }

        if ($totalCalories == 0) {
            Log::info("[SCAN-BE] Pinecone RAG returned 0 calories. Using TkpiDictionary offline lookup for '{$foodName}'...");
            $dictData = \App\Helpers\TkpiDictionary::lookup($foodName);
            $totalCalories = (double)$dictData['kalori'];
            $totalCarbs = (double)$dictData['karbo'];
            $totalProtein = (double)$dictData['protein'];
            $totalFat = (double)$dictData['lemak'];
            $totalFiber = (double)$dictData['serat'];
            $totalSugar = (double)$dictData['gula'];
            $glycemicIndexWeighted = (double)$dictData['gi'] * $totalCarbs;

            $nutritionPrompt = "Untuk makanan '{$foodName}' dan bahan: " . json_encode($weights) . ". Estimasi total nilai nutrisi dalam JSON.";
            $nutritionText = \App\Helpers\GeminiHelper::generateContent($nutritionPrompt, null, null, 10);
            if ($nutritionText) {
                $nutrients = json_decode($nutritionText, true);
                if ($nutrients && isset($nutrients['calories_kcal']) && $nutrients['calories_kcal'] > 0) {
                    $totalCalories = (double)($nutrients['calories_kcal']);
                    $totalProtein = (double)($nutrients['protein_g'] ?? $totalProtein);
                    $totalFat = (double)($nutrients['fat_g'] ?? $totalFat);
                    $totalCarbs = (double)($nutrients['carbs_g'] ?? $totalCarbs);
                    $totalFiber = (double)($nutrients['fiber_g'] ?? $totalFiber);
                    $totalSugar = (double)($nutrients['sugar_g'] ?? $totalSugar);
                    $glycemicIndexWeighted = (double)($nutrients['glycemic_index'] ?? 50) * $totalCarbs;
                }
            }
        }

        $avgGlycemicIndex = ($totalCarbs > 0 && $glycemicIndexWeighted > 0) 
            ? round($glycemicIndexWeighted / $totalCarbs, 1) 
            : 0;

        $glycemicLoad = ($avgGlycemicIndex > 0 && $totalCarbs > 0) 
            ? round(($avgGlycemicIndex * $totalCarbs) / 100, 1) 
            : 0;

        $glycemicScore = $glycemicLoad;

        $riskCategory = 'low';
        if ($glycemicScore >= 20) {
            $riskCategory = 'high';
        } elseif ($glycemicScore >= 11) {
            $riskCategory = 'medium';
        }

        $tkpiContext = implode("\n", $tkpiDetails);

        $dssPrompt = "Anda adalah ahli gizi spesialis Diabetes Mellitus. Saya akan memberikan informasi makanan yang dipindai pengguna, nilai nutrisi, dan profil kesehatan.

USER PROFILE:
{$profileInfo}

USER NOTES:
\"{$userNotes}\"

MAKANAN TERDETEKSI:
Nama: {$foodName}
Bahan: {$ingredientsString}

NUTRISI TOTAL (dari TKPI):
Total Kalori: {$totalCalories} kkal
Total Karbohidrat: {$totalCarbs} g
Total Gula: {$totalSugar} g
Total Protein: {$totalProtein} g
Total Lemak: {$totalFat} g
Total Serat: {$totalFiber} g
Estimasi Indeks Glikemik: {$avgGlycemicIndex}
Glycemic Load: {$glycemicLoad}
Kategori Risiko Glikemik: {$riskCategory}

Detail per bahan:
{$tkpiContext}

TASK:
1. Berikan status rekomendasi untuk penderita diabetes (DIANJURKAN | PERHATIAN | HINDARI).
   *CRITICAL*: Jika ada bahan yang termasuk alergi pengguna, WAJIB HINDARI dan jelaskan.
   Pertimbangkan status diabetes pengguna ({$diabetesStatusMap[$profile->diabetes_status]}).
2. Berikan AI Insight dalam Bahasa Indonesia menjelaskan dampak makanan ini terhadap gula darah pengguna (2-3 kalimat).
3. Berikan AI Recommendation berupa saran praktis untuk pengguna (2-3 kalimat).
4. Berikan 2-3 alternative_foods berupa pangan lokal Indonesia dengan GI lebih rendah sebagai pengganti. Format array of objects: [{\"name\": \"Nasi Merah\", \"reason\": \"GI lebih rendah dan kaya serat\"}]

Return ONLY a JSON object:
{
  \"recommendation_status\": \"DIANJURKAN|PERHATIAN|HINDARI\",
  \"ai_insight\": \"string (2-3 kalimat)\",
  \"ai_recommendation\": \"string (2-3 kalimat)\",
  \"alternative_foods\": [{\"name\": \"string\", \"reason\": \"string\"}]
}";

        // Primary: Gemini API -> Auto-Switch to DeepSeek API on limit
        $dssText = \App\Helpers\GeminiHelper::generateContent($dssPrompt, null, null, 10);
        if (!$dssText) {
            Log::info("[SCAN-BE] Gemini API limit/unavailable. Auto-switching to DeepSeek AI API...");
            $dssText = \App\Helpers\DeepSeekHelper::generateContent($dssPrompt);
        }
        $dssData = $dssText ? json_decode($dssText, true) : null;

        if (!$dssData) {
            $status = $riskCategory === 'high' ? 'HINDARI' : ($riskCategory === 'medium' ? 'PERHATIAN' : 'DIANJURKAN');
            $dssData = [
                'recommendation_status' => $status,
                'ai_insight' => "Menu {$foodName} ini dianalisis memiliki estimasi kalori " . round($totalCalories, 0) . " kkal dengan total karbohidrat " . round($totalCarbs, 1) . "g dan Glycemic Score " . $glycemicScore . ".",
                'ai_recommendation' => "Konsumsi makanan ini secara bijak dengan memperhatikan porsi. Imbangi dengan asupan serat dan protein untuk menjaga kestabilan kadar gula darah.",
                'alternative_foods' => [
                    ['name' => 'Nasi Merah dengan Sayur Bening', 'reason' => 'Indeks Glikemik lebih rendah dan kaya serat pangan.'],
                    ['name' => 'Pepes Tahu / Ikan Kukus', 'reason' => 'Tinggi protein tanpa tambahan gula olahan.']
                ]
            ];
        }

        $photoUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('public/food_scans');
            $photoUrl = asset('storage/' . str_replace('public/', '', $path));
        }

        $responseData = [
            'food_name_detected' => $foodName,
            'ingredients' => $ingredients,
            'portion_grams' => round($totalWeight, 0),
            'photo_url' => $photoUrl,
            'calories_kcal' => round($totalCalories, 2),
            'carbs_g' => round($totalCarbs, 2),
            'sugar_g' => round($totalSugar, 2),
            'protein_g' => round($totalProtein, 2),
            'fat_g' => round($totalFat, 2),
            'fiber_g' => round($totalFiber, 2),
            'glycemic_index' => $avgGlycemicIndex,
            'glycemic_score' => $glycemicScore,
            'risk_category' => $riskCategory,
            'recommendation_status' => $dssData['recommendation_status'] ?? 'PERHATIAN',
            'ai_insight' => $dssData['ai_insight'] ?? '',
            'ai_recommendation' => $dssData['ai_recommendation'] ?? '',
            'alternative_foods' => $dssData['alternative_foods'] ?? [],
        ];

        return response()->json([
            'message' => 'Analisis makanan berhasil',
            'data' => $responseData
        ], 200);
    }
}
