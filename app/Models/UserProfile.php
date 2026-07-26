<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'user_id', 'name', 'age', 'gender', 'height_cm', 'weight_kg', 'bmi',
    'diabetes_status', 'family_diabetes_history', 'hypertension', 
    'food_allergies', 'health_targets'
])]
class UserProfile extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'family_diabetes_history' => 'boolean',
            'hypertension' => 'boolean',
            'food_allergies' => 'array',
            'health_targets' => 'array',
            'bmi' => 'float',
            'height_cm' => 'float',
            'weight_kg' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Auto calculate BMI
    public function calculateBMI()
    {
        if ($this->height_cm > 0 && $this->weight_kg > 0) {
            $heightInMeters = $this->height_cm / 100;
            $this->bmi = round($this->weight_kg / ($heightInMeters * $heightInMeters), 2);
        }
        return $this->bmi;
    }
}
