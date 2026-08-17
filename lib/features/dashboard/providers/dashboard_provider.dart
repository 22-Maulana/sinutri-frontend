import 'dart:convert';
import 'package:collection/collection.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../core/constants/api_constants.dart';
import '../../../core/services/notification_service.dart';
import '../../profile/providers/profile_provider.dart';
import '../../profile/models/profile_state.dart';
import '../models/dashboard_state.dart';
import '../../history/models/history_state.dart';

final dashboardProvider = StateNotifierProvider<DashboardNotifier, DashboardState>((ref) {
  final profileState = ref.watch(profileProvider);
  return DashboardNotifier(ref, profileState);
});

class DashboardNotifier extends StateNotifier<DashboardState> {
  final Ref _ref;
  final ProfileState _profileState;

  DashboardNotifier(this._ref, this._profileState) : super(_initialState()) {
    if (_profileState.motherName != 'Loading...') {
      _updateFromProfile();
    }
  }

  void _updateFromProfile() {
    state = state.copyWith(
      userName: _profileState.motherName.split(' ')[0], // First name
    );
    fetchSummary();
  }

  static DashboardState _initialState() {
    return DashboardState(
      userName: 'User',
      currentDate: DateFormat('EEEE, d MMMM yyyy', 'id').format(DateTime.now()),
      activeProfileName: 'Saya',
      caloryPercentage: 0,
      currentCalories: 0,
      targetCalories: 2550,
      proteinPercentage: 0,
      ironPercentage: 0,
      fatPercentage: 0,
      calciumPercentage: 0,
      recentMeals: [],
      isLoading: false,
    );
  }

  Future<void> fetchSummary() async {
    if (state.recentMeals.isEmpty) {
      state = state.copyWith(isLoading: true);
    }
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
        List<dynamic> recentMealsRaw = [];
        if (rawMeals is List) {
          recentMealsRaw = rawMeals;
        } else if (rawMeals is Map) {
          recentMealsRaw = rawMeals.values.toList();
        }

        // Safe parsing helpers
        int parseInt(dynamic value) => value is num ? value.toInt() : (int.tryParse(value?.toString() ?? '') ?? 0);
        double parseDouble(dynamic value) => value is num ? value.toDouble() : (double.tryParse(value?.toString() ?? '') ?? 0.0);

        // Targets from backend
        int targetCal = parseInt(targets['calories'] ?? 2000);
        double targetCarbs = parseDouble(targets['carbs'] ?? 250);
        double targetSugar = parseDouble(targets['sugar'] ?? 50);
        double targetProtein = parseDouble(targets['protein'] ?? 100);
        double targetSerat = parseDouble(targets['fiber'] ?? 30);

        final currentCal = parseInt(summary['calories'] ?? summary['current_calories']);
        final currentCarbs = parseDouble(summary['carbs'] ?? summary['carbs_g']);
        final currentSugar = parseDouble(summary['sugar'] ?? summary['sugar_g']);
        final currentProtein = parseDouble(summary['protein'] ?? summary['protein_g']);
        final currentSerat = parseDouble(summary['fiber'] ?? summary['fiber_g']);
        
        // Recent meals mapping
        final meals = recentMealsRaw.whereType<Map>().reversed.take(3).map((m) {
          final timeStr = m['meal_time']?.toString() ?? DateTime.now().toIso8601String();
          final time = DateTime.tryParse(timeStr) ?? DateTime.now();
          return FoodHistoryItem(
            id: m['id']?.toString() ?? '',
            name: m['food_name_detected'] ?? 'Makanan',
            time: DateFormat('HH:mm').format(time) + ' WIB',
            calories: parseInt(m['calories_kcal'] ?? m['calories']),
            imagePath: m['photo_url'] ?? 'assets/images/placeholder.png',
            isSaved: true,
            fullMeal: DailyMealItem(
              id: m['id']?.toString() ?? '',
              name: m['food_name_detected'] ?? 'Makanan',
              time: DateFormat('HH:mm').format(time) + ' WIB',
              calories: parseInt(m['calories_kcal'] ?? m['calories']),
              recommendation: m['recommendation_status'] ?? 'PERHATIAN',
              protein: parseDouble(m['protein_g'] ?? m['protein']),
              fat: parseDouble(m['fat_g'] ?? m['fat']),
              carbs: parseDouble(m['carbs_g'] ?? m['carbs']),
              fiber: parseDouble(m['fiber_g'] ?? m['fiber']),
              sugar: parseDouble(m['sugar_g'] ?? m['sugar']),
              reason: m['notes'] ?? '',
              akgPercentageCalories: parseDouble(m['akg_percentage_calories']),
              akgPercentageProtein: parseDouble(m['akg_percentage_protein']),
              exactIgScore: parseDouble(m['ig_score'] ?? m['exact_ig_score']),
              exactIgCategory: m['ig_category'] ?? m['exact_ig_category'] ?? 'RENDAH',
            ),
          );
        }).toList();

        state = state.copyWith(
          currentCalories: currentCal,
          targetCalories: targetCal > 0 ? targetCal : 2000,
          caloryPercentage: targetCal > 0 ? (currentCal / targetCal).clamp(0.0, 1.0) : 0.0,
          proteinPercentage: targetCarbs > 0 ? (currentCarbs / targetCarbs).clamp(0.0, 1.0) : 0.0, // Karbohidrat bar
          ironPercentage: targetSugar > 0 ? (currentSugar / targetSugar).clamp(0.0, 1.0) : 0.0,    // Gula bar
          fatPercentage: targetProtein > 0 ? (currentProtein / targetProtein).clamp(0.0, 1.0) : 0.0, // Protein bar
          calciumPercentage: targetSerat > 0 ? (currentSerat / targetSerat).clamp(0.0, 1.0) : 0.0,   // Serat bar
          recentMeals: meals,
          isLoading: false,
        );
      } else {
        state = state.copyWith(isLoading: false);
      }
    } catch (e) {
      print("Error fetching dashboard summary: $e");
      state = state.copyWith(isLoading: false);
    }
  }

  void setActiveProfile(String profileName) {
    if (state.activeProfileName == profileName) return;
    state = state.copyWith(activeProfileName: profileName);
    fetchSummary();
  }

  Future<void> triggerDailySummaryNotification() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      // Get Mother Summary
      final motherUri = Uri.parse('${ApiConstants.dashboardSummary}?target_type=MOTHER&target_id=${_profileState.motherId}');
      final motherRes = await http.get(
        motherUri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );
      
      String bodyText = "";
      if (motherRes.statusCode == 200) {
        final mData = jsonDecode(motherRes.body)['summary'];
        bodyText += "Ibu: ${(mData['current_calories'] as num).toInt()} / 2550 kkal. ";
      }

      // Get Child Summary if exists
      if (_profileState.children.isNotEmpty) {
        final child = _profileState.children.first;
        final childUri = Uri.parse('${ApiConstants.dashboardSummary}?target_type=CHILD&target_id=${child.id}');
        final childRes = await http.get(
          childUri,
          headers: {
            'Authorization': 'Bearer $token',
            'Accept': 'application/json',
          },
        );
        if (childRes.statusCode == 200) {
          final cData = jsonDecode(childRes.body)['summary'];
          bodyText += "${child.name}: ${(cData['current_calories'] as num).toInt()} / 1400 kkal.";
        }
      }

      if (bodyText.isNotEmpty) {
        await NotificationService.showNotification(
          id: 100,
          title: "Rekapan Gizi SINUTRI Hari Ini",
          body: bodyText + " Tetap jaga asupan sehat ya!",
        );
      }
    } catch (e) {
      print("Error triggering notification: $e");
    }
  }
}
