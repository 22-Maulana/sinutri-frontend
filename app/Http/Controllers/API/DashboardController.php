<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\FoodLog;
use App\Models\MealPlan;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            return response()->json(['error' => 'Profile tidak ditemukan'], 404);
        }

        $date = $request->input('date', Carbon::today()->toDateString());

        // Get today's food logs
        $logs = FoodLog::where('user_id', $user->id)
            ->whereDate('meal_time', $date)
            ->orderBy('meal_time', 'asc')
            ->get();

        $totalCalories = $logs->sum('calories_kcal');
        $totalCarbs = $logs->sum('carbs_g');
        $totalSugar = $logs->sum('sugar_g');
        $totalProtein = $logs->sum('protein_g');
        $totalFat = $logs->sum('fat_g');
        $totalFiber = $logs->sum('fiber_g');

        // Calculate average glycemic score
        $totalGlycemicScore = 0;
        $countWithGS = 0;
        foreach ($logs as $log) {
            if ($log->glycemic_score && $log->glycemic_score > 0) {
                $totalGlycemicScore += $log->glycemic_score;
                $countWithGS++;
            }
        }
        $avgGlycemicScore = $countWithGS > 0 ? round($totalGlycemicScore / $countWithGS, 1) : 0;

        // Calculate daily targets based on profile
        $dailyTargets = $this->calculateDailyTargets($profile);

        // Get AI Daily Insight
        $aiInsight = $this->generateDailyInsight($user, $profile, $logs, $totalCalories, $totalCarbs, $totalSugar, $avgGlycemicScore, $dailyTargets);

        // Get today's meal plan if exists
        $mealPlan = MealPlan::where('user_id', $user->id)
            ->whereDate('plan_date', $date)
            ->first();

        return response()->json([
            'message' => 'Dashboard summary',
            'date' => $date,
            'profile' => [
                'name' => $profile->name,
                'age' => $profile->age,
                'bmi' => $profile->bmi,
                'diabetes_status' => $profile->diabetes_status,
            ],
            'today_summary' => [
                'calories' => round($totalCalories, 2),
                'carbs' => round($totalCarbs, 2),
                'sugar' => round($totalSugar, 2),
                'protein' => round($totalProtein, 2),
                'fat' => round($totalFat, 2),
                'fiber' => round($totalFiber, 2),
                'glycemic_score' => $avgGlycemicScore,
            ],
            'daily_targets' => $dailyTargets,
            'recent_meals' => $logs->take(5),
            'ai_daily_insight' => $aiInsight,
            'meal_plan' => $mealPlan,
        ]);
    }

    public function weeklyProgress(Request $request)
    {
        $user = $request->user();
        $endDate = Carbon::parse($request->input('end_date', Carbon::today()->toDateString()));
        $startDate = $endDate->copy()->subDays(6);

        $logs = FoodLog::where('user_id', $user->id)
            ->whereDate('meal_time', '>=', $startDate)
            ->whereDate('meal_time', '<=', $endDate)
            ->orderBy('meal_time', 'asc')
            ->get();

        $weeklyData = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();
            $dayLogs = $logs->filter(function($log) use ($dateString) {
                return Carbon::parse($log->meal_time)->toDateString() === $dateString;
            });

            $dayCalories = $dayLogs->sum('calories_kcal');
            $dayCarbs = $dayLogs->sum('carbs_g');
            $daySugar = $dayLogs->sum('sugar_g');
            $dayFiber = $dayLogs->sum('fiber_g');

            $totalGS = 0;
            $countGS = 0;
            foreach ($dayLogs as $log) {
                if ($log->glycemic_score && $log->glycemic_score > 0) {
                    $totalGS += $log->glycemic_score;
                    $countGS++;
                }
            }
            $avgGS = $countGS > 0 ? round($totalGS / $countGS, 1) : 0;

            $weeklyData[] = [
                'date' => $dateString,
                'day_name' => $date->format('D'),
                'calories' => round($dayCalories, 2),
                'carbs' => round($dayCarbs, 2),
                'sugar' => round($daySugar, 2),
                'fiber' => round($dayFiber, 2),
                'glycemic_score' => $avgGS,
            ];
        }

        return response()->json([
            'message' => 'Weekly progress',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => $weeklyData,
        ]);
    }

    private function calculateDailyTargets($profile)
    {
        // Basic Harris-Benedict equation for BMR
        $gender = $profile->gender;
        $age = $profile->age;
        $weight = $profile->weight_kg;
        $height = $profile->height_cm;

        if ($gender === 'L') {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        } else {
            $bmr = 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);
        }

        // Activity factor (moderate activity)
        $dailyCalories = $bmr * 1.55;

        // Macronutrient distribution for diabetes
        // Carbs: 45-60% of calories (using 50%)
        // Protein: 15-20% (using 20%)
        // Fat: 20-35% (using 30%)

        $carbsCalories = $dailyCalories * 0.50;
        $proteinCalories = $dailyCalories * 0.20;
        $fatCalories = $dailyCalories * 0.30;

        $carbsGrams = $carbsCalories / 4; // 4 cal per gram
        $proteinGrams = $proteinCalories / 4;
        $fatGrams = $fatCalories / 9; // 9 cal per gram

        // Fiber: 25-30g for women, 30-35g for men
        $fiberGrams = $gender === 'L' ? 32 : 27;

        // Sugar: max 10% of total calories (WHO recommendation)
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

    private function generateDailyInsight($user, $profile, $logs, $totalCalories, $totalCarbs, $totalSugar, $avgGlycemicScore, $dailyTargets)
    {
        if ($logs->isEmpty()) {
            return "Belum ada data konsumsi makanan hari ini. Mulai scan makanan untuk mendapatkan insight yang dipersonalisasi.";
        }

        $geminiKey = env('GEMINI_API_KEY');

        $diabetesStatusMap = [
            'dm_type_1' => 'Diabetes Mellitus Tipe 1',
            'dm_type_2' => 'Diabetes Mellitus Tipe 2',
            'prediabetes' => 'Prediabetes',
            'not_diagnosed' => 'Belum Terdiagnosis DM'
        ];

        $mealList = $logs->map(function($log) {
            return $log->food_name_detected . " ({$log->meal_type})";
        })->implode(', ');

        $prompt = "Anda adalah ahli gizi spesialis Diabetes Mellitus.

PROFIL PENGGUNA:
Nama: {$profile->name}
Usia: {$profile->age} tahun
Status Diabetes: {$diabetesStatusMap[$profile->diabetes_status]}
BMI: {$profile->bmi}

KONSUMSI HARI INI:
Makanan: {$mealList}
Total Kalori: {$totalCalories} kkal (Target: {$dailyTargets['calories']} kkal)
Total Karbohidrat: {$totalCarbs} g (Target: {$dailyTargets['carbs']} g)
Total Gula: {$totalSugar} g (Target: {$dailyTargets['sugar']} g)
Rata-rata Glycemic Score: {$avgGlycemicScore}

TASK:
Berikan AI Daily Insight dalam Bahasa Indonesia (2-3 kalimat singkat) yang memberikan motivasi dan saran praktis untuk pengguna berdasarkan data konsumsi hari ini.

Return ONLY plain text (no JSON, no markdown).";

        $response = Http::timeout(30)->withHeaders([
            'Content-Type' => 'application/json'
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ]);

        if ($response->successful()) {
            $text = $response->json('candidates.0.content.parts.0.text');
            return trim($text);
        }

        return "Konsumsi makanan hari ini sudah cukup baik. Terus jaga pola makan sehat dan seimbang.";
    }
}
