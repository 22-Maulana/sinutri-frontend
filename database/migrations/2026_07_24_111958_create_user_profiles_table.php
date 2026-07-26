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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            
            // Step 1 - Data Dasar
            $table->string('name');
            $table->integer('age');
            $table->enum('gender', ['L', 'P']); // L=Laki-laki, P=Perempuan
            $table->float('height_cm');
            $table->float('weight_kg');
            $table->float('bmi')->nullable(); // Dihitung otomatis
            
            // Step 2 - Kondisi Kesehatan
            $table->enum('diabetes_status', [
                'dm_type_1', 
                'dm_type_2', 
                'prediabetes', 
                'not_diagnosed'
            ])->default('not_diagnosed');
            $table->boolean('family_diabetes_history')->default(false);
            $table->boolean('hypertension')->nullable();
            $table->json('food_allergies')->nullable(); // Array of allergies
            
            // Step 3 - Target Kesehatan (multiple selection)
            $table->json('health_targets')->nullable(); // Array of targets
            
            $table->timestamps();
            
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
