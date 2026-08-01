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
        if (!Schema::hasTable('meal_plans')) {
            Schema::create('meal_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->date('date');
                $table->json('breakfast');
                $table->json('lunch');
                $table->json('dinner');
                $table->json('snacks');
                $table->decimal('total_calories', 8, 2);
                $table->decimal('total_carbs', 8, 2);
                $table->decimal('total_protein', 8, 2);
                $table->decimal('total_fat', 8, 2);
                $table->decimal('total_fiber', 8, 2);
                $table->text('ai_insight');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plans');
    }
};
