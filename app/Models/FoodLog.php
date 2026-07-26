<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'user_id', 'meal_time', 'meal_type', 'photo_url', 
    'food_name_detected', 'portion_grams', 'notes', 'recommendation_status', 
    'calories_kcal', 'protein_g', 'fat_g', 'carbs_g', 'sugar_g', 'fiber_g',
    'glycemic_index', 'glycemic_score', 'risk_category',
    'ai_insight', 'ai_recommendation', 'alternative_foods'
])]
class FoodLog extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'meal_time' => 'datetime',
            'alternative_foods' => 'array',
            'calories_kcal' => 'float',
            'protein_g' => 'float',
            'fat_g' => 'float',
            'carbs_g' => 'float',
            'sugar_g' => 'float',
            'fiber_g' => 'float',
            'glycemic_index' => 'float',
            'glycemic_score' => 'float',
            'portion_grams' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
