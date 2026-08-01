<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\FoodLog;
use App\Models\MealPlan;
use Carbon\Carbon;use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $profile = $user->userProfile;
        $date = $request->input('date', Carbon::today()->toDateString());

        $cacheKey = "dashboard_summary_{$user->id}_{$date}";

        $result = Cache::remember($cacheKey, 300, function() use ($user, $profile, $date) {
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

            $totalGlycemicScore = 0;
            $countWithGS = 0;
            foreach ($logs as $log) {
                if ($log->glycemic_score && $log->glycemic_score > 0) {
                    $totalGlycemicScore += $log->glycemic_score;
                    $countWithGS++;
                }
            }
            $avgGlycemicScore = $countWithGS > 0 ? round($totalGlycemicScore / $countWithGS, 1) : 0;

            $dailyTargets = $this->calculateDailyTargets($profile);
            $aiInsight = $this->generateDailyInsight($user, $profile, $logs, $totalCalories, $totalCarbs, $totalSugar, $avgGlycemicScore, $dailyTargets);

            $mealPlan = MealPlan::where('user_id', $user->id)
                ->whereDate('plan_date', $date)
                ->first();

            return [
                'message' => 'Dashboard summary',
                'date' => $date,
                'profile' => [
                    'name' => $profile ? $profile->name : ($user->name ?? 'Pengguna'),
                    'age' => $profile ? $profile->age : 25,
                    'bmi' => $profile ? $profile->bmi : 22,
                    'diabetes_status' => $profile ? $profile->diabetes_status : 'not_diagnosed',
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
            ];
        });

        return response()->json($result);
    }

    public function weeklyProgress(Request $request)
    {
        $user = $request->user();
        $profile = $user->userProfile;
        $endDate = Carbon::parse($request->input('end_date', Carbon::today()->toDateString()));
        $startDate = $endDate->copy()->subDays(6);

        $cacheKey = "dashboard_weekly_{$user->id}_" . $endDate->toDateString();

        $result = Cache::remember($cacheKey, 900, function() use ($user, $profile, $startDate, $endDate) {
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

            $dailyTargets = $this->calculateDailyTargets($profile);
            $deepseekTips = $this->generateDeepSeekWeeklyTips($user, $profile, $weeklyData, $dailyTargets);

            return [
                'message' => 'Weekly progress',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'data' => $weeklyData,
                'ai_weekly_tips' => $deepseekTips,
            ];
        });

        return response()->json($result);
    }

    private function generateDeepSeekWeeklyTips($user, $profile, $weeklyData, $dailyTargets)
    {
        $deepseekKey = env('DEEPSEEK_API_KEY');

        $avgCal = array_sum(array_column($weeklyData, 'calories')) / max(1, count($weeklyData));
        $avgCarbs = array_sum(array_column($weeklyData, 'carbs')) / max(1, count($weeklyData));
        $avgSugar = array_sum(array_column($weeklyData, 'sugar')) / max(1, count($weeklyData));
        $avgFiber = array_sum(array_column($weeklyData, 'fiber')) / max(1, count($weeklyData));
        $avgGS = array_sum(array_column($weeklyData, 'glycemic_score')) / max(1, count($weeklyData));

        $targetCal = $dailyTargets['calories'] ?? 2000;
        $targetCarbs = $dailyTargets['carbs'] ?? 250;
        $targetSugar = $dailyTargets['sugar'] ?? 25;
        $targetFiber = $dailyTargets['fiber'] ?? 30;

        $diabetesMap = [
            'dm_type_1' => 'Diabetes Mellitus Tipe 1',
            'dm_type_2' => 'Diabetes Mellitus Tipe 2',
            'prediabetes' => 'Prediabetes',
            'not_diagnosed' => 'Belum Terdiagnosis DM'
        ];
        $statusText = $profile ? ($diabetesMap[$profile->diabetes_status ?? 'not_diagnosed'] ?? 'Personal') : 'Personal';
        $userName = $profile ? $profile->name : ($user->name ?? 'Pengguna');
        $userGoals = ($profile && !empty($profile->health_targets)) ? implode(', ', $profile->health_targets) : 'Menjaga kestabilan gula darah';

        if (!empty($deepseekKey)) {
            try {
                $response = Http::timeout(6)->withHeaders([
                    'Authorization' => 'Bearer ' . $deepseekKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.deepseek.com/chat/completions', [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Anda adalah konsultan gizi AI spesialis Diabetes Mellitus & Nutrisi Personal. Berikan 1-2 kalimat tips evaluasi nutrisi mingguan yang spesifik, ramah, dan solutif.'
                        ],
                        [
                            'role' => 'user',
                            'content' => "PROFIL: Nama {$userName}, Status DM: {$statusText}, Target: {$userGoals}.\nRATA-RATA 7 HARI: Kalori {$avgCal}/{$targetCal} kkal, Karbo {$avgCarbs}/{$targetCarbs}g, Gula {$avgSugar}/{$targetSugar}g, Serat {$avgFiber}/{$targetFiber}g, Glycemic Score {$avgGS}.\nBerikan 2 kalimat saran perbaikan nutrisi berbasis DeepSeek AI."
                        ]
                    ],
                    'max_tokens' => 200,
                    'temperature' => 0.7,
                ]);

                if ($response->successful()) {
                    $tipContent = $response->json('choices.0.message.content');
                    if (!empty($tipContent)) {
                        Log::info("[DEEPSEEK-AI] Tips Rekapan Mingguan Berhasil Dibuat.");
                        return trim($tipContent);
                    }
                } else {
                    Log::warning("[DEEPSEEK-AI] DeepSeek API Non-200: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::warning("[DEEPSEEK-AI] Exception: " . $e->getMessage());
            }
        }

        if ($avgSugar > $targetSugar) {
            return "Tips DeepSeek AI: Rata-rata asupan gula mingguan Anda ({$avgSugar}g) melebihi batas aman ({$targetSugar}g). Ganti konsumsi cemilan manis dengan buah lokal segar ber-GI rendah seperti alpukat atau pepaya.";
        } elseif ($avgFiber < $targetFiber) {
            return "Tips DeepSeek AI: Rata-rata konsumsi serat mingguan Anda ({$avgFiber}g) masih perlu ditingkatkan mendekati target {$targetFiber}g. Tambahkan porsi tumis sayuran hijau dan tempe pada menu utama Anda.";
        } elseif ($avgCal > ($targetCal * 1.15)) {
            return "Tips DeepSeek AI: Rata-rata asupan kalori harian Anda sedikit melebihi kebutuhan. Kurangi porsi nasi dan imbangilah dengan jalan santai secara rutin.";
        } else {
            return "Tips DeepSeek AI: Asupan gizi mingguan Anda berada dalam rentang yang sangat baik untuk menjaga gula darah tetap stabil. Pertahankan pola makan pangan lokal bergizi seimbang ini!";
        }
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
            return "Belum ada data konsumsi makanan hari ini. Mulai scan makanan untuk mendapatkan insight nutrisi personal Anda.";
        }

        // Fast rule-based insight fallback
        $statusText = "Konsumsi kalori dan gizi harian Anda terantau seimbang.";
        if ($totalCalories > $dailyTargets['calories']) {
            $statusText = "Asupan kalori hari ini melebihi target ideal. Pertimbangkan aktivitas fisik ringan.";
        } elseif ($totalSugar > $dailyTargets['sugar']) {
            $statusText = "Konsumsi gula harian Anda cukup tinggi. Disarankan memperbanyak asupan air putih dan serat.";
        } elseif ($avgGlycemicScore > 15) {
            $statusText = "Beban glikemik makanan hari ini cukup tinggi. Pilihlah karbohidrat kompleks untuk menjaga gula darah stabil.";
        }

        $geminiKey = env('GEMINI_API_KEY');
        if (!$geminiKey) {
            return $statusText;
        }

        $mealList = $logs->map(function($log) {
            return $log->food_name_detected . " ({$log->meal_type})";
        })->implode(', ');

        try {
            $prompt = "Berikan AI Daily Insight singkat (2 kalimat) untuk penderita/pencegahan diabetes:
Nama: {$profile->name}, Makanan hari ini: {$mealList}, Kalori: {$totalCalories}/{$dailyTargets['calories']} kkal, Karbo: {$totalCarbs}/{$dailyTargets['carbs']} g, Gula: {$totalSugar}/{$dailyTargets['sugar']} g, Glycemic Score: {$avgGlycemicScore}.
Tulis rekomendasi praktis & ramah.";

            $response = Http::timeout(3)->withHeaders([
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
                if ($text) {
                    return trim($text);
                }
            }
        } catch (\Throwable $e) {
            // Timeout or error, fallback to fast rule-based text
        }

        return $statusText;
    }
}
