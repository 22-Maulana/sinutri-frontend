<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'user_id', 'plan_date', 
    'breakfast_items', 'lunch_items', 'dinner_items', 'snack_items',
    'total_calories', 'total_carbs', 'total_protein', 'total_fat', 'total_fiber', 'total_sugar',
    'estimated_total_cost', 'ai_insight',
    'budget', 'available_ingredients', 'food_preferences'
])]
class MealPlan extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'breakfast_items' => 'array',
            'lunch_items' => 'array',
            'dinner_items' => 'array',
            'snack_items' => 'array',
            'available_ingredients' => 'array',
            'food_preferences' => 'array',
            'total_calories' => 'float',
            'total_carbs' => 'float',
            'total_protein' => 'float',
            'total_fat' => 'float',
            'total_fiber' => 'float',
            'total_sugar' => 'float',
            'estimated_total_cost' => 'float',
            'budget' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
