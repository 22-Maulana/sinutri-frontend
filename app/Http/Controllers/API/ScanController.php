<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\FoodLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use App\Models\MotherProfile;
use App\Models\ChildProfile;

class ScanController extends Controller
{
    public function scan(Request $request)
    {
        set_time_limit(180);

        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'description' => 'nullable|string|max:1000',
            'target_type' => 'required|in:MOTHER,CHILD',
            'target_id' => 'required|uuid',
            'notes' => 'nullable|string'
        ]);

        $hasImage = $request->hasFile('image');
        $hasDescription = $request->filled('description');

        if (!$hasImage && !$hasDescription) {
            return response()->json(['error' => 'Harus menyertakan foto atau deskripsi makanan.'], 422);
        }

        $base64Image = null;
        $mimeType = null;
        if ($hasImage) {
            $imagePath = $request->file('image')->getPathname();
            $mimeType = $request->file('image')->getMimeType();
            $base64Image = base64_encode(file_get_contents($imagePath));
        }

        $geminiKey = env('GEMINI_API_KEY');
        $pineconeKey = env('PINECONE_API_KEY');
        $pineconeHost = rtrim(env('PINECONE_HOST'), '/');

        $profileInfo = "";
        $targetName = "";
        $allergies = [];
        if ($request->target_type === 'MOTHER') {
            $profile = MotherProfile::where('id', $request->target_id)->first();
            if ($profile) {
                $targetName = $profile->full_name;
                $profileInfo = "Subjek: Ibu (Status: {$profile->status}). ";
                $allergies = $profile->allergies ?? [];
                if (!empty($allergies)) {
                    $profileInfo .= "Alergi: " . implode(', ', $allergies) . ". ";
                }
            }
        } else {
            $profile = ChildProfile::where('id', $request->target_id)->first();
            if ($profile) {
                $targetName = $profile->name;
                $age = Carbon::parse($profile->birth_date)->diffInMonths(Carbon::now());
                $profileInfo = "Subjek: Anak (Usia: {$age} bulan). ";
                $allergies = $profile->allergies ?? [];
                if (!empty($allergies)) {
                    $profileInfo .= "Alergi: " . implode(', ', $allergies) . ". ";
                }
            }
        }

        $userNotes = $request->input('notes', '');
        $description = $request->input('description', '');

        // ==========================================================
        // Tahap 1: Ekstraksi Komposisi Bahan Makanan
        // ==========================================================
        if ($hasImage) {
            $extractionPrompt = "Identify all ingredients/components in this food image. Combine them with the user's additional notes: \"{$userNotes}\".
            Return ONLY a JSON array of ingredient strings, with no additional formatting or code blocks.
            Example response: [\"Nasi Putih\", \"Ayam Goreng\", \"Minyak Goreng\", \"Wortel\"]";

            $visionResponse = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $extractionPrompt],
                            [
                                "inlineData" => [
                                    "mimeType" => $mimeType,
                                    "data" => $base64Image
                                ]
                            ]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "responseMimeType" => "application/json"
                ]
            ]);

            if (!$visionResponse->successful()) {
                Log::error("Gemini Vision Error: " . $visionResponse->body());
                return response()->json(['error' => 'Gagal mendeteksi gambar dengan AI.'], 500);
            }

            $visionText = $visionResponse->json('candidates.0.content.parts.0.text');
            $ingredients = json_decode(trim($visionText), true);
        } else {
            $textPrompt = "From the following food description, extract all individual ingredients/components. Description: \"{$description}\". Additional notes: \"{$userNotes}\".
            Return ONLY a JSON array of ingredient strings.
            Example response: [\"Nasi Putih\", \"Ayam Goreng\", \"Sambal\"]";

            $textResponse = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $textPrompt]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "responseMimeType" => "application/json"
                ]
            ]);

            if (!$textResponse->successful()) {
                Log::error("Gemini Text Error: " . $textResponse->body());
                return response()->json(['error' => 'Gagal mengekstrak bahan dari deskripsi.'], 500);
            }

            $textResult = $textResponse->json('candidates.0.content.parts.0.text');
            $ingredients = json_decode(trim($textResult), true);
        }

        if (empty($ingredients) || !is_array($ingredients)) {
            return response()->json(['error' => 'Tidak ada bahan makanan yang terdeteksi.'], 400);
        }

        // ==========================================================
        // Tahap 2: Estimasi Porsi/Berat (gram) per Bahan
        // ==========================================================
        $ingredientsString = implode(', ', $ingredients);

        $weightParts = [
            ["text" => "Based on the identified ingredients: [{$ingredientsString}], user notes: \"{$userNotes}\", and description: \"{$description}\",
            estimate the weight of each ingredient in grams for a single serving.
            Return ONLY a JSON object mapping each ingredient to its weight in grams.
            Example response: {\"Nasi Putih\": 150, \"Ayam Goreng\": 80}"]
        ];
        if ($hasImage) {
            $weightParts[] = [
                "inlineData" => [
                    "mimeType" => $mimeType,
                    "data" => $base64Image
                ]
            ];
        }

        $weightResponse = Http::timeout(60)->withHeaders([
            'Content-Type' => 'application/json'
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
            "contents" => [["parts" => $weightParts]],
            "generationConfig" => ["responseMimeType" => "application/json"]
        ]);

        $weights = [];
        if ($weightResponse->successful()) {
            $weightText = $weightResponse->json('candidates.0.content.parts.0.text');
            $weights = json_decode(trim($weightText), true) ?? [];
        }

        // ==========================================================
        // Tahap 3: Embedding & Query TKPI per Bahan (RAG)
        // ==========================================================
        $totalCalories = 0;
        $totalProtein = 0;
        $totalFat = 0;
        $totalCarbs = 0;
        $totalFiber = 0;
        $totalIron = 0;
        $totalCalcium = 0;
        $tkpiDetails = [];

        foreach ($ingredients as $ingredient) {
            $weight = $weights[$ingredient] ?? 50;

            $embedResponse = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$geminiKey}", [
                "model" => "models/gemini-embedding-001",
                "content" => [
                    "parts" => [
                        ["text" => "Nama Makanan: " . $ingredient]
                    ]
                ]
            ]);

            if ($embedResponse->successful()) {
                $embedding = $embedResponse->json('embedding.values');

                $pineconeResponse = Http::timeout(30)->withHeaders([
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

                        $calVal = (double)($meta['kalori'] ?? 0);
                        $protVal = (double)($meta['protein'] ?? 0);
                        $fatVal = (double)($meta['lemak'] ?? 0);
                        $carbsVal = (double)($meta['karbohidrat'] ?? 0);
                        $fiberVal = (double)($meta['serat'] ?? $meta['fiber'] ?? 0);
                        $ironVal = (double)($meta['zat_besi'] ?? $meta['besi'] ?? $meta['iron'] ?? 0);
                        $calcVal = (double)($meta['kalsium'] ?? $meta['calcium'] ?? 0);

                        $factor = $weight / 100.0;
                        $totalCalories += $calVal * $factor;
                        $totalProtein += $protVal * $factor;
                        $totalFat += $fatVal * $factor;
                        $totalCarbs += $carbsVal * $factor;
                        $totalFiber += $fiberVal * $factor;
                        $totalIron += $ironVal * $factor;
                        $totalCalcium += $calcVal * $factor;

                         $tkpiDetails[] = "- {$ingredient} ({$weight}g) -> '{$meta['nama_makanan']}': Kal " . round($calVal * $factor, 1) . " kkal, Pro " . round($protVal * $factor, 1) . "g, Lem " . round($fatVal * $factor, 1) . "g, Kar " . round($carbsVal * $factor, 1) . "g";
                    }
                }
            }
        }

        if ($totalCalories == 0) {
            Log::info("Pinecone RAG returned 0 calories or failed. Falling back to Gemini direct nutrition estimation.");
            $nutritionPrompt = "For the following list of ingredients and their estimated weights:
            " . json_encode($weights) . "
            
            Estimate the total nutrition values.
            Return ONLY a JSON object:
            {
              \"calories_kcal\": float,
              \"protein_g\": float,
              \"fat_g\": float,
              \"carbs_g\": float,
              \"fiber_g\": float,
              \"iron_mg\": float,
              \"calcium_mg\": float
            }";

            $nutritionResponse = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $nutritionPrompt]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "responseMimeType" => "application/json"
                ]
            ]);

            if ($nutritionResponse->successful()) {
                $nutrients = json_decode(trim($nutritionResponse->json('candidates.0.content.parts.0.text')), true);
                if ($nutrients) {
                    $totalCalories = (double)($nutrients['calories_kcal'] ?? 0);
                    $totalProtein = (double)($nutrients['protein_g'] ?? 0);
                    $totalFat = (double)($nutrients['fat_g'] ?? 0);
                    $totalCarbs = (double)($nutrients['carbs_g'] ?? 0);
                    $totalFiber = (double)($nutrients['fiber_g'] ?? 0);
                    $totalIron = (double)($nutrients['iron_mg'] ?? 0);
                    $totalCalcium = (double)($nutrients['calcium_mg'] ?? 0);
                }
            }
        }

        $tkpiContext = implode("\n", $tkpiDetails);

        // ==========================================================
        // Tahap 4: Keputusan DSS & Rekomendasi
        // ==========================================================
        $dssParts = [
            ["text" => "You are an expert nutritionist. I will provide the list of ingredients, their calculated nutritional values, the user profile context, and user notes.
        
        USER PROFILE CONTEXT:
        {$profileInfo}
        
        USER NOTES ABOUT THIS MEAL:
        \"{$userNotes}\"

        INGREDIENTS & ESTIMATED NUTRIENTS (TKPI RAG):
        Ingredients: {$ingredientsString}
        Calculated Total Calories: {$totalCalories} kcal
        Calculated Total Protein: {$totalProtein} g
        Calculated Total Fat: {$totalFat} g
        Calculated Total Carbs: {$totalCarbs} g
        Calculated Total Fiber: {$totalFiber} g
        Calculated Total Iron: {$totalIron} mg
        Calculated Total Calcium: {$totalCalcium} mg
        
        Details per ingredient:
        {$tkpiContext}
        
        Task:
        1. Review the calculated nutrients and ingredients.
        2. Provide personalized recommendation status (DIANJURKAN|PERHATIAN|HINDARI).
           *CRITICAL*: If the ingredients contain any items listed in the USER's allergies, you MUST mark it as HINDARI and explain why in the notes.
           *CONTEXT*: Consider the user's status (pregnant/breastfeeding) or the child's age (MPASI suitability).
        3. Add notes in Indonesian explaining why it is recommended or not, and any tips. Mention the user/child name ($targetName) if appropriate.
        4. Output the food name detected representing the meal.

        Return ONLY a JSON object:
        {
          \"food_name_detected\": \"string\",
          \"notes\": \"string\",
          \"recommendation_status\": \"DIANJURKAN|PERHATIAN|HINDARI\"
        }"]
        ];
        if ($hasImage) {
            $dssParts[] = [
                "inlineData" => [
                    "mimeType" => $mimeType,
                    "data" => $base64Image
                ]
            ];
        }

        $ragResponse = Http::timeout(60)->withHeaders([
            'Content-Type' => 'application/json'
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
            "contents" => [["parts" => $dssParts]],
            "generationConfig" => ["responseMimeType" => "application/json"]
        ]);

        if (!$ragResponse->successful()) {
            Log::error("Gemini Analysis Error: " . $ragResponse->body());
            return response()->json(['error' => 'Gagal menganalisis keputusan gizi.'], 500);
        }

        $resultJsonString = $ragResponse->json('candidates.0.content.parts.0.text');
        $decisionData = json_decode($resultJsonString, true);

        if (!$decisionData) {
            return response()->json(['error' => 'Gagal mengurai respons keputusan AI.'], 500);
        }

        // ==========================================
        // Tahap 5: Simpan ke Database
        // ==========================================
        $photoUrl = null;
        if ($hasImage) {
            $path = $request->file('image')->store('public/food_logs');
            $photoUrl = asset('storage/' . str_replace('public/', '', $path));
        }

        $dataResponse = [
            'target_type' => $request->target_type,
            'target_id' => $request->target_id,
            'photo_url' => $photoUrl,
            'food_name_detected' => $decisionData['food_name_detected'] ?? $ingredientsString,
            'notes' => $decisionData['notes'] ?? '',
            'recommendation_status' => $decisionData['recommendation_status'] ?? 'PERHATIAN',
            'calories_kcal' => round($totalCalories, 2),
            'protein_g' => round($totalProtein, 2),
            'fat_g' => round($totalFat, 2),
            'carbs_g' => round($totalCarbs, 2),
            'fiber_g' => round($totalFiber, 2),
            'iron_mg' => round($totalIron, 2),
            'calcium_mg' => round($totalCalcium, 2),
        ];

        return response()->json([
            'message' => 'Analisis berhasil',
            'data' => $dataResponse
        ], 201);
    }
}



// tes automation deployment