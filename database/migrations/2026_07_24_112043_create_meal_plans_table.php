<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            
            $table->date('plan_date');
            
            // Meal Items (JSON structure for each meal type)
            $table->json('breakfast_items')->nullable(); // [{food_name, portion_grams, calories, protein, fat, carbs, fiber, sugar, estimated_cost}]
            $table->json('lunch_items')->nullable();
            $table->json('dinner_items')->nullable();
            $table->json('snack_items')->nullable();
            
            // Nutritional Summary
            $table->float('total_calories')->default(0);
            $table->float('total_carbs')->default(0);
            $table->float('total_protein')->default(0);
            $table->float('total_fat')->default(0);
            $table->float('total_fiber')->default(0);
            $table->float('total_sugar')->default(0);
            $table->float('estimated_total_cost')->nullable();
            
            // AI Analysis
            $table->text('ai_insight')->nullable();
            
            // User Input (optional)
            $table->float('budget')->nullable();
            $table->json('available_ingredients')->nullable();
            $table->json('food_preferences')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'plan_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plans');
    }
};
