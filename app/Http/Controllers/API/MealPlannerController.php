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
        set_time_limit(180);

        $validator = Validator::make($request->all(), [
            'plan_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'available_ingredients' => 'nullable|array',
            'food_preferences' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            return response()->json(['error' => 'Harap lengkapi profil kesehatan terlebih dahulu.'], 422);
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

        $profileInfo = "Nama: {$profile->name}, Usia: {$profile->age} tahun, Gender: " . ($profile->gender === 'L' ? 'Laki-laki' : 'Perempuan') . ", ";
        $profileInfo .= "Berat: {$profile->weight_kg} kg, Tinggi: {$profile->height_cm} cm, BMI: {$profile->bmi}, ";
        $profileInfo .= "Status Diabetes: {$diabetesStatusMap[$profile->diabetes_status]}, ";
        $profileInfo .= "Riwayat Keluarga DM: " . ($profile->family_diabetes_history ? 'Ya' : 'Tidak') . ". ";

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

KEBUTUHAN NUTRISI HARIAN (berdasarkan kalkulasi):
- Kalori: {$dailyTargets['calories']} kkal
- Karbohidrat: {$dailyTargets['carbs']} g
- Protein: {$dailyTargets['protein']} g
- Lemak: {$dailyTargets['fat']} g
- Serat: {$dailyTargets['fiber']} g
- Gula (maksimal): {$dailyTargets['sugar']} g

PREFERENSI PENGGUNA:
{$budgetInfo}
{$ingredientsInfo}
{$preferencesInfo}

TASK:
Buatkan meal plan harian (breakfast, lunch, dinner, snack) yang:
1. Menggunakan pangan lokal Indonesia
2. Mengutamakan makanan dengan Indeks Glikemik rendah-sedang
3. Sesuai dengan profil diabetes pengguna
4. Memenuhi target nutrisi harian
5. Hindari bahan yang termasuk alergi pengguna
6. Sesuaikan dengan budget dan bahan yang tersedia (jika ada)

Untuk SETIAP ITEM makanan, berikan:
- food_name (nama makanan Indonesia)
- portion_grams (berat dalam gram)
- calories (kalori)
- carbs (karbohidrat dalam gram)
- protein (protein dalam gram)
- fat (lemak dalam gram)
- fiber (serat dalam gram)
- sugar (gula dalam gram)
- estimated_cost (estimasi harga dalam Rupiah)

Return ONLY a JSON object:
{
  \"breakfast_items\": [{\"food_name\": \"string\", \"portion_grams\": number, \"calories\": number, \"carbs\": number, \"protein\": number, \"fat\": number, \"fiber\": number, \"sugar\": number, \"estimated_cost\": number}],
  \"lunch_items\": [...],
  \"dinner_items\": [...],
  \"snack_items\": [...],
  \"ai_insight\": \"string (2-3 kalimat menjelaskan mengapa meal plan ini cocok untuk pengguna)\"
}";

        $response = Http::timeout(90)->withHeaders([
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

        if (!$response->successful()) {
            Log::error("Gemini Meal Planner Error: " . $response->body());
            return response()->json(['error' => 'Gagal membuat meal plan dengan AI.'], 500);
        }

        $resultText = $response->json('candidates.0.content.parts.0.text');
        $mealPlanData = json_decode($resultText, true);

        if (!$mealPlanData) {
            return response()->json(['error' => 'Gagal mengurai respons AI.'], 500);
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
