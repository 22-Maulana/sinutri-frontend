class MealPlan {
  final String id;
  final String date;
  final List<MealItem> breakfast;
  final List<MealItem> lunch;
  final List<MealItem> dinner;
  final List<MealItem> snacks;
  final NutritionSummary totalNutrition;
  final String aiInsight;

  MealPlan({
    required this.id,
    required this.date,
    required this.breakfast,
    required this.lunch,
    required this.dinner,
    required this.snacks,
    required this.totalNutrition,
    required this.aiInsight,
  });

  factory MealPlan.fromJson(Map<String, dynamic> json) {
    return MealPlan(
      id: json['id']?.toString() ?? '',
      date: json['plan_date'] ?? '',
      breakfast: (json['breakfast_items'] as List?)?.map((e) => MealItem.fromJson(e)).toList() ?? [],
      lunch: (json['lunch_items'] as List?)?.map((e) => MealItem.fromJson(e)).toList() ?? [],
      dinner: (json['dinner_items'] as List?)?.map((e) => MealItem.fromJson(e)).toList() ?? [],
      snacks: (json['snack_items'] as List?)?.map((e) => MealItem.fromJson(e)).toList() ?? [],
      totalNutrition: NutritionSummary(
        calories: (json['total_calories'] ?? 0).toDouble(),
        carbs: (json['total_carbs'] ?? 0).toDouble(),
        protein: (json['total_protein'] ?? 0).toDouble(),
        fat: (json['total_fat'] ?? 0).toDouble(),
        fiber: (json['total_fiber'] ?? 0).toDouble(),
      ),
      aiInsight: json['ai_insight'] ?? '',
    );
  }
}

class MealItem {
  final String name;
  final String portion;
  final double calories;
  final double carbs;
  final double protein;
  final double fat;
  final double fiber;
  final double estimatedCost;

  MealItem({
    required this.name,
    required this.portion,
    required this.calories,
    required this.carbs,
    required this.protein,
    required this.fat,
    required this.fiber,
    required this.estimatedCost,
  });

  factory MealItem.fromJson(Map<String, dynamic> json) {
    return MealItem(
      name: json['food_name'] ?? '',
      portion: '${json['portion_grams'] ?? 0} gram',
      calories: (json['calories'] ?? 0).toDouble(),
      carbs: (json['carbs'] ?? 0).toDouble(),
      protein: (json['protein'] ?? 0).toDouble(),
      fat: (json['fat'] ?? 0).toDouble(),
      fiber: (json['fiber'] ?? 0).toDouble(),
      estimatedCost: (json['estimated_cost'] ?? 0).toDouble(),
    );
  }
}

class NutritionSummary {
  final double calories;
  final double carbs;
  final double protein;
  final double fat;
  final double fiber;

  NutritionSummary({
    required this.calories,
    required this.carbs,
    required this.protein,
    required this.fat,
    required this.fiber,
  });

  factory NutritionSummary.fromJson(Map<String, dynamic> json) {
    return NutritionSummary(
      calories: (json['calories'] ?? 0).toDouble(),
      carbs: (json['carbs'] ?? 0).toDouble(),
      protein: (json['protein'] ?? 0).toDouble(),
      fat: (json['fat'] ?? 0).toDouble(),
      fiber: (json['fiber'] ?? 0).toDouble(),
    );
  }
}
