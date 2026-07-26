<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\FoodLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class HealthReportController extends Controller
{
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            return response()->json(['error' => 'Profile tidak ditemukan'], 404);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $duration = $startDate->diffInDays($endDate) + 1;

        // Get all food logs within the period
        $logs = FoodLog::where('user_id', $user->id)
            ->whereDate('meal_time', '>=', $startDate)
            ->whereDate('meal_time', '<=', $endDate)
            ->orderBy('meal_time', 'asc')
            ->get();

        if ($logs->isEmpty()) {
            return response()->json(['error' => 'Tidak ada data konsumsi pada periode ini'], 404);
        }

        $totalMenus = $logs->count();

        // Calculate nutritional summary
        $totalCalories = $logs->sum('calories_kcal');
        $totalCarbs = $logs->sum('carbs_g');
        $totalSugar = $logs->sum('sugar_g');
        $totalProtein = $logs->sum('protein_g');
        $totalFat = $logs->sum('fat_g');
        $totalFiber = $logs->sum('fiber_g');

        $avgCalories = $totalCalories / $duration;
        $avgCarbs = $totalCarbs / $duration;
        $avgSugar = $totalSugar / $duration;
        $avgProtein = $totalProtein / $duration;
        $avgFat = $totalFat / $duration;
        $avgFiber = $totalFiber / $duration;

        // Calculate glycemic risk distribution
        $lowRisk = $logs->where('risk_category', 'low')->count();
        $mediumRisk = $logs->where('risk_category', 'medium')->count();
        $highRisk = $logs->where('risk_category', 'high')->count();

        $totalWithRisk = $lowRisk + $mediumRisk + $highRisk;
        $avgGlycemicScore = 0;

        if ($totalWithRisk > 0) {
            $totalGS = 0;
            foreach ($logs as $log) {
                if ($log->glycemic_score && $log->glycemic_score > 0) {
                    $totalGS += $log->glycemic_score;
                }
            }
            $avgGlycemicScore = $totalGS / $totalWithRisk;
        }

        // Group logs by date for daily sugar consumption chart
        $dailySugarData = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();
            $dayLogs = $logs->filter(function($log) use ($dateString) {
                return Carbon::parse($log->meal_time)->toDateString() === $dateString;
            });
            $daySugar = $dayLogs->sum('sugar_g');
            $dailySugarData[] = [
                'date' => $dateString,
                'sugar' => round($daySugar, 2)
            ];
        }

        // Macronutrient distribution
        $macroDistribution = [
            'calories' => round($avgCalories, 2),
            'carbs' => round($avgCarbs, 2),
            'protein' => round($avgProtein, 2),
            'fat' => round($avgFat, 2),
            'fiber' => round($avgFiber, 2),
        ];

        // Glycemic score trend (by date)
        $glycemicTrend = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();
            $dayLogs = $logs->filter(function($log) use ($dateString) {
                return Carbon::parse($log->meal_time)->toDateString() === $dateString;
            });

            $dayGS = 0;
            $countGS = 0;
            foreach ($dayLogs as $log) {
                if ($log->glycemic_score && $log->glycemic_score > 0) {
                    $dayGS += $log->glycemic_score;
                    $countGS++;
                }
            }
            $avgDayGS = $countGS > 0 ? round($dayGS / $countGS, 1) : 0;

            $glycemicTrend[] = [
                'date' => $dateString,
                'glycemic_score' => $avgDayGS
            ];
        }

        // Count active days
        $uniqueDays = $logs->map(function($log) {
            return Carbon::parse($log->meal_time)->toDateString();
        })->unique()->count();

        // Meal history
        $mealHistory = $logs->map(function($log) {
            return [
                'date' => Carbon::parse($log->meal_time)->toDateString(),
                'time' => Carbon::parse($log->meal_time)->format('H:i'),
                'meal_type' => $log->meal_type,
                'food_name' => $log->food_name_detected,
                'calories' => round($log->calories_kcal, 2),
                'carbs' => round($log->carbs_g, 2),
                'sugar' => round($log->sugar_g, 2),
                'risk_category' => $log->risk_category ?? 'low',
            ];
        });

        // Generate AI Insight & Recommendation
        $aiAnalysis = $this->generateAIAnalysis($profile, $startDate, $endDate, $duration, $avgCalories, $avgCarbs, $avgSugar, $avgFiber, $avgGlycemicScore, $lowRisk, $mediumRisk, $highRisk, $totalMenus);

        $reportData = [
            'user_info' => [
                'name' => $profile->name,
                'age' => $profile->age,
                'gender' => $profile->gender === 'L' ? 'Laki-laki' : 'Perempuan',
                'height_cm' => $profile->height_cm,
                'weight_kg' => $profile->weight_kg,
                'bmi' => $profile->bmi,
                'diabetes_status' => $profile->diabetes_status,
            ],
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'duration_days' => $duration,
                'total_menus' => $totalMenus,
            ],
            'nutrition_summary' => [
                'avg_calories' => round($avgCalories, 2),
                'avg_carbs' => round($avgCarbs, 2),
                'avg_sugar' => round($avgSugar, 2),
                'avg_protein' => round($avgProtein, 2),
                'avg_fat' => round($avgFat, 2),
                'avg_fiber' => round($avgFiber, 2),
            ],
            'glycemic_summary' => [
                'avg_glycemic_score' => round($avgGlycemicScore, 2),
                'low_risk_count' => $lowRisk,
                'medium_risk_count' => $mediumRisk,
                'high_risk_count' => $highRisk,
            ],
            'meal_history' => $mealHistory,
            'charts' => [
                'daily_sugar_consumption' => $dailySugarData,
                'macronutrient_distribution' => $macroDistribution,
                'glycemic_score_trend' => $glycemicTrend,
            ],
            'activity_summary' => [
                'total_scans' => $totalMenus,
                'active_days' => $uniqueDays,
            ],
            'ai_insight' => $aiAnalysis['insight'],
            'ai_recommendation' => $aiAnalysis['recommendation'],
        ];

        return response()->json([
            'message' => 'Health report generated successfully',
            'data' => $reportData
        ]);
    }

    private function generateAIAnalysis($profile, $startDate, $endDate, $duration, $avgCalories, $avgCarbs, $avgSugar, $avgFiber, $avgGlycemicScore, $lowRisk, $mediumRisk, $highRisk, $totalMenus)
    {
        $geminiKey = env('GEMINI_API_KEY');

        $diabetesStatusMap = [
            'dm_type_1' => 'Diabetes Mellitus Tipe 1',
            'dm_type_2' => 'Diabetes Mellitus Tipe 2',
            'prediabetes' => 'Prediabetes',
            'not_diagnosed' => 'Belum Terdiagnosis DM'
        ];

        $prompt = "Anda adalah ahli gizi spesialis Diabetes Mellitus.

PROFIL PENGGUNA:
Nama: {$profile->name}
Usia: {$profile->age} tahun
Status Diabetes: {$diabetesStatusMap[$profile->diabetes_status]}
BMI: {$profile->bmi}

PERIODE LAPORAN:
{$startDate->format('d M Y')} - {$endDate->format('d M Y')} ({$duration} hari)
Total menu tercatat: {$totalMenus}

RINGKASAN KONSUMSI RATA-RATA HARIAN:
- Kalori: " . round($avgCalories, 0) . " kkal
- Karbohidrat: " . round($avgCarbs, 1) . " g
- Gula: " . round($avgSugar, 1) . " g
- Serat: " . round($avgFiber, 1) . " g
- Rata-rata Glycemic Score: " . round($avgGlycemicScore, 1) . "

DISTRIBUSI RISIKO GLIKEMIK:
- Rendah: {$lowRisk} menu
- Sedang: {$mediumRisk} menu
- Tinggi: {$highRisk} menu

TASK:
1. Berikan AI Insight (3-4 kalimat) dalam Bahasa Indonesia yang menganalisis pola konsumsi selama periode ini, identifikasi kelebihan dan kekurangan.
2. Berikan AI Recommendation (3-4 kalimat) berupa saran perbaikan pola makan yang spesifik dan dapat diterapkan.

Return ONLY a JSON object:
{
  \"ai_insight\": \"string\",
  \"ai_recommendation\": \"string\"
}";

        try {
            $response = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $prompt]
                        ]
                    ]
                ],
                "generationConfig" => ["responseMimeType" => "application/json"]
            ]);

            if ($response->successful()) {
                $aiText = $response->json('candidates.0.content.parts.0.text');
                $aiData = json_decode($aiText, true);
                if ($aiData) {
                    return [
                        'insight' => $aiData['ai_insight'] ?? '',
                        'recommendation' => $aiData['ai_recommendation'] ?? '',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("Health Report AI Error: " . $e->getMessage());
        }

        return [
            'insight' => 'Pola konsumsi Anda selama periode ini sudah cukup baik.',
            'recommendation' => 'Terus jaga pola makan sehat dan seimbang.'
        ];
    }

    public function exportPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate report data (reuse generate logic)
        $reportResponse = $this->generate($request);
        
        if ($reportResponse->status() !== 200) {
            return $reportResponse;
        }

        $reportData = $reportResponse->getData(true)['data'];
        
        // Generate PDF
        $pdf = Pdf::loadView('reports.health-report', ['data' => $reportData]);
        
        $filename = 'health-report-' . $reportData['period']['start_date'] . '-to-' . $reportData['period']['end_date'] . '.pdf';
        
        return $pdf->download($filename);
    }
}
