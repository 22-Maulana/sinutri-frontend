<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\FoodLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class FoodLogController extends Controller
{
    public function store(Request $request)
    {
        Log::info("[FOODLOG-BE] User konfirmasi 'Ya, Saya Makan Ini' -> Menyimpan FoodLog ke Database", [
            'user_id' => $request->user()?->id,
            'food_name' => $request->input('food_name_detected'),
            'calories' => $request->input('calories_kcal'),
            'meal_type' => $request->input('meal_type'),
        ]);

        $validator = Validator::make($request->all(), [
            'meal_time' => 'required|date',
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'food_name_detected' => 'required|string|max:255',
            'portion_grams' => 'nullable|numeric',
            'photo_url' => 'nullable|string',
            'recommendation_status' => 'required|string',
            'calories_kcal' => 'required|numeric',
            'carbs_g' => 'required|numeric',
            'sugar_g' => 'required|numeric',
            'protein_g' => 'required|numeric',
            'fat_g' => 'required|numeric',
            'fiber_g' => 'required|numeric',
            'glycemic_index' => 'nullable|numeric',
            'glycemic_score' => 'nullable|numeric',
            'risk_category' => 'nullable|in:low,medium,high',
            'ai_insight' => 'nullable|string',
            'ai_recommendation' => 'nullable|string',
            'alternative_foods' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $foodLog = FoodLog::create([
            'user_id' => $request->user()->id,
            'meal_time' => Carbon::parse($request->input('meal_time')),
            'meal_type' => $request->input('meal_type'),
            'photo_url' => $request->input('photo_url'),
            'food_name_detected' => $request->input('food_name_detected'),
            'portion_grams' => $request->input('portion_grams'),
            'notes' => $request->input('notes'),
            'recommendation_status' => $request->input('recommendation_status'),
            'calories_kcal' => $request->input('calories_kcal', 0),
            'protein_g' => $request->input('protein_g', 0),
            'fat_g' => $request->input('fat_g', 0),
            'carbs_g' => $request->input('carbs_g', 0),
            'sugar_g' => $request->input('sugar_g', 0),
            'fiber_g' => $request->input('fiber_g', 0),
            'glycemic_index' => $request->input('glycemic_index'),
            'glycemic_score' => $request->input('glycemic_score'),
            'risk_category' => $request->input('risk_category'),
            'ai_insight' => $request->input('ai_insight'),
            'ai_recommendation' => $request->input('ai_recommendation'),
            'alternative_foods' => $request->input('alternative_foods'),
        ]);

        return response()->json([
            'message' => 'Food log berhasil disimpan',
            'data' => $foodLog
        ], 201);
    }

    public function index(Request $request)
    {
        $query = FoodLog::where('user_id', $request->user()->id);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereDate('meal_time', '>=', $request->start_date)
                  ->whereDate('meal_time', '<=', $request->end_date);
        }

        $logs = $query->orderBy('meal_time', 'desc')->get();

        return response()->json([
            'message' => 'Food logs retrieved successfully',
            'data' => $logs
        ]);
    }

    public function show(Request $request, $id)
    {
        $log = FoodLog::where('user_id', $request->user()->id)->find($id);

        if (!$log) {
            return response()->json(['message' => 'Food log tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Food log detail',
            'data' => $log
        ]);
    }

    public function todayMenu(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        
        $logs = FoodLog::where('user_id', $request->user()->id)
            ->whereDate('meal_time', $date)
            ->orderBy('meal_time', 'asc')
            ->get();

        $totalCalories = $logs->sum('calories_kcal');
        $totalCarbs = $logs->sum('carbs_g');
        $totalSugar = $logs->sum('sugar_g');
        $totalProtein = $logs->sum('protein_g');
        $totalFat = $logs->sum('fat_g');
        $totalFiber = $logs->sum('fiber_g');

        return response()->json([
            'message' => 'Menu hari ini',
            'date' => $date,
            'meals' => $logs,
            'summary' => [
                'total_calories' => round($totalCalories, 2),
                'total_carbs' => round($totalCarbs, 2),
                'total_sugar' => round($totalSugar, 2),
                'total_protein' => round($totalProtein, 2),
                'total_fat' => round($totalFat, 2),
                'total_fiber' => round($totalFiber, 2),
            ]
        ]);
    }

    public function analyzeMenu(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'food_log_ids' => 'required|array|min:1',
            'food_log_ids.*' => 'uuid|exists:food_logs,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            return response()->json(['error' => 'Profile tidak ditemukan'], 404);
        }

        $logs = FoodLog::where('user_id', $user->id)
            ->whereIn('id', $request->food_log_ids)
            ->get();

        if ($logs->isEmpty()) {
            return response()->json(['error' => 'Tidak ada food log yang ditemukan'], 404);
        }

        $totalCalories = $logs->sum('calories_kcal');
        $totalCarbs = $logs->sum('carbs_g');
        $totalSugar = $logs->sum('sugar_g');
        $totalProtein = $logs->sum('protein_g');
        $totalFat = $logs->sum('fat_g');
        $totalFiber = $logs->sum('fiber_g');

        $totalGlycemicLoad = 0;
        $countWithGI = 0;
        foreach ($logs as $log) {
            if ($log->glycemic_score && $log->glycemic_score > 0) {
                $totalGlycemicLoad += $log->glycemic_score;
                $countWithGI++;
            }
        }

        $avgGlycemicScore = $countWithGI > 0 ? round($totalGlycemicLoad / $countWithGI, 1) : 0;

        $overallRisk = 'low';
        if ($avgGlycemicScore >= 20) {
            $overallRisk = 'high';
        } elseif ($avgGlycemicScore >= 11) {
            $overallRisk = 'medium';
        }

        $menuList = $logs->map(function($log) {
            return $log->food_name_detected . " ({$log->meal_type})";
        })->implode(', ');

        $diabetesStatusMap = [
            'dm_type_1' => 'Diabetes Mellitus Tipe 1',
            'dm_type_2' => 'Diabetes Mellitus Tipe 2',
            'prediabetes' => 'Prediabetes',
            'not_diagnosed' => 'Belum Terdiagnosis DM'
        ];

        $profileInfo = "Profil: {$profile->name}, Usia {$profile->age} tahun, Status DM: {$diabetesStatusMap[$profile->diabetes_status]}, BMI: {$profile->bmi}.";

        $geminiKey = env('GEMINI_API_KEY');

        $analysisPrompt = "Anda adalah ahli gizi spesialis Diabetes Mellitus.

USER PROFILE:
{$profileInfo}

MENU HARI INI:
{$menuList}

TOTAL NUTRISI:
- Kalori: {$totalCalories} kkal
- Karbohidrat: {$totalCarbs} g
- Gula: {$totalSugar} g
- Protein: {$totalProtein} g
- Lemak: {$totalFat} g
- Serat: {$totalFiber} g
- Rata-rata Glycemic Score: {$avgGlycemicScore}
- Kategori Risiko Glikemik: {$overallRisk}

TASK:
1. Berikan AI Insight (3-4 kalimat) dalam Bahasa Indonesia menjelaskan bagaimana menu ini berdampak pada gula darah dan kesehatan pengguna.
2. Berikan AI Recommendation (3-4 kalimat) berupa saran perbaikan menu atau pola makan untuk hari ini atau esok hari.

Return ONLY a JSON object:
{
  \"ai_insight\": \"string\",
  \"ai_recommendation\": \"string\"
}";

        $aiResponse = Http::timeout(60)->withHeaders([
            'Content-Type' => 'application/json'
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $analysisPrompt]
                    ]
                ]
            ],
            "generationConfig" => ["responseMimeType" => "application/json"]
        ]);

        $aiInsight = '';
        $aiRecommendation = '';

        if ($aiResponse->successful()) {
            $aiText = $aiResponse->json('candidates.0.content.parts.0.text');
            $aiData = json_decode($aiText, true);
            if ($aiData) {
                $aiInsight = $aiData['ai_insight'] ?? '';
                $aiRecommendation = $aiData['ai_recommendation'] ?? '';
            }
        }

        return response()->json([
            'message' => 'Analisis menu selesai',
            'data' => [
                'summary' => [
                    'total_calories' => round($totalCalories, 2),
                    'total_carbs' => round($totalCarbs, 2),
                    'total_sugar' => round($totalSugar, 2),
                    'total_protein' => round($totalProtein, 2),
                    'total_fat' => round($totalFat, 2),
                    'total_fiber' => round($totalFiber, 2),
                    'avg_glycemic_score' => $avgGlycemicScore,
                    'risk_category' => $overallRisk,
                ],
                'ai_insight' => $aiInsight,
                'ai_recommendation' => $aiRecommendation,
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $log = FoodLog::where('user_id', $request->user()->id)->find($id);

        if (!$log) {
            return response()->json(['message' => 'Food log tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $log->update([
            'notes' => $request->notes
        ]);

        return response()->json([
            'message' => 'Food log berhasil diupdate',
            'data' => $log
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $log = FoodLog::where('user_id', $request->user()->id)->find($id);

        if (!$log) {
            return response()->json(['message' => 'Food log tidak ditemukan'], 404);
        }

        $log->delete();

        return response()->json([
            'message' => 'Food log berhasil dihapus'
        ]);
    }
}
