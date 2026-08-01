import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../core/constants/api_constants.dart';
import '../../../core/services/pdf_service.dart';
import '../models/profile_state.dart';

final profileProvider = StateNotifierProvider<ProfileNotifier, ProfileState>((ref) {
  return ProfileNotifier();
});

class ProfileNotifier extends StateNotifier<ProfileState> {
  ProfileNotifier() : super(_initialState()) {
    fetchProfile();
  }

  static ProfileState _initialState() {
    return ProfileState(
      motherId: '',
      motherName: 'Loading...',
      email: '',
      pregnancyStatusText: '',
      breastfeedingStatusText: '',
      motherAvatarPath: '', 
      children: [],
      motherBirthDate: null,
      motherStatus: '',
      motherAllergies: [],
      isDarkMode: false,
      hasNotifications: true,
      isLoading: true,
    );
  }

  Future<void> fetchProfile() async {
    state = state.copyWith(isLoading: true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      final response = await http.get(
        Uri.parse(ApiConstants.profile),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body)['data'] ?? {};
        final userProfile = data['profile'] ?? data['user_profile'] ?? data['mother_profile'];
        final List<dynamic> childrenData = data['children'] ?? [];

        final children = childrenData.map((c) {
          final birthDate = DateTime.parse(c['birth_date']);
          final months = DateTime.now().difference(birthDate).inDays ~/ 30;
          String ageText = months >= 12 ? "${months ~/ 12} Tahun" : "$months Bulan";

          return ChildProfileInfo(
            id: c['id']?.toString() ?? '',
            name: c['name'] ?? '',
            ageText: ageText,
            allergiesText: (c['allergies'] is List && (c['allergies'] as List).isNotEmpty) ? "Alergi: ${(c['allergies'] as List).join(', ')}" : 'Tidak ada alergi',
            initial: (c['name'] != null && c['name'].toString().isNotEmpty) ? c['name'].toString().substring(0, 1) : 'A',
            gender: c['gender'] ?? 'L',
            birthDate: birthDate,
            rawAllergies: c['allergies'] is List ? List<String>.from(c['allergies']) : [],
          );
        }).toList();

        final allergies = userProfile != null ? List<String>.from(userProfile['food_allergies'] ?? userProfile['allergies'] ?? []) : <String>[];
        final birthDateStr = userProfile != null ? (userProfile['birth_date'] ?? userProfile['dob']) : null;

        state = state.copyWith(
          motherId: userProfile != null ? (userProfile['id']?.toString() ?? data['id']?.toString() ?? '') : (data['id']?.toString() ?? ''),
          motherName: data['name'] ?? userProfile?['name'] ?? 'Pengguna SINUTRI',
          email: data['email'] ?? '',
          pregnancyStatusText: 'Profil Personal',
          motherBirthDate: birthDateStr != null ? DateTime.tryParse(birthDateStr.toString()) : null,
          motherStatus: userProfile?['diabetes_status'] ?? '',
          motherAllergies: allergies,
          children: children,
          isLoading: false,
        );
      }
    } catch (e) {
      print("Error fetching profile: $e");
      state = state.copyWith(isLoading: false);
    }
  }

  void toggleDarkMode(bool value) {
    state = state.copyWith(isDarkMode: value);
  }

  Future<bool> updateMotherProfile({
    String? name,
    String? status,
    DateTime? birthDate,
    List<String>? allergies,
  }) async {
    state = state.copyWith(isLoading: true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      // Note: Backend ProfileController@updateMotherProfile might not update 'name' in User table,
      // but we send it anyway if available or we might need another endpoint.
      final response = await http.put(
        Uri.parse(ApiConstants.profileMother),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'status': status,
          'birth_date': birthDate?.toIso8601String().split('T')[0],
          'allergies': allergies,
        }),
      );

      if (response.statusCode == 200) {
        await fetchProfile();
        return true;
      }
      state = state.copyWith(isLoading: false);
      return false;
    } catch (e) {
      print("Error updating mother profile: $e");
      state = state.copyWith(isLoading: false);
      return false;
    }
  }

  Future<bool> addChild({
    required String name,
    required DateTime birthDate,
    required String gender,
    List<String>? allergies,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      final response = await http.post(
        Uri.parse(ApiConstants.profileChild),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'name': name,
          'birth_date': birthDate.toIso8601String(),
          'gender': gender,
          'allergies': allergies ?? [],
        }),
      );

      if (response.statusCode == 201) {
        await fetchProfile();
        return true;
      }
      return false;
    } catch (e) {
      print("Error adding child: $e");
      return false;
    }
  }

  Future<bool> updateChild({
    required String id,
    required String name,
    required DateTime birthDate,
    required String gender,
    List<String>? allergies,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      final response = await http.put(
        Uri.parse('${ApiConstants.profileChild}/$id'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'name': name,
          'birth_date': birthDate.toIso8601String(),
          'gender': gender,
          'allergies': allergies ?? [],
        }),
      );

      if (response.statusCode == 200) {
        await fetchProfile();
        return true;
      }
      return false;
    } catch (e) {
      print("Error updating child: $e");
      return false;
    }
  }

  Future<bool> deleteChild(String id) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      final response = await http.delete(
        Uri.parse('${ApiConstants.profileChild}/$id'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        await fetchProfile();
        return true;
      }
      return false;
    } catch (e) {
      print("Error deleting child: $e");
      return false;
    }
  }

  Future<void> exportPdfReport() async {
    state = state.copyWith(isLoading: true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      // 1. Fetch Mother Data (Logs & Growth)
      final motherLogsRes = await http.get(
        Uri.parse('${ApiConstants.foodLogs}?target_type=MOTHER&target_id=${state.motherId}'),
        headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
      );
      
      final motherGrowthRes = await http.get(
        Uri.parse('${ApiConstants.growthRecords}?target_type=MOTHER&target_id=${state.motherId}'),
        headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
      );

      List<Map<String, dynamic>> motherLogs = [];
      if (motherLogsRes.statusCode == 200) {
        final data = jsonDecode(motherLogsRes.body)['data'] as List;
        motherLogs = data.map((m) => {
          'date': DateFormat('dd/MM/yy').format(DateTime.parse(m['meal_time'])),
          'name': m['food_name_detected'],
          'calories': double.tryParse(m['calories_kcal']?.toString() ?? '0') ?? 0.0,
          'protein': double.tryParse(m['protein_g']?.toString() ?? '0') ?? 0.0,
          'carbs': double.tryParse(m['carbs_g']?.toString() ?? '0') ?? 0.0,
        }).toList();
      }

      List<Map<String, dynamic>> motherGrowth = [];
      if (motherGrowthRes.statusCode == 200) {
        final data = jsonDecode(motherGrowthRes.body)['data'] as List;
        motherGrowth = data.map((g) => {
          'date': DateFormat('dd/MM/yy').format(DateTime.parse(g['measured_at'])),
          'weight': double.tryParse(g['weight_kg']?.toString() ?? '0') ?? 0.0,
          'height': double.tryParse(g['height_cm']?.toString() ?? '0') ?? 0.0,
          'status': g['status'] ?? 'Normal',
        }).toList();
      }

      // 2. Fetch Children Data
      List<Map<String, dynamic>> childrenData = [];
      for (var child in state.children) {
        // Logs
        final childLogsRes = await http.get(
          Uri.parse('${ApiConstants.foodLogs}?target_type=CHILD&target_id=${child.id}'),
          headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
        );
        
        // Growth
        final childGrowthRes = await http.get(
          Uri.parse('${ApiConstants.growthRecords}?target_type=CHILD&target_id=${child.id}'),
          headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
        );

        List<Map<String, dynamic>> cLogs = [];
        if (childLogsRes.statusCode == 200) {
          final data = jsonDecode(childLogsRes.body)['data'] as List;
          cLogs = data.map((m) => {
            'date': DateFormat('dd/MM/yy').format(DateTime.parse(m['meal_time'])),
            'name': m['food_name_detected'],
            'calories': double.tryParse(m['calories_kcal']?.toString() ?? '0') ?? 0.0,
            'protein': double.tryParse(m['protein_g']?.toString() ?? '0') ?? 0.0,
            'carbs': double.tryParse(m['carbs_g']?.toString() ?? '0') ?? 0.0,
          }).toList();
        }

        List<Map<String, dynamic>> cGrowth = [];
        if (childGrowthRes.statusCode == 200) {
          final data = jsonDecode(childGrowthRes.body)['data'] as List;
          cGrowth = data.map((g) => {
            'date': DateFormat('dd/MM/yy').format(DateTime.parse(g['measured_at'])),
            'weight': double.tryParse(g['weight_kg']?.toString() ?? '0') ?? 0.0,
            'height': double.tryParse(g['height_cm']?.toString() ?? '0') ?? 0.0,
            'status': g['status'] ?? 'Normal',
          }).toList();
        }

        childrenData.add({
          'name': child.name,
          'logs': cLogs,
          'growth': cGrowth,
        });
      }

      state = state.copyWith(isLoading: false);
      
      // 3. Generate PDF
      await PdfService.generateNutritionReport(
        motherName: state.motherName,
        motherLogs: motherLogs,
        motherGrowth: motherGrowth,
        childrenData: childrenData,
      );

    } catch (e) {
      print("Error exporting PDF: $e");
      state = state.copyWith(isLoading: false);
    }
  }

  // Profiling API Methods for Diabetes System
  Future<bool> saveBasicData({
    required String name,
    required int age,
    required String gender,
    required double heightCm,
    required double weightKg,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? prefs.getString('token');

      if (token == null) {
        state = state.copyWith(isLoading: false, errorMessage: 'Token tidak ditemukan');
        return false;
      }

      final response = await http.post(
        Uri.parse('${ApiConstants.apiBaseUrl}/profile/basic-data'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'name': name,
          'age': age,
          'gender': gender,
          'height_cm': heightCm,
          'weight_kg': weightKg,
        }),
      );

      if (response.statusCode == 200) {
        state = state.copyWith(isLoading: false);
        return true;
      } else {
        final errorData = jsonDecode(response.body);
        state = state.copyWith(
          isLoading: false,
          errorMessage: errorData['message'] ?? 'Gagal menyimpan data dasar',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: e.toString());
      return false;
    }
  }

  Future<bool> updateHealthCondition({
    required String diabetesStatus,
    required bool familyDiabetesHistory,
    bool? hypertension,
    List<String>? foodAllergies,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? prefs.getString('token');

      if (token == null) {
        state = state.copyWith(isLoading: false, errorMessage: 'Token tidak ditemukan');
        return false;
      }

      final response = await http.put(
        Uri.parse('${ApiConstants.apiBaseUrl}/profile/health-condition'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'diabetes_status': diabetesStatus,
          'family_diabetes_history': familyDiabetesHistory,
          if (hypertension != null) 'hypertension': hypertension,
          if (foodAllergies != null) 'food_allergies': foodAllergies,
        }),
      );

      if (response.statusCode == 200) {
        state = state.copyWith(isLoading: false);
        return true;
      } else {
        final errorData = jsonDecode(response.body);
        state = state.copyWith(
          isLoading: false,
          errorMessage: errorData['message'] ?? 'Gagal menyimpan kondisi kesehatan',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: e.toString());
      return false;
    }
  }

  Future<bool> updateHealthTargets({
    required List<String> targets,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? prefs.getString('token');

      if (token == null) {
        state = state.copyWith(isLoading: false, errorMessage: 'Token tidak ditemukan');
        return false;
      }

      final response = await http.put(
        Uri.parse('${ApiConstants.apiBaseUrl}/profile/health-targets'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'health_targets': targets,
        }),
      );

      if (response.statusCode == 200) {
        state = state.copyWith(isLoading: false);
        return true;
      } else {
        final errorData = jsonDecode(response.body);
        state = state.copyWith(
          isLoading: false,
          errorMessage: errorData['message'] ?? 'Gagal menyimpan target kesehatan',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: e.toString());
      return false;
    }
  }
}
