import 'dart:convert';
import 'package:collection/collection.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../core/constants/api_constants.dart';
import '../../profile/providers/profile_provider.dart';
import '../../profile/models/profile_state.dart';
import '../models/nutrition_graph_state.dart';

final nutritionGraphProvider = StateNotifierProvider.autoDispose<NutritionGraphNotifier, NutritionGraphState>((ref) {
  final profileState = ref.watch(profileProvider);
  return NutritionGraphNotifier(ref, profileState);
});

class NutritionGraphNotifier extends StateNotifier<NutritionGraphState> {
  final Ref _ref;
  final ProfileState _profileState;

  NutritionGraphNotifier(this._ref, this._profileState) : super(_initialState()) {
    // Initialize with first profile name if available
    if (_profileState.motherName != 'Loading...') {
      Future.microtask(() => _initializeFirstProfile());
    }
  }

  void _initializeFirstProfile() {
    state = state.copyWith(activeProfileName: 'Saya');
    fetchSummary();
  }

  static NutritionGraphState _initialState() {
    return NutritionGraphState(
      activeProfileName: 'Saya',
      activeTab: 'Harian',
      currentDateText: DateFormat('EEEE, d MMMM yyyy', 'id').format(DateTime.now()),
      caloryPercentage: 0,
      currentCalories: 0,
      targetCalories: 2550, // Default target
      remainingCalories: 2550,
      macros: {
        'Karbohidrat': MacroNutrientInfo(current: 0, target: 300, percentage: 0),
        'Protein': MacroNutrientInfo(current: 0, target: 75, percentage: 0),
        'Lemak': MacroNutrientInfo(current: 0, target: 60, percentage: 0),
        'Serat': MacroNutrientInfo(current: 0, target: 25, percentage: 0),
      },
      mealsTimeline: [],
      isLoading: false,
    );
  }

  Future<void> fetchSummary() async {
    state = state.copyWith(isLoading: true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? prefs.getString('auth_token') ?? '';

      String targetType = 'MOTHER';
      String targetId = _profileState.motherId;

      if (state.activeProfileName != 'Saya') {
        final child = _profileState.children.firstWhereOrNull(
          (c) => c.name == state.activeProfileName,
        );
        if (child != null) {
          targetType = 'CHILD';
          targetId = child.id;
        }
      }

      final queryParams = {
        'target_type': targetType,
        'target_id': targetId,
      };

      final uri = Uri.parse(ApiConstants.dashboardSummary).replace(queryParameters: queryParams);
      
      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        var decoded = jsonDecode(response.body);
        if (decoded is List && decoded.isNotEmpty) {
          decoded = decoded[0];
        }
        if (decoded is! Map) {
          throw Exception("Invalid response format");
        }
        final Map<String, dynamic> data = Map<String, dynamic>.from(decoded);
        
        Map<String, dynamic> summary = {};
        if (data['today_summary'] is Map) {
          summary = Map<String, dynamic>.from(data['today_summary']);
        } else if (data['summary'] is Map) {
          summary = Map<String, dynamic>.from(data['summary']);
        }

        Map<String, dynamic> targets = {};
        if (data['daily_targets'] is Map) {
          targets = Map<String, dynamic>.from(data['daily_targets']);
        }
        
        dynamic rawMeals = data['recent_meals'];
        List<dynamic> recentMeals = [];
        if (rawMeals is List) {
          recentMeals = rawMeals;
        } else if (rawMeals is Map) {
          recentMeals = rawMeals.values.toList();
        }

        int parseInt(dynamic value) {
          if (value == null) return 0;
          if (value is num) return value.toInt();
          return (double.tryParse(value.toString()) ?? 0.0).toInt();
        }

        int targetCal = parseInt(targets['calories'] ?? 2000);
        int targetCarbs = parseInt(targets['carbs'] ?? 250);
        int targetProtein = parseInt(targets['protein'] ?? 100);
        int targetFat = parseInt(targets['fat'] ?? 65);
        int targetFiber = parseInt(targets['fiber'] ?? 30);
        int targetSugar = parseInt(targets['sugar'] ?? 50);

        final currentCal = parseInt(summary['calories'] ?? summary['current_calories']);
        final currentCarbs = parseInt(summary['carbs'] ?? summary['carbs_g']);
        final currentProtein = parseInt(summary['protein'] ?? summary['protein_g']);
        final currentFat = parseInt(summary['fat'] ?? summary['fat_g']);
        final currentFiber = parseInt(summary['fiber'] ?? summary['fiber_g']);
        final currentSugar = parseInt(summary['sugar'] ?? summary['sugar_g']);

        final meals = recentMeals.whereType<Map>().map((m) {
          final timeStr = m['meal_time']?.toString() ?? DateTime.now().toIso8601String();
          final time = DateTime.tryParse(timeStr) ?? DateTime.now();
          final foodName = m['food_name_detected']?.toString() ?? 'Makanan';
          return TimelineMealInfo(
            time: DateFormat('HH:mm').format(time),
            name: foodName,
            calories: parseInt(m['calories_kcal'] ?? m['calories']),
            icon: _getIconForFood(foodName),
            iconColor: _getIconColorForFood(foodName),
          );
        }).toList();

        state = state.copyWith(
          currentCalories: currentCal,
          targetCalories: targetCal > 0 ? targetCal : 2000,
          remainingCalories: (targetCal - currentCal).clamp(0, 9999),
          caloryPercentage: targetCal > 0 ? (currentCal / targetCal).clamp(0.0, 1.0) : 0.0,
          macros: {
            'Karbohidrat': MacroNutrientInfo(
              current: currentCarbs,
              target: targetCarbs,
              percentage: targetCarbs > 0 ? (currentCarbs / targetCarbs).clamp(0.0, 1.0) : 0.0,
            ),
            'Protein': MacroNutrientInfo(
              current: currentProtein,
              target: targetProtein,
              percentage: targetProtein > 0 ? (currentProtein / targetProtein).clamp(0.0, 1.0) : 0.0,
            ),
            'Lemak': MacroNutrientInfo(
              current: currentFat,
              target: targetFat,
              percentage: targetFat > 0 ? (currentFat / targetFat).clamp(0.0, 1.0) : 0.0,
            ),
            'Serat': MacroNutrientInfo(
              current: currentFiber,
              target: targetFiber,
              percentage: targetFiber > 0 ? (currentFiber / targetFiber).clamp(0.0, 1.0) : 0.0,
            ),
            'Gula': MacroNutrientInfo(
              current: currentSugar,
              target: targetSugar,
              percentage: targetSugar > 0 ? (currentSugar / targetSugar).clamp(0.0, 1.0) : 0.0,
            ),
          },
          mealsTimeline: meals,
          isLoading: false,
        );
      } else {
        state = state.copyWith(isLoading: false, errorMessage: "HTTP Error: ${response.statusCode}");
      }
    } catch (e) {
      print("Error fetching summary: $e");
      state = state.copyWith(isLoading: false, errorMessage: "Error: $e");
    }
  }

  IconData _getIconForFood(String name) {
    name = name.toLowerCase();
    if (name.contains('nasi')) return Icons.restaurant;
    if (name.contains('susu')) return Icons.local_cafe;
    if (name.contains('buah')) return Icons.bakery_dining;
    if (name.contains('ayam') || name.contains('daging')) return Icons.kebab_dining;
    return Icons.lunch_dining;
  }

  Color _getIconColorForFood(String name) {
    name = name.toLowerCase();
    if (name.contains('nasi')) return Colors.blue;
    if (name.contains('susu')) return Colors.brown;
    if (name.contains('buah')) return Colors.green;
    return Colors.orange;
  }

  void setActiveProfile(String profileName) {
    if (state.activeProfileName == profileName) return;
    state = state.copyWith(activeProfileName: profileName);
    fetchSummary();
  }

  Future<void> setActiveTab(String tab) async {
    state = state.copyWith(activeTab: tab);
    // In a real app, this might fetch weekly/monthly data
    await fetchSummary();
  }
}
