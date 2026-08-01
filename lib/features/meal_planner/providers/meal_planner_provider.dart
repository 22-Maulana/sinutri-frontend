import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../core/constants/api_constants.dart';
import '../models/meal_plan.dart';
import '../models/meal_planner_state.dart';

final mealPlannerProvider = StateNotifierProvider<MealPlannerNotifier, MealPlannerState>((ref) {
  return MealPlannerNotifier();
});

class MealPlannerNotifier extends StateNotifier<MealPlannerState> {
  MealPlannerNotifier() : super(MealPlannerState());

  Future<void> generateMealPlan({
    double? budget,
    String? stockIngredients,
    String? preferences,
  }) async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? prefs.getString('auth_token');

      if (token == null || token.isEmpty) {
        throw Exception('Token tidak ditemukan');
      }

      final response = await http.post(
        Uri.parse(ApiConstants.mealPlannerGenerate),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          if (budget != null) 'budget': budget,
          if (stockIngredients != null) 'available_ingredients': stockIngredients.split(',').map((e) => e.trim()).toList(),
          if (preferences != null) 'food_preferences': preferences.split(',').map((e) => e.trim()).toList(),
        }),
      );

      if (response.statusCode == 201 || response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final plan = MealPlan.fromJson(data['data']['meal_plan']);
        
        state = state.copyWith(
          isLoading: false,
          currentPlan: plan,
        );
      } else {
        try {
          final errorData = jsonDecode(response.body);
          final msg = errorData['message'] ?? errorData['error'] ?? 'Gagal membuat meal plan (Status ${response.statusCode})';
          throw msg;
        } catch (e) {
          if (e is String) rethrow;
          throw 'Gagal membuat meal plan (Status ${response.statusCode})';
        }
      }
    } catch (e) {
      final cleanMsg = e.toString().replaceAll('Exception: ', '');
      state = state.copyWith(
        isLoading: false,
        error: cleanMsg,
      );
    }
  }

  Future<void> saveMealPlan() async {
    if (state.currentPlan == null) return;

    // Meal plan sudah otomatis tersimpan saat generate
    // Cukup reload list untuk update UI
    await loadSavedPlans();
  }

  Future<void> loadSavedPlans() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? prefs.getString('auth_token');

      if (token == null || token.isEmpty) {
        throw Exception('Token tidak ditemukan');
      }

      final response = await http.get(
        Uri.parse(ApiConstants.mealPlanner),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final plans = (data['data'] as List)
            .map((json) => MealPlan.fromJson(json))
            .toList();
        
        state = state.copyWith(savedPlans: plans);
      }
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  void clearError() {
    state = state.copyWith(error: null);
  }
}
