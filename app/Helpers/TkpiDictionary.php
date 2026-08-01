<?php

namespace App\Helpers;

class TkpiDictionary
{
    private static $database = [
        'nasi pecel' => ['kalori' => 380, 'karbo' => 54, 'protein' => 12, 'lemak' => 14, 'serat' => 4.5, 'gula' => 3.2, 'gi' => 55],
        'nasi goreng' => ['kalori' => 520, 'karbo' => 68, 'protein' => 14, 'lemak' => 22, 'serat' => 2.1, 'gula' => 2.5, 'gi' => 68],
        'nasi putih' => ['kalori' => 180, 'karbo' => 40, 'protein' => 3.0, 'lemak' => 0.4, 'serat' => 0.5, 'gula' => 0.1, 'gi' => 72],
        'nasi merah' => ['kalori' => 110, 'karbo' => 23, 'protein' => 2.6, 'lemak' => 0.9, 'serat' => 1.8, 'gula' => 0.2, 'gi' => 50],
        'ayam goreng' => ['kalori' => 260, 'karbo' => 4.0, 'protein' => 25, 'lemak' => 16, 'serat' => 0.0, 'gula' => 0.0, 'gi' => 30],
        'ayam bakar' => ['kalori' => 210, 'karbo' => 2.5, 'protein' => 27, 'lemak' => 10, 'serat' => 0.0, 'gula' => 1.0, 'gi' => 25],
        'telur dadar' => ['kalori' => 150, 'karbo' => 1.0, 'protein' => 10, 'lemak' => 12, 'serat' => 0.0, 'gula' => 0.2, 'gi' => 0],
        'telur rebus' => ['kalori' => 78, 'karbo' => 0.6, 'protein' => 6.3, 'lemak' => 5.3, 'serat' => 0.0, 'gula' => 0.2, 'gi' => 0],
        'tempe goreng' => ['kalori' => 120, 'karbo' => 7.0, 'protein' => 8.0, 'lemak' => 7.5, 'serat' => 1.4, 'gula' => 0.5, 'gi' => 35],
        'tempe bacem' => ['kalori' => 110, 'karbo' => 9.0, 'protein' => 7.5, 'lemak' => 4.5, 'serat' => 1.2, 'gula' => 2.0, 'gi' => 40],
        'tahu goreng' => ['kalori' => 90, 'karbo' => 3.5, 'protein' => 7.0, 'lemak' => 5.5, 'serat' => 0.8, 'gula' => 0.2, 'gi' => 30],
        'sayur bening' => ['kalori' => 45, 'karbo' => 7.5, 'protein' => 2.5, 'lemak' => 0.5, 'serat' => 2.8, 'gula' => 1.2, 'gi' => 25],
        'tumis kangkung' => ['kalori' => 65, 'karbo' => 5.0, 'protein' => 3.0, 'lemak' => 4.0, 'serat' => 2.2, 'gula' => 0.8, 'gi' => 30],
        'gado gado' => ['kalori' => 320, 'karbo' => 38, 'protein' => 14, 'lemak' => 14, 'serat' => 5.2, 'gula' => 4.0, 'gi' => 48],
        'soto ayam' => ['kalori' => 280, 'karbo' => 22, 'protein' => 20, 'lemak' => 12, 'serat' => 1.8, 'gula' => 1.5, 'gi' => 45],
        'sop iga' => ['kalori' => 350, 'karbo' => 12, 'protein' => 28, 'lemak' => 21, 'serat' => 1.5, 'gula' => 0.8, 'gi' => 35],
        'alpukat' => ['kalori' => 160, 'karbo' => 8.5, 'protein' => 2.0, 'lemak' => 14.7, 'serat' => 6.7, 'gula' => 0.7, 'gi' => 15],
        'apel' => ['kalori' => 95, 'karbo' => 25, 'protein' => 0.5, 'lemak' => 0.3, 'serat' => 4.4, 'gula' => 19.0, 'gi' => 36],
        'pisang' => ['kalori' => 105, 'karbo' => 27, 'protein' => 1.3, 'lemak' => 0.3, 'serat' => 3.1, 'gula' => 14.4, 'gi' => 51],
    ];

    /**
     * Search dictionary for ingredient or food name
     */
    public static function lookup($queryText)
    {
        $cleanQuery = strtolower(trim($queryText));
        
        foreach (self::$database as $key => $values) {
            if (str_contains($cleanQuery, $key) || str_contains($key, $cleanQuery)) {
                return array_merge(['food_name' => ucwords($key)], $values);
            }
        }

        // Generic fallback values per 100g if not matched
        return [
            'food_name' => ucwords($queryText),
            'kalori' => 150,
            'karbo' => 22,
            'protein' => 8,
            'lemak' => 5,
            'serat' => 2.0,
            'gula' => 1.0,
            'gi' => 45,
        ];
    }
}
