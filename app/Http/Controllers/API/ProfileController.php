<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Step 1 - Store/Update Data Dasar
     */
    public function storeBasicData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'age' => 'required|integer|min:1|max:120',
            'gender' => 'required|in:L,P',
            'height_cm' => 'required|numeric|min:50|max:250',
            'weight_kg' => 'required|numeric|min:10|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $profile = $user->userProfile;

        $data = [
            'user_id' => $user->id,
            'name' => $request->name,
            'age' => $request->age,
            'gender' => $request->gender,
            'height_cm' => $request->height_cm,
            'weight_kg' => $request->weight_kg,
        ];

        if ($profile) {
            $profile->update($data);
        } else {
            $profile = UserProfile::create($data);
        }

        // Auto calculate BMI
        $profile->calculateBMI();
        $profile->save();

        return response()->json([
            'message' => 'Data dasar berhasil disimpan',
            'data' => $profile
        ], 200);
    }

    /**
     * Step 2 - Update Kondisi Kesehatan
     */
    public function updateHealthCondition(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'diabetes_status' => 'required|in:dm_type_1,dm_type_2,prediabetes,not_diagnosed',
            'family_diabetes_history' => 'required|boolean',
            'hypertension' => 'nullable|boolean',
            'food_allergies' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            return response()->json(['message' => 'Profile belum dibuat. Lengkapi data dasar terlebih dahulu.'], 404);
        }

        $profile->update([
            'diabetes_status' => $request->diabetes_status,
            'family_diabetes_history' => $request->family_diabetes_history,
            'hypertension' => $request->hypertension,
            'food_allergies' => $request->food_allergies ?? [],
        ]);

        return response()->json([
            'message' => 'Kondisi kesehatan berhasil disimpan',
            'data' => $profile
        ], 200);
    }

    /**
     * Step 3 - Update Target Kesehatan
     */
    public function updateHealthTargets(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'health_targets' => 'required|array|min:1',
            'health_targets.*' => 'in:stable_blood_sugar,reduce_sugar_intake,control_carbs,lose_weight,healthy_diet',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            return response()->json(['message' => 'Profile belum dibuat. Lengkapi data dasar terlebih dahulu.'], 404);
        }

        $profile->update([
            'health_targets' => $request->health_targets,
        ]);

        return response()->json([
            'message' => 'Target kesehatan berhasil disimpan',
            'data' => $profile
        ], 200);
    }

    /**
     * Get User Profile (All data)
     */
    public function getProfile(Request $request)
    {
        $user = $request->user()->load('userProfile');

        return response()->json([
            'message' => 'Profile retrieved successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile' => $user->userProfile,
            ]
        ]);
    }

    /**
     * Update Full Profile (untuk edit profile kemudian)
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:1|max:120',
            'gender' => 'nullable|in:L,P',
            'height_cm' => 'nullable|numeric|min:50|max:250',
            'weight_kg' => 'nullable|numeric|min:10|max:300',
            'diabetes_status' => 'nullable|in:dm_type_1,dm_type_2,prediabetes,not_diagnosed',
            'family_diabetes_history' => 'nullable|boolean',
            'hypertension' => 'nullable|boolean',
            'food_allergies' => 'nullable|array',
            'health_targets' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $profile = $user->userProfile;

        if (!$profile) {
            return response()->json(['message' => 'Profile tidak ditemukan'], 404);
        }

        $profile->update($request->only([
            'name', 'age', 'gender', 'height_cm', 'weight_kg',
            'diabetes_status', 'family_diabetes_history', 'hypertension',
            'food_allergies', 'health_targets'
        ]));

        // Recalculate BMI if weight or height changed
        if ($request->has('height_cm') || $request->has('weight_kg')) {
            $profile->calculateBMI();
            $profile->save();
        }

        return response()->json([
            'message' => 'Profile berhasil diupdate',
            'data' => $profile
        ], 200);
    }
}
