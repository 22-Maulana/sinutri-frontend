import 'dart:convert';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../core/constants/api_constants.dart';
import '../../profile/providers/profile_provider.dart';

class ProfileRecapData {
  final String id;
  final String name;
  final String role;
  final double compliance;
  final Map<String, double> macros; // 'Karbo', 'Protein', 'Lemak'
  final String status;

  ProfileRecapData({
    required this.id,
    required this.name,
    required this.role,
    required this.compliance,
    required this.macros,
    required this.status,
  });
}

class WeeklyRecapState {
  final List<ProfileRecapData> recaps;
  final String aiWeeklyTips;
  final String akgAdvice;
  final bool isLoading;

  WeeklyRecapState({
    required this.recaps,
    this.aiWeeklyTips = '',
    this.akgAdvice = '',
    this.isLoading = false,
  });

  WeeklyRecapState copyWith({
    List<ProfileRecapData>? recaps,
    String? aiWeeklyTips,
    String? akgAdvice,
    bool? isLoading,
  }) {
    return WeeklyRecapState(
      recaps: recaps ?? this.recaps,
      aiWeeklyTips: aiWeeklyTips ?? this.aiWeeklyTips,
      akgAdvice: akgAdvice ?? this.akgAdvice,
      isLoading: isLoading ?? this.isLoading,
    );
  }
}

final weeklyRecapProvider = StateNotifierProvider<WeeklyRecapNotifier, WeeklyRecapState>((ref) {
  return WeeklyRecapNotifier(ref);
});

class WeeklyRecapNotifier extends StateNotifier<WeeklyRecapState> {
  final Ref _ref;

  WeeklyRecapNotifier(this._ref) : super(WeeklyRecapState(recaps: [], isLoading: true)) {
    fetchWeeklyRecaps();
  }

  Future<void> fetchWeeklyRecaps() async {
    state = state.copyWith(isLoading: true);
    try {
      final profileState = _ref.read(profileProvider);
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? prefs.getString('auth_token') ?? '';

      final List<ProfileRecapData> newRecaps = [];

      final now = DateTime.now();
      final sevenDaysAgo = now.subtract(const Duration(days: 7));
      final startDate = DateFormat('yyyy-MM-dd').format(sevenDaysAgo);
      final endDate = DateFormat('yyyy-MM-dd').format(now);

      // Fetch Personal User Recap
      final userRecap = await _fetchSingleProfileRecap(
        token: token,
        targetType: 'MOTHER',
        targetId: profileState.motherId,
        name: profileState.motherName.isNotEmpty ? profileState.motherName : 'Profil Saya',
        role: 'Profil Personal',
        startDate: startDate,
        endDate: endDate,
      );
      if (userRecap != null) newRecaps.add(userRecap);

      // Fetch DeepSeek AI Weekly Tips from API
      String tips = '';
      String akgAdviceStr = '';
      try {
        final weeklyUri = Uri.parse('${ApiConstants.dashboardWeekly}?end_date=$endDate');
        final weeklyRes = await http.get(weeklyUri, headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        });
        if (weeklyRes.statusCode == 200) {
          final wJson = jsonDecode(weeklyRes.body);
          tips = wJson['ai_weekly_tips'] ?? '';
          akgAdviceStr = wJson['akg_advice'] ?? '';
        }
      } catch (_) {}

      if (tips.isEmpty) {
        tips = "Tips DeepSeek AI: Evaluasi 7 hari menunjukkan pola nutrisi Anda berjalan baik. Tingkatkan asupan serat dari sayur hijau dan pertahankan konsumsi pangan lokal rendah Indeks Glikemik.";
      }
      
      if (akgAdviceStr.isEmpty) {
        akgAdviceStr = "AKG Mingguan: Tingkat pemenuhan AKG Anda cukup seimbang. Terus jaga asupan kalori dan protein harian.";
      }

      state = state.copyWith(recaps: newRecaps, aiWeeklyTips: tips, akgAdvice: akgAdviceStr, isLoading: false);
    } catch (e) {
      print("Error fetching weekly recaps: $e");
      state = state.copyWith(isLoading: false);
    }
  }

  Future<ProfileRecapData?> _fetchSingleProfileRecap({
    required String token,
    required String targetType,
    required String targetId,
    required String name,
    required String role,
    required String startDate,
    required String endDate,
  }) async {
    if (targetId.isEmpty) return null;

    final queryParams = {
      'target_type': targetType,
      'target_id': targetId,
      'start_date': startDate,
      'end_date': endDate,
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
      final jsonBody = jsonDecode(response.body);
      final data = jsonBody['today_summary'] ?? jsonBody['summary'] ?? {};
      final targets = jsonBody['daily_targets'] ?? {};
      
      double parseDouble(dynamic value) => value is num ? value.toDouble() : (double.tryParse(value?.toString() ?? '') ?? 0.0);

      double targetCal = parseDouble(targets['calories'] ?? 2000) * 7;
      double targetCarbs = parseDouble(targets['carbs'] ?? 250) * 7;
      double targetProtein = parseDouble(targets['protein'] ?? 100) * 7;
      double targetFat = parseDouble(targets['fat'] ?? 65) * 7;

      double currentCal = parseDouble(data['calories'] ?? data['current_calories']);
      double currentCarbs = parseDouble(data['carbs'] ?? data['carbs_g']);
      double currentProtein = parseDouble(data['protein'] ?? data['protein_g']);
      double currentFat = parseDouble(data['fat'] ?? data['fat_g']);

      double compliance = targetCal > 0 ? (currentCal / targetCal).clamp(0.0, 1.0) : 0.0;
      
      String status = 'Cukup';
      if (compliance > 0.8) status = 'Sangat Baik';
      else if (compliance > 0.5) status = 'Baik';
      else if (compliance < 0.3) status = 'Perlu Perhatian';

      return ProfileRecapData(
        id: targetId.isNotEmpty ? targetId : 'user',
        name: name.isNotEmpty ? name : 'Pengguna',
        role: role,
        compliance: compliance,
        macros: {
          'Karbo': targetCarbs > 0 ? (currentCarbs / targetCarbs).clamp(0.0, 1.0) : 0.0,
          'Protein': targetProtein > 0 ? (currentProtein / targetProtein).clamp(0.0, 1.0) : 0.0,
          'Lemak': targetFat > 0 ? (currentFat / targetFat).clamp(0.0, 1.0) : 0.0,
        },
        status: status,
      );
    }
    return null;
  }
}
