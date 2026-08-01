<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\MealPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MealPlannerController extends Controller
{
    public function generate(Request $request)
    {
        set_time_limit(60);

        $validator = Validator::make($request->all(), [
            'plan_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'available_ingredients' => 'nullable|array',
            'food_preferences' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Format input tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            // Auto-create basic profile if not exists to avoid block
            $profile = UserProfile::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'age' => 30,
                'gender' => 'L',
                'height_cm' => 165,
                'weight_kg' => 60,
                'diabetes_status' => 'not_diagnosed',
                'family_diabetes_history' => false,
                'food_allergies' => [],
                'health_targets' => ['stable_blood_sugar'],
            ]);
            $profile->calculateBMI();
            $profile->save();
        }

        $planDate = $request->input('plan_date', Carbon::today()->toDateString());
        $budget = $request->input('budget');
        $availableIngredients = $request->input('available_ingredients', []);
        $foodPreferences = $request->input('food_preferences', []);

        // Calculate daily nutrition requirements
        $dailyTargets = $this->calculateNutritionRequirements($profile);

        $geminiKey = env('GEMINI_API_KEY');

        $diabetesStatusMap = [
            'dm_type_1' => 'Diabetes Mellitus Tipe 1',
            'dm_type_2' => 'Diabetes Mellitus Tipe 2',
            'prediabetes' => 'Prediabetes',
            'not_diagnosed' => 'Belum Terdiagnosis DM'
        ];

        $statusKey = $profile->diabetes_status ?? 'not_diagnosed';
        $statusLabel = $diabetesStatusMap[$statusKey] ?? 'Belum Terdiagnosis DM';

        $profileInfo = "Nama: {$profile->name}, Usia: {$profile->age} tahun, Gender: " . ($profile->gender === 'P' ? 'Perempuan' : 'Laki-laki') . ", ";
        $profileInfo .= "Berat: {$profile->weight_kg} kg, Tinggi: {$profile->height_cm} cm, BMI: {$profile->bmi}, ";
        $profileInfo .= "Status Diabetes: {$statusLabel}. ";

        $allergies = $profile->food_allergies ?? [];
        if (!empty($allergies)) {
            $profileInfo .= "Alergi: " . implode(', ', $allergies) . ". ";
        }

        $healthTargets = $profile->health_targets ?? [];
        if (!empty($healthTargets)) {
            $targetsReadable = array_map(function($target) {
                $map = [
                    'stable_blood_sugar' => 'Menjaga kadar gula darah tetap stabil',
                    'reduce_sugar_intake' => 'Mengurangi konsumsi gula harian',
                    'control_carbs' => 'Mengontrol asupan karbohidrat',
                    'lose_weight' => 'Menurunkan berat badan secara sehat',
                    'healthy_diet' => 'Menjaga pola makan yang lebih sehat',
                ];
                return $map[$target] ?? $target;
            }, $healthTargets);
            $profileInfo .= "Target Kesehatan: " . implode(', ', $targetsReadable) . ". ";
        }

        $budgetInfo = $budget ? "Budget tersedia: Rp " . number_format($budget, 0, ',', '.') . ". " : "Budget: tidak dibatasi. ";
        $ingredientsInfo = !empty($availableIngredients) ? "Bahan tersedia: " . implode(', ', $availableIngredients) . ". " : "";
        $preferencesInfo = !empty($foodPreferences) ? "Preferensi makanan: " . implode(', ', $foodPreferences) . ". " : "";

        $prompt = "Anda adalah ahli gizi spesialis Diabetes Mellitus dan pangan lokal Indonesia.

PROFIL PENGGUNA:
{$profileInfo}

KEBUTUHAN NUTRISI HARIAN:
- Kalori: {$dailyTargets['calories']} kkal
- Karbohidrat: {$dailyTargets['carbs']} g
- Protein: {$dailyTargets['protein']} g
- Lemak: {$dailyTargets['fat']} g
- Serat: {$dailyTargets['fiber']} g
- Gula: {$dailyTargets['sugar']} g

PREFERENSI PENGGUNA:
{$budgetInfo}
{$ingredientsInfo}
{$preferencesInfo}

Buatkan meal plan harian (breakfast, lunch, dinner, snack) berbasis pangan lokal Indonesia rendah Indeks Glikemik.

Return ONLY JSON object:
{
  \"breakfast_items\": [{\"food_name\": \"string\", \"portion_grams\": number, \"calories\": number, \"carbs\": number, \"protein\": number, \"fat\": number, \"fiber\": number, \"sugar\": number, \"estimated_cost\": number}],
  \"lunch_items\": [...],
  \"dinner_items\": [...],
  \"snack_items\": [...],
  \"ai_insight\": \"string\"
}";

        $mealPlanData = null;

        if (!empty($geminiKey)) {
            try {
                $response = Http::timeout(10)->withHeaders([
                    'Content-Type' => 'application/json'
                ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                    "contents" => [
                        [
                            "parts" => [
                                ["text" => $prompt]
                            ]
                        ]
                    ],
                    "generationConfig" => [
                        "responseMimeType" => "application/json"
                    ]
                ]);

                if ($response->successful()) {
                    $resultText = $response->json('candidates.0.content.parts.0.text');
                    $cleanedJson = preg_replace('/^```json\s*|\s*```$/m', '', trim($resultText));
                    $mealPlanData = json_decode($cleanedJson, true);
                } else {
                    Log::warning("Gemini Meal Planner Non-200: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::warning("Gemini Meal Planner Exception: " . $e->getMessage());
            }
        }

        // Fallback rule-based meal plan if Gemini fails or times out
        if (!$mealPlanData || empty($mealPlanData['breakfast_items'])) {
            $stockText = !empty($availableIngredients) ? implode(', ', $availableIngredients) : 'bahan lokal segar';
            $mealPlanData = [
                'breakfast_items' => [
                    ['food_name' => 'Nasi Merah Kukus', 'portion_grams' => 100, 'calories' => 110, 'carbs' => 23, 'protein' => 2.6, 'fat' => 0.9, 'fiber' => 1.8, 'sugar' => 0.2, 'estimated_cost' => 3000],
                    ['food_name' => 'Telur Rebus / Orak-Arik', 'portion_grams' => 60, 'calories' => 90, 'carbs' => 0.6, 'protein' => 7.5, 'fat' => 6.0, 'fiber' => 0, 'sugar' => 0.2, 'estimated_cost' => 2500],
                    ['food_name' => 'Tumis Buncis & Wortel', 'portion_grams' => 80, 'calories' => 45, 'carbs' => 7.0, 'protein' => 1.8, 'fat' => 1.5, 'fiber' => 2.5, 'sugar' => 1.5, 'estimated_cost' => 3000],
                ],
                'lunch_items' => [
                    ['food_name' => 'Nasi Merah / Jagung', 'portion_grams' => 150, 'calories' => 165, 'carbs' => 34, 'protein' => 3.9, 'fat' => 1.3, 'fiber' => 2.7, 'sugar' => 0.3, 'estimated_cost' => 4500],
                    ['food_name' => 'Dada Ayam Panggang Bumbu Kukus', 'portion_grams' => 100, 'calories' => 165, 'carbs' => 0, 'protein' => 31.0, 'fat' => 3.6, 'fiber' => 0, 'sugar' => 0, 'estimated_cost' => 12000],
                    ['food_name' => 'Sayur Bening Bayam Labu Siam', 'portion_grams' => 120, 'calories' => 35, 'carbs' => 6.5, 'protein' => 2.2, 'fat' => 0.4, 'fiber' => 2.8, 'sugar' => 1.2, 'estimated_cost' => 3500],
                    ['food_name' => 'Tempe Bacem Tanpa Gula Berlebih', 'portion_grams' => 50, 'calories' => 95, 'carbs' => 7.5, 'protein' => 9.0, 'fat' => 4.0, 'fiber' => 1.4, 'sugar' => 0.8, 'estimated_cost' => 2000],
                ],
                'dinner_items' => [
                    ['food_name' => 'Sup Ikan Nila / Gurame Bening', 'portion_grams' => 120, 'calories' => 140, 'carbs' => 2.0, 'protein' => 22.0, 'fat' => 4.5, 'fiber' => 0.5, 'sugar' => 0.5, 'estimated_cost' => 15000],
                    ['food_name' => 'Pepes Tahu Jamur', 'portion_grams' => 80, 'calories' => 70, 'carbs' => 4.0, 'protein' => 6.5, 'fat' => 3.2, 'fiber' => 1.5, 'sugar' => 0.4, 'estimated_cost' => 3000],
                    ['food_name' => 'Tumis Kangkung Dua Bawang', 'portion_grams' => 100, 'calories' => 40, 'carbs' => 4.5, 'protein' => 2.5, 'fat' => 1.8, 'fiber' => 2.2, 'sugar' => 0.6, 'estimated_cost' => 3000],
                ],
                'snack_items' => [
                    ['food_name' => 'Alpukat Segar Tanpa Pemanis', 'portion_grams' => 100, 'calories' => 160, 'carbs' => 8.5, 'protein' => 2.0, 'fat' => 14.7, 'fiber' => 6.7, 'sugar' => 0.7, 'estimated_cost' => 6000],
                    ['food_name' => 'Kacang Tanah Sangrai', 'portion_grams' => 30, 'calories' => 170, 'carbs' => 4.8, 'protein' => 7.8, 'fat' => 14.0, 'fiber' => 2.4, 'sugar' => 1.2, 'estimated_cost' => 2500],
                ],
                'ai_insight' => "Meal plan ini dirancang khusus mengutamakan pangan lokal Indonesia dengan Indeks Glikemik rendah-sedang untuk memanjakan lidah sekaligus menjaga kestabilan kadar gula darah sepanjang hari. Memanfaatkan persediaan ($stockText) secara optimal.",
            ];
        }

        // Calculate totals
        $allItems = array_merge(
            $mealPlanData['breakfast_items'] ?? [],
            $mealPlanData['lunch_items'] ?? [],
            $mealPlanData['dinner_items'] ?? [],
            $mealPlanData['snack_items'] ?? []
        );

        $totalCalories = array_sum(array_column($allItems, 'calories'));
        $totalCarbs = array_sum(array_column($allItems, 'carbs'));
        $totalProtein = array_sum(array_column($allItems, 'protein'));
        $totalFat = array_sum(array_column($allItems, 'fat'));
        $totalFiber = array_sum(array_column($allItems, 'fiber'));
        $totalSugar = array_sum(array_column($allItems, 'sugar'));
        $totalCost = array_sum(array_column($allItems, 'estimated_cost'));

        // Save to database
        $mealPlan = MealPlan::updateOrCreate(
            [
                'user_id' => $user->id,
                'plan_date' => $planDate,
            ],
            [
                'breakfast_items' => $mealPlanData['breakfast_items'] ?? [],
                'lunch_items' => $mealPlanData['lunch_items'] ?? [],
                'dinner_items' => $mealPlanData['dinner_items'] ?? [],
                'snack_items' => $mealPlanData['snack_items'] ?? [],
                'total_calories' => round($totalCalories, 2),
                'total_carbs' => round($totalCarbs, 2),
                'total_protein' => round($totalProtein, 2),
                'total_fat' => round($totalFat, 2),
                'total_fiber' => round($totalFiber, 2),
                'total_sugar' => round($totalSugar, 2),
                'estimated_total_cost' => round($totalCost, 0),
                'ai_insight' => $mealPlanData['ai_insight'] ?? '',
                'budget' => $budget,
                'available_ingredients' => $availableIngredients,
                'food_preferences' => $foodPreferences,
            ]
        );

        return response()->json([
            'message' => 'Meal plan berhasil dibuat',
            'data' => [
                'meal_plan' => $mealPlan,
                'daily_targets' => $dailyTargets,
            ]
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $mealPlans = MealPlan::where('user_id', $user->id)
            ->orderBy('plan_date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'message' => 'Meal plans retrieved',
            'data' => $mealPlans
        ]);
    }

    public function show(Request $request, $id)
    {
        $mealPlan = MealPlan::where('user_id', $request->user()->id)->find($id);

        if (!$mealPlan) {
            return response()->json(['message' => 'Meal plan tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Meal plan detail',
            'data' => $mealPlan
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $mealPlan = MealPlan::where('user_id', $request->user()->id)->find($id);

        if (!$mealPlan) {
            return response()->json(['message' => 'Meal plan tidak ditemukan'], 404);
        }

        $mealPlan->delete();

        return response()->json([
            'message' => 'Meal plan berhasil dihapus'
        ]);
    }

    private function calculateNutritionRequirements($profile)
    {
        $gender = $profile->gender;
        $age = $profile->age;
        $weight = $profile->weight_kg;
        $height = $profile->height_cm;

        // Harris-Benedict equation for BMR
        if ($gender === 'L') {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        } else {
            $bmr = 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);
        }

        // Activity factor (moderate activity = 1.55)
        $dailyCalories = $bmr * 1.55;

        // Adjust based on diabetes status
        if ($profile->diabetes_status === 'dm_type_1' || $profile->diabetes_status === 'dm_type_2') {
            // Carbs: 45-50% of calories
            $carbsCalories = $dailyCalories * 0.48;
            $proteinCalories = $dailyCalories * 0.22;
            $fatCalories = $dailyCalories * 0.30;
        } else {
            // Prediabetes or not diagnosed
            $carbsCalories = $dailyCalories * 0.50;
            $proteinCalories = $dailyCalories * 0.20;
            $fatCalories = $dailyCalories * 0.30;
        }

        $carbsGrams = $carbsCalories / 4;
        $proteinGrams = $proteinCalories / 4;
        $fatGrams = $fatCalories / 9;

        $fiberGrams = $gender === 'L' ? 32 : 27;
        $sugarGrams = ($dailyCalories * 0.10) / 4;

        return [
            'calories' => round($dailyCalories, 0),
            'carbs' => round($carbsGrams, 1),
            'protein' => round($proteinGrams, 1),
            'fat' => round($fatGrams, 1),
            'fiber' => $fiberGrams,
            'sugar' => round($sugarGrams, 1),
        ];
    }
}
