import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../core/constants/api_constants.dart';
import '../models/history_state.dart';
import '../../dashboard/providers/dashboard_provider.dart';


final historyProvider = StateNotifierProvider<HistoryNotifier, HistoryState>((ref) {
  return HistoryNotifier(ref);
});


class HistoryNotifier extends StateNotifier<HistoryState> {
  final Ref _ref;

  HistoryNotifier(this._ref) : super(_initialState()) {
    fetchHistory();
  }


  static HistoryState _initialState() {
    final now = DateTime.now();
    return HistoryState(
      selectedDayIndex: 4, 
      selectedDate: now,
      selectedPeriod: HistoryPeriod.daily,
      dateText: DateFormat('EEEE, d MMMM yyyy', 'id').format(now),
      meals: [],
      summary: DailySummary(
        currentCalories: 0,
        targetCalories: 2200,
        currentProtein: 0,
        targetProtein: 75,
        currentCarbs: 0,
        targetCarbs: 300,
      ),
      isLoading: true,
    );
  }

  Future<void> fetchHistory() async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      debugPrint("[HISTORY-FE] Fetching history for period: ${state.selectedPeriod}, date: ${state.dateText}");
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? prefs.getString('auth_token') ?? '';

      // Susun query parameters berdasarkan period
      final Map<String, String> queryParams = {};

      if (state.selectedPeriod == HistoryPeriod.daily) {
        final dateStr = DateFormat('yyyy-MM-dd').format(state.selectedDate);
        queryParams['start_date'] = dateStr;
        queryParams['end_date'] = dateStr;
      } else if (state.selectedPeriod == HistoryPeriod.weekly) {
        final now = DateTime.now();
        queryParams['start_date'] = DateFormat('yyyy-MM-dd').format(now.subtract(const Duration(days: 6)));
        queryParams['end_date'] = DateFormat('yyyy-MM-dd').format(now);
      } else if (state.selectedPeriod == HistoryPeriod.monthly) {
        final now = DateTime.now();
        queryParams['start_date'] = DateFormat('yyyy-MM-dd').format(now.subtract(const Duration(days: 29)));
        queryParams['end_date'] = DateFormat('yyyy-MM-dd').format(now);
      }

      // Panggil langsung endpoint /api/food-logs (bukan dashboard/summary)
      final uri = Uri.parse(ApiConstants.foodLogs).replace(queryParameters: queryParams);

      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      debugPrint("[HISTORY-FE] Fetch food-logs response: ${response.statusCode}");

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final List<dynamic> logsRaw = data['data'] ?? [];

        debugPrint("[HISTORY-FE] Loaded ${logsRaw.length} meal logs dari food-logs endpoint.");

        // Safe parsing helpers
        int parseInt(dynamic value) => value is num ? value.toInt() : (int.tryParse(value?.toString() ?? '') ?? 0);
        double parseDouble(dynamic value) => value is num ? value.toDouble() : (double.tryParse(value?.toString() ?? '') ?? 0.0);

        final meals = logsRaw.map((item) {
          final DateTime dt = DateTime.parse(item['meal_time']);
          String timeStr;
          if (state.selectedPeriod == HistoryPeriod.daily) {
            timeStr = DateFormat('HH:mm').format(dt);
          } else {
            timeStr = DateFormat('d MMM, HH:mm', 'id').format(dt);
          }

          return DailyMealItem(
            id: item['id'].toString(),
            name: item['food_name_detected'] ?? 'Makanan',
            time: timeStr,
            calories: parseInt(item['calories_kcal'] ?? item['calories'] ?? 0),
            recommendation: item['recommendation_status'] ?? 'PERHATIAN',
            protein: parseDouble(item['protein_g'] ?? item['protein'] ?? 0),
            fat: parseDouble(item['fat_g'] ?? item['fat'] ?? 0),
            carbs: parseDouble(item['carbs_g'] ?? item['carbs'] ?? 0),
            fiber: parseDouble(item['fiber_g'] ?? item['fiber'] ?? 0),
            reason: item['notes'] ?? '',
            akgPercentageCalories: parseDouble(item['akg_percentage_calories']),
            akgPercentageProtein: parseDouble(item['akg_percentage_protein']),
            exactIgScore: parseDouble(item['exact_ig_score']),
            exactIgCategory: item['exact_ig_category'] ?? 'RENDAH',
          );
        }).toList();

        // Hitung summary dari log yang diambil
        int totalCal = meals.fold(0, (s, m) => s + m.calories);
        int totalProt = meals.fold(0, (s, m) => s + m.protein.toInt());
        int totalCarbs = meals.fold(0, (s, m) => s + m.carbs.toInt());

        int multiplier = state.selectedPeriod == HistoryPeriod.weekly
            ? 7
            : state.selectedPeriod == HistoryPeriod.monthly
                ? 30
                : 1;

        if (!mounted) return;

        state = state.copyWith(
          meals: meals,
          summary: DailySummary(
            currentCalories: totalCal,
            targetCalories: 2000 * multiplier,
            currentProtein: totalProt,
            targetProtein: 75 * multiplier,
            currentCarbs: totalCarbs,
            targetCarbs: 250 * multiplier,
          ),
          isLoading: false,
        );
      } else {
        if (!mounted) return;
        state = state.copyWith(isLoading: false, error: 'Gagal mengambil data riwayat (${response.statusCode})');
        debugPrint("[HISTORY-FE] Error: ${response.statusCode} - ${response.body}");
      }
    } catch (e, stack) {
      debugPrint("[HISTORY-FE] Exception fetching history: $e\n$stack");
      if (!mounted) return;
      state = state.copyWith(isLoading: false, error: 'Error: $e');
    }
  }


  void setPeriod(HistoryPeriod period) {
    if (state.selectedPeriod == period) return;
    state = state.copyWith(selectedPeriod: period);
    fetchHistory();
  }

  void selectDay(int index, String dateText) {
    if (state.selectedDayIndex == index) return;
    final selectedDate = DateTime.now().subtract(Duration(days: (4 - index).toInt()));
    state = state.copyWith(selectedDayIndex: index, selectedDate: selectedDate, dateText: dateText, selectedPeriod: HistoryPeriod.daily);
    fetchHistory();
  }

  void selectCustomDate(DateTime date) {
    final now = DateTime.now();
    int index = -1;
    
    // Check if it's within the last 5 days
    final today = DateTime(now.year, now.month, now.day);
    final selectedDay = DateTime(date.year, date.month, date.day);
    final diff = today.difference(selectedDay).inDays;
    
    if (diff >= 0 && diff <= 4) {
      index = 4 - diff;
    }
    
    state = state.copyWith(
      selectedDayIndex: index,
      selectedDate: date,
      dateText: DateFormat('EEEE, d MMMM yyyy', 'id').format(date),
      selectedPeriod: HistoryPeriod.daily
    );
    fetchHistory();
  }

  Future<void> deleteHistory(String id) async {
    debugPrint("[HISTORY-FE] Deleting food log ID: $id");
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      final response = await http.delete(
        Uri.parse('${ApiConstants.foodLogs}/$id'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      debugPrint("[HISTORY-FE] Delete response code: ${response.statusCode}");

      if (response.statusCode == 200) {
        fetchHistory();
        _ref.read(dashboardProvider.notifier).fetchSummary();
      }
    } catch (e, stack) {
      debugPrint("[HISTORY-FE] Error deleting history: $e\n$stack");
    }
  }

  Future<void> editHistory(String id, String notes) async {
    debugPrint("[HISTORY-FE] Editing food log ID: $id with notes: $notes");
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      final response = await http.put(
        Uri.parse('${ApiConstants.foodLogs}/$id'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'notes': notes,
        }),
      );

      debugPrint("[HISTORY-FE] Edit response code: ${response.statusCode}");

      if (response.statusCode == 200) {
        fetchHistory();
        _ref.read(dashboardProvider.notifier).fetchSummary();
      }
    } catch (e, stack) {
      debugPrint("[HISTORY-FE] Error editing history: $e\n$stack");
    }
  }
}
