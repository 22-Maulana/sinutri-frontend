class ScanResponseModel {
  final String foodName;
  final String portionDesc;
  final String suggestionNote;
  final String recommendationStatus;
  final String reasoning;
  final String aiInsight;
  final String aiRecommendation;
  final List<String> ingredients;
  final List<Map<String, String>> alternativeFoods;
  final double glycemicIndex;
  final double glycemicScore;
  final String riskCategory;
  final String? photoUrl;
  
  final double calories;
  final int caloriesAkg;
  final double protein;
  final int proteinAkg;
  final double carbs;
  final int carbsAkg;
  final double fat;
  final int fatAkg;

  final Map<String, double> micronutrients;

  ScanResponseModel({
    required this.foodName,
    required this.portionDesc,
    required this.suggestionNote,
    required this.recommendationStatus,
    required this.reasoning,
    this.aiInsight = '',
    this.aiRecommendation = '',
    this.ingredients = const [],
    this.alternativeFoods = const [],
    this.glycemicIndex = 0.0,
    this.glycemicScore = 0.0,
    this.riskCategory = 'low',
    this.photoUrl,
    required this.calories,
    required this.caloriesAkg,
    required this.protein,
    required this.proteinAkg,
    required this.carbs,
    required this.carbsAkg,
    required this.fat,
    required this.fatAkg,
    required this.micronutrients,
  });
}
