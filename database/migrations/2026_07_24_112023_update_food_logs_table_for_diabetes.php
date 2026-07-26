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
        Schema::table('food_logs', function (Blueprint $table) {
            // Drop kolom yang tidak diperlukan untuk sistem diabetes
            $table->dropColumn(['target_type', 'target_id', 'iron_mg', 'calcium_mg']);
            
            // Tambah kolom untuk diabetes mellitus system
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack'])->after('meal_time');
            $table->float('sugar_g')->default(0)->after('carbs_g');
            $table->float('portion_grams')->nullable()->after('food_name_detected');
            
            // Glycemic Analysis
            $table->float('glycemic_index')->nullable()->after('fiber_g');
            $table->float('glycemic_score')->nullable()->after('glycemic_index');
            $table->enum('risk_category', ['low', 'medium', 'high'])->nullable()->after('glycemic_score');
            
            // AI Analysis
            $table->text('ai_insight')->nullable()->after('risk_category');
            $table->text('ai_recommendation')->nullable()->after('ai_insight');
            $table->json('alternative_foods')->nullable()->after('ai_recommendation'); // Daftar alternatif makanan
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('food_logs', function (Blueprint $table) {
            // Kembalikan kolom lama
            $table->enum('target_type', ['MOTHER', 'CHILD'])->after('user_id');
            $table->uuid('target_id')->after('target_type');
            $table->float('iron_mg')->default(0)->after('fiber_g');
            $table->float('calcium_mg')->default(0)->after('iron_mg');
            
            // Hapus kolom diabetes
            $table->dropColumn([
                'meal_type', 'sugar_g', 'portion_grams',
                'glycemic_index', 'glycemic_score', 'risk_category',
                'ai_insight', 'ai_recommendation', 'alternative_foods'
            ]);
        });
    }
};
