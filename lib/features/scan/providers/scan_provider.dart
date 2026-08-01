import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:collection/collection.dart';
import '../../../core/constants/api_constants.dart';
import '../../profile/providers/profile_provider.dart';
import '../../profile/models/profile_state.dart';
import '../../dashboard/providers/dashboard_provider.dart';
import '../../history/providers/history_provider.dart';
import '../models/scan_request_model.dart';
import '../models/scan_response_model.dart';

final scanProvider = StateNotifierProvider<ScanNotifier, ScanRequestModel>((ref) {
  return ScanNotifier(ref);
});

class ScanAnalysisResult {
  final bool success;
  final ScanResponseModel? data;
  final String? errorMessage;

  ScanAnalysisResult.success(this.data) : success = true, errorMessage = null;
  ScanAnalysisResult.failure(this.errorMessage) : success = false, data = null;
}

class ScanNotifier extends StateNotifier<ScanRequestModel> {
  final Ref _ref;

  ScanNotifier(this._ref) : super(ScanRequestModel());
  final ImagePicker _picker = ImagePicker();

  void setTargetProfile(String profile) {
    state = state.copyWith(targetProfileName: profile);
  }

  Future<void> pickImage(ImageSource source) async {
    try {
      final XFile? image = await _picker.pickImage(
        source: source,
        maxWidth: 1080,
        maxHeight: 1080,
        imageQuality: 80,
      );
      if (image != null) {
        state = state.copyWith(imagePath: image.path);
      }
    } catch (e) {
      print("Error picking image: $e");
    }
  }

  void removeImage() {
    state = state.copyWith(imagePath: '');
  }

  void setNotes(String notes) {
    state = state.copyWith(notes: notes);
  }

  void setDescription(String description) {
    state = state.copyWith(description: description);
  }

  ({String targetType, String targetId}) _resolveTarget() {
    final profileState = _ref.read(profileProvider);
    String targetType = 'MOTHER';
    String targetId = profileState.motherId;

    if (state.targetProfileName != 'Saya') {
      final child = profileState.children.firstWhereOrNull(
        (c) => c.name == state.targetProfileName,
      );
      if (child != null) {
        targetType = 'CHILD';
        targetId = child.id;
      }
    }
    return (targetType: targetType, targetId: targetId);
  }

  ScanResponseModel? _parseResponse(Map<String, dynamic> data, String targetType) {
    final caloriesVal = double.tryParse(data['calories_kcal']?.toString() ?? '0') ?? 0.0;
    final proteinVal = double.tryParse(data['protein_g']?.toString() ?? '0') ?? 0.0;
    final carbsVal = double.tryParse(data['carbs_g']?.toString() ?? '0') ?? 0.0;
    final fatVal = double.tryParse(data['fat_g']?.toString() ?? '0') ?? 0.0;
    final fiberVal = double.tryParse(data['fiber_g']?.toString() ?? '0') ?? 0.0;
    final sugarVal = double.tryParse(data['sugar_g']?.toString() ?? '0') ?? 0.0;

    int targetCal = targetType == 'MOTHER' ? 2550 : 1400;
    int targetProtein = targetType == 'MOTHER' ? 75 : 40;
    int targetCarbs = targetType == 'MOTHER' ? 300 : 150;
    int targetFat = targetType == 'MOTHER' ? 60 : 35;

    final aiInsight = data['ai_insight']?.toString() ?? '';
    final aiRecommendation = data['ai_recommendation']?.toString() ?? '';

    String combinedReasoning = aiRecommendation;
    if (aiInsight.isNotEmpty && aiRecommendation.isNotEmpty) {
      combinedReasoning = "$aiInsight\n\nSaran AI: $aiRecommendation";
    } else if (aiInsight.isNotEmpty) {
      combinedReasoning = aiInsight;
    } else if (combinedReasoning.isEmpty) {
      combinedReasoning = data['notes']?.toString() ?? 'Direkomendasikan sesuai kebutuhan nutrisi harian.';
    }

    final ingredientsList = (data['ingredients'] is List)
        ? List<String>.from(data['ingredients'])
        : <String>[];

    final alternatives = <Map<String, String>>[];
    if (data['alternative_foods'] is List) {
      for (var item in data['alternative_foods']) {
        if (item is Map) {
          alternatives.add({
            'name': item['name']?.toString() ?? '',
            'reason': item['reason']?.toString() ?? '',
          });
        }
      }
    }

    return ScanResponseModel(
      foodName: data['food_name_detected'] ?? 'Makanan',
      portionDesc: '${data['portion_grams'] ?? 100} gram (1 Porsi)',
      suggestionNote: aiInsight.isNotEmpty ? aiInsight : (data['notes'] ?? ''),
      recommendationStatus: data['recommendation_status'] ?? 'PERHATIAN',
      reasoning: combinedReasoning,
      aiInsight: aiInsight,
      aiRecommendation: aiRecommendation,
      ingredients: ingredientsList,
      alternativeFoods: alternatives,
      glycemicIndex: double.tryParse(data['glycemic_index']?.toString() ?? '0') ?? 0.0,
      glycemicScore: double.tryParse(data['glycemic_score']?.toString() ?? '0') ?? 0.0,
      riskCategory: data['risk_category']?.toString() ?? 'low',
      photoUrl: data['photo_url'],
      calories: caloriesVal,
      caloriesAkg: ((caloriesVal / targetCal) * 100).round(),
      protein: proteinVal,
      proteinAkg: ((proteinVal / targetProtein) * 100).round(),
      carbs: carbsVal,
      carbsAkg: ((carbsVal / targetCarbs) * 100).round(),
      fat: fatVal,
      fatAkg: ((fatVal / targetFat) * 100).round(),
      micronutrients: {
        'Serat': fiberVal,
        'Gula': sugarVal,
        'Indeks Glikemik': double.tryParse(data['glycemic_index']?.toString() ?? '0') ?? 0.0,
      },
    );
  }

  Future<ScanAnalysisResult> analyzeFood() async {
    if (state.imagePath == null || state.imagePath!.isEmpty) {
      debugPrint("[SCAN-FE] Warning: imagePath kosong, analisis dibatalkan.");
      return ScanAnalysisResult.failure("Harap pilih atau ambil foto makanan terlebih dahulu.");
    }

    try {
      debugPrint("[SCAN-FE] Memulai analisis gambar: ${state.imagePath}");
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? prefs.getString('auth_token') ?? '';
      final target = _resolveTarget();

      debugPrint("[SCAN-FE] Target Profile: ${target.targetType} (ID: ${target.targetId}), Notes: ${state.notes}");

      var request = http.MultipartRequest('POST', Uri.parse(ApiConstants.scan));
      request.headers.addAll({
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      });
      request.fields['target_type'] = target.targetType;
      request.fields['target_id'] = target.targetId;
      request.fields['notes'] = state.notes;
      request.files.add(await http.MultipartFile.fromPath('image', state.imagePath!));

      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);

      debugPrint("[SCAN-FE] Response Status: ${response.statusCode}");

      if (response.statusCode == 200 || response.statusCode == 201) {
        final bodyJson = jsonDecode(response.body);
        final data = bodyJson['data'];
        final parsed = _parseResponse(data, target.targetType);
        debugPrint("[SCAN-FE] Analisis AI Sukses -> Makanan: ${parsed?.foodName}, Status: ${parsed?.recommendationStatus}, Kalori: ${parsed?.calories} kkal");
        debugPrint("[SCAN-FE] Catatan: Hasil scan BELUM disimpan ke database. Menunggu konfirmasi user.");
        return ScanAnalysisResult.success(parsed);
      } else {
        debugPrint("[SCAN-FE] API Error: ${response.statusCode} - ${response.body}");
        try {
          final errJson = jsonDecode(response.body);
          final msg = errJson['message'] ?? errJson['error'] ?? "Terjadi kesalahan server (Kode ${response.statusCode}).";
          return ScanAnalysisResult.failure(msg);
        } catch (_) {
          return ScanAnalysisResult.failure("Gagal memproses analisis makanan (Status ${response.statusCode}). Silakan coba lagi.");
        }
      }
    } catch (e, stack) {
      debugPrint("[SCAN-FE] Exception saat analisis gambar: $e\n$stack");
      return ScanAnalysisResult.failure("Gagal terhubung ke server. Periksa koneksi internet Anda dan coba lagi.");
    }
  }

  Future<ScanAnalysisResult> analyzeFoodByText() async {
    if (state.description.isEmpty) {
      debugPrint("[SCAN-FE] Warning: deskripsi kosong, analisis teks dibatalkan.");
      return ScanAnalysisResult.failure("Harap tuliskan deskripsi makanan terlebih dahulu.");
    }

    try {
      debugPrint("[SCAN-FE] Memulai analisis teks: ${state.description}");
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';
      final target = _resolveTarget();

      final response = await http.post(
        Uri.parse(ApiConstants.scan),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'target_type': target.targetType,
          'target_id': target.targetId,
          'description': state.description,
          'notes': state.notes,
        }),
      );

      debugPrint("[SCAN-FE] Response Status: ${response.statusCode}");

      if (response.statusCode == 200 || response.statusCode == 201) {
        final bodyJson = jsonDecode(response.body);
        final data = bodyJson['data'];
        final parsed = _parseResponse(data, target.targetType);
        debugPrint("[SCAN-FE] Analisis Teks Sukses -> Makanan: ${parsed?.foodName}, Status: ${parsed?.recommendationStatus}");
        debugPrint("[SCAN-FE] Catatan: Hasil scan BELUM disimpan ke database. Menunggu konfirmasi user.");
        return ScanAnalysisResult.success(parsed);
      } else {
        debugPrint("[SCAN-FE] API Error: ${response.statusCode} - ${response.body}");
        try {
          final errJson = jsonDecode(response.body);
          final msg = errJson['message'] ?? errJson['error'] ?? "Terjadi kesalahan server (Kode ${response.statusCode}).";
          return ScanAnalysisResult.failure(msg);
        } catch (_) {
          return ScanAnalysisResult.failure("Gagal memproses analisis makanan (Status ${response.statusCode}). Silakan coba lagi.");
        }
      }
    } catch (e, stack) {
      debugPrint("[SCAN-FE] Exception saat analisis teks: $e\n$stack");
      return ScanAnalysisResult.failure("Gagal terhubung ke server. Periksa koneksi internet Anda dan coba lagi.");
    }
  }

  void discardScan() {
    debugPrint("[SCAN-FE] User memilih 'Saya Tidak Makan Ini' -> Scan dibatalkan. TIDAK ADA data yang disimpan ke database.");
  }

  Future<void> saveToHistory(ScanResponseModel result) async {
    try {
      debugPrint("[SCAN-FE] User memilih 'Ya, Saya Makan Ini' -> Memproses simpan ke Database (POST /api/food-logs)");
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      // Determine meal type based on hour
      final hour = DateTime.now().hour;
      String mealType = 'snack';
      if (hour >= 5 && hour < 11) {
        mealType = 'breakfast';
      } else if (hour >= 11 && hour < 16) {
        mealType = 'lunch';
      } else if (hour >= 16 && hour < 22) {
        mealType = 'dinner';
      }

      final payload = {
        'meal_time': DateTime.now().toIso8601String(),
        'meal_type': mealType,
        'photo_url': result.photoUrl,
        'food_name_detected': result.foodName,
        'portion_grams': 100,
        'notes': result.reasoning,
        'recommendation_status': result.recommendationStatus,
        'calories_kcal': result.calories,
        'protein_g': result.protein,
        'fat_g': result.fat,
        'carbs_g': result.carbs,
        'sugar_g': 0.0,
        'fiber_g': result.micronutrients['Serat'] ?? 0.0,
      };

      debugPrint("[SCAN-FE] Sending FoodLog Payload: ${jsonEncode(payload)}");

      final response = await http.post(
        Uri.parse(ApiConstants.foodLogs),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode(payload),
      );

      debugPrint("[SCAN-FE] Save FoodLog Response Code: ${response.statusCode}");

      if (response.statusCode == 201 || response.statusCode == 200) {
        debugPrint("[SCAN-FE] Sukses menyimpan Makanan ${result.foodName} ke Database! Updating Dashboard & History.");
        _ref.read(dashboardProvider.notifier).fetchSummary();
        _ref.read(historyProvider.notifier).fetchHistory();
      } else {
        debugPrint("[SCAN-FE] Gagal menyimpan food log ke server: ${response.statusCode} - ${response.body}");
      }
    } catch (e, stack) {
      debugPrint("[SCAN-FE] Exception saat menyimpan food log: $e\n$stack");
    }
  }
}
