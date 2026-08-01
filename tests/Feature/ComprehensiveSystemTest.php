<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\FoodLog;
use App\Models\MealPlan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class ComprehensiveSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Create an active user with profile
        $this->user = User::create([
            'name' => 'System Tester',
            'email' => 'systemtest@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'user',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('auth_token')->plainTextToken;

        UserProfile::create([
            'user_id' => $this->user->id,
            'name' => 'System Tester',
            'age' => 30,
            'gender' => 'L',
            'height_cm' => 175.0,
            'weight_kg' => 70.0,
            'bmi' => 22.86,
            'diabetes_status' => 'dm_type_2',
            'family_diabetes_history' => true,
            'hypertension' => false,
            'food_allergies' => ['Seafood'],
            'health_targets' => ['stable_blood_sugar', 'reduce_sugar_intake'],
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 1. AUTHENTICATION & PROFILING FLOW                                          */
    /* -------------------------------------------------------------------------- */

    public function test_01_user_registration_and_activation_flow()
    {
        // Step 1: Register
        $regResponse = $this->postJson('/api/register', [
            'name' => 'Flow User',
            'email' => 'flowuser@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $regResponse->assertStatus(201)
                    ->assertJson(['requires_activation' => true]);

        // Step 2: Verify OTP
        $otpUser = User::where('email', 'flowuser@example.com')->first();
        $this->assertNotNull($otpUser);

        $verifyResponse = $this->postJson('/api/verify-otp', [
            'email' => 'flowuser@example.com',
            'otp_code' => $otpUser->otp_code,
        ]);

        $verifyResponse->assertStatus(200)
                       ->assertJsonStructure(['token', 'user']);

        // Step 3: Login
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'flowuser@example.com',
            'password' => 'Secret123!',
        ]);

        $loginResponse->assertStatus(200)
                      ->assertJsonStructure(['token', 'user']);
    }

    public function test_02_profiling_three_steps()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];

        // Step 1: Basic Data
        $step1 = $this->postJson('/api/profile/basic-data', [
            'name' => 'System Tester Updated',
            'age' => 32,
            'gender' => 'L',
            'height_cm' => 178,
            'weight_kg' => 72,
        ], $headers);

        $step1->assertStatus(200)
              ->assertJsonPath('data.bmi', 22.72);

        // Step 2: Health Condition
        $step2 = $this->putJson('/api/profile/health-condition', [
            'diabetes_status' => 'prediabetes',
            'family_diabetes_history' => false,
            'hypertension' => true,
            'food_allergies' => ['Kacang'],
        ], $headers);

        $step2->assertStatus(200)
              ->assertJsonPath('data.diabetes_status', 'prediabetes');

        // Step 3: Health Targets
        $step3 = $this->putJson('/api/profile/health-targets', [
            'health_targets' => ['stable_blood_sugar', 'control_carbs'],
        ], $headers);

        $step3->assertStatus(200);

        // Get Profile
        $getProfile = $this->getJson('/api/profile', $headers);
        $getProfile->assertStatus(200)
                   ->assertJsonPath('data.name', 'System Tester');
    }

    /* -------------------------------------------------------------------------- */
    /* 2. FOOD LOG MANAGEMENT                                                     */
    /* -------------------------------------------------------------------------- */

    public function test_03_food_logs_crud_and_today_menu()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];

        // 1. Create Food Log
        $createLog = $this->postJson('/api/food-logs', [
            'meal_time' => now()->toDateTimeString(),
            'meal_type' => 'lunch',
            'food_name_detected' => 'Nasi Merah & Ayam Bakar',
            'portion_grams' => 200,
            'recommendation_status' => 'RECOMMENDED',
            'calories_kcal' => 350.0,
            'carbs_g' => 45.0,
            'sugar_g' => 2.0,
            'protein_g' => 25.0,
            'fat_g' => 8.0,
            'fiber_g' => 5.0,
            'glycemic_index' => 55,
            'glycemic_score' => 24.75,
            'risk_category' => 'medium',
            'notes' => 'Makan siang sehat',
        ], $headers);

        $createLog->assertStatus(201);
        $logId = $createLog->json('data.id');

        // 2. Get All Food Logs
        $getLogs = $this->getJson('/api/food-logs', $headers);
        $getLogs->assertStatus(200);

        // 3. Get Today Menu
        $todayMenu = $this->getJson('/api/food-logs/today', $headers);
        $todayMenu->assertStatus(200);

        // 4. Show Single Log
        $showLog = $this->getJson('/api/food-logs/' . $logId, $headers);
        $showLog->assertStatus(200)
                ->assertJsonPath('data.food_name_detected', 'Nasi Merah & Ayam Bakar');

        // 5. Update Food Log
        $updateLog = $this->putJson('/api/food-logs/' . $logId, [
            'notes' => 'Diubah catatan makan siang',
        ], $headers);
        $updateLog->assertStatus(200);

        // 6. Delete Food Log
        $deleteLog = $this->deleteJson('/api/food-logs/' . $logId, [], $headers);
        $deleteLog->assertStatus(200);
    }

    /* -------------------------------------------------------------------------- */
    /* 3. DASHBOARD MONITORING & NUTRITION METRICS                               */
    /* -------------------------------------------------------------------------- */

    public function test_04_dashboard_summary_and_weekly_progress()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];

        // Seed a sample log for today
        FoodLog::create([
            'user_id' => $this->user->id,
            'meal_time' => now(),
            'meal_type' => 'breakfast',
            'food_name_detected' => 'Salad Sayur',
            'portion_grams' => 150,
            'recommendation_status' => 'RECOMMENDED',
            'calories_kcal' => 120,
            'carbs_g' => 15,
            'sugar_g' => 3,
            'protein_g' => 4,
            'fat_g' => 5,
            'fiber_g' => 6,
            'glycemic_index' => 30,
            'glycemic_score' => 4.5,
            'risk_category' => 'low',
        ]);

        // Dashboard Summary
        $summary = $this->getJson('/api/dashboard/summary', $headers);
        $summary->assertStatus(200)
                ->assertJsonStructure(['today_summary' => ['calories', 'carbs', 'sugar', 'protein', 'fat', 'fiber']]);

        // Weekly Progress
        $weekly = $this->getJson('/api/dashboard/weekly', $headers);
        $weekly->assertStatus(200)
               ->assertJsonStructure(['data']);
    }

    /* -------------------------------------------------------------------------- */
    /* 4. AI MEAL PLANNER                                                          */
    /* -------------------------------------------------------------------------- */

    public function test_05_meal_planner_crud()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];

        // Create a MealPlan
        $plan = MealPlan::create([
            'user_id' => $this->user->id,
            'plan_date' => now()->toDateString(),
            'breakfast_items' => [['food_name' => 'Oatmeal', 'calories' => 200]],
            'lunch_items' => [['food_name' => 'Ayam Kukus', 'calories' => 350]],
            'dinner_items' => [['food_name' => 'Sup Sayur', 'calories' => 150]],
            'snack_items' => [['food_name' => 'Apel', 'calories' => 80]],
            'total_calories' => 780,
            'total_carbs' => 95,
            'total_protein' => 50,
            'total_fat' => 20,
            'total_fiber' => 15,
            'total_sugar' => 12,
            'ai_insight' => 'Menu seimbang untuk penderita diabetes',
        ]);

        // Index
        $index = $this->getJson('/api/meal-planner', $headers);
        $index->assertStatus(200);

        // Show
        $show = $this->getJson('/api/meal-planner/' . $plan->id, $headers);
        $show->assertStatus(200)
             ->assertJsonPath('data.ai_insight', 'Menu seimbang untuk penderita diabetes');

        // Delete
        $delete = $this->deleteJson('/api/meal-planner/' . $plan->id, [], $headers);
        $delete->assertStatus(200);
    }

    /* -------------------------------------------------------------------------- */
    /* 5. HEALTH REPORT GENERATION & PDF EXPORT                                  */
    /* -------------------------------------------------------------------------- */

    public function test_06_health_report_and_pdf_export()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];

        // Seed some food logs
        FoodLog::create([
            'user_id' => $this->user->id,
            'meal_time' => now(),
            'meal_type' => 'breakfast',
            'food_name_detected' => 'Bubur Manado',
            'portion_grams' => 250,
            'recommendation_status' => 'LIMIT',
            'calories_kcal' => 280,
            'carbs_g' => 40,
            'sugar_g' => 2,
            'protein_g' => 10,
            'fat_g' => 6,
            'fiber_g' => 4,
            'glycemic_index' => 65,
            'glycemic_score' => 26,
            'risk_category' => 'medium',
        ]);

        // Generate Report
        $report = $this->postJson('/api/health-report/generate', [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ], $headers);

        $report->assertStatus(200);

        // Export PDF
        $pdf = $this->postJson('/api/health-report/export-pdf', [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ], $headers);

        $pdf->assertStatus(200);
    }

    /* -------------------------------------------------------------------------- */
    /* 6. NUTRIBOT AI CHATBOT WITH RAG                                             */
    /* -------------------------------------------------------------------------- */

    public function test_07_nutribot_chatbot_interaction()
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Penderita diabetes disarankan mengonsumsi makanan berindeks glikemik rendah seperti nasi merah dan sayuran.']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $headers = ['Authorization' => 'Bearer ' . $this->token];

        $response = $this->postJson('/api/chatbot', [
            'message' => 'Apa rekomendasi makanan untuk penderita diabetes tipe 2?',
        ], $headers);

        $response->assertStatus(200)
                 ->assertJsonStructure(['reply']);
    }
}
