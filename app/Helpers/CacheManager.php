<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheManager
{
    /**
     * Invalidate all user-specific caches when food log, profile, or meal plan changes.
     */
    public static function invalidateUserCache($userId)
    {
        if (!$userId) return;

        $today = date('Y-m-d');
        Cache::forget("user_profile_{$userId}");
        Cache::forget("dashboard_summary_{$userId}_{$today}");
        Cache::forget("dashboard_weekly_{$userId}_{$today}");
        Cache::forget("today_menu_{$userId}_{$today}");
        Cache::forget("meal_plans_{$userId}");
    }
}
