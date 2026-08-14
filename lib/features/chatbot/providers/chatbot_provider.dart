import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:intl/intl.dart';
import '../../../core/constants/api_constants.dart';
import '../models/chat_message.dart';

class ChatbotState {
  final List<ChatMessage> messages;
  final bool isLoading;
  final String currentProfile;

  ChatbotState({
    required this.messages, 
    this.isLoading = false,
    this.currentProfile = 'Saya',
  });

  ChatbotState copyWith({
    List<ChatMessage>? messages, 
    bool? isLoading,
    String? currentProfile,
  }) {
    return ChatbotState(
      messages: messages ?? this.messages,
      isLoading: isLoading ?? this.isLoading,
      currentProfile: currentProfile ?? this.currentProfile,
    );
  }
}

final chatbotProvider = StateNotifierProvider<ChatbotNotifier, ChatbotState>((ref) {
  return ChatbotNotifier();
});

class ChatbotNotifier extends StateNotifier<ChatbotState> {
  ChatbotNotifier() : super(ChatbotState(messages: [])) {
    _loadHistory();
  }

  static const String _historyKey = 'chat_history_v1';

  Future<void> _loadHistory() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final historyJson = prefs.getString(_historyKey);
      
      if (historyJson != null) {
        final List<dynamic> decoded = jsonDecode(historyJson);
        final messages = decoded.map((m) => ChatMessage.fromJson(m)).toList();
        state = state.copyWith(messages: messages);
        debugPrint("[NUTRIBOT-FE] Loaded ${messages.length} chat history items.");
      } else {
        // Initial greeting if no history
        state = state.copyWith(
          messages: [
            ChatMessage(
              text: 'Halo! Saya NutriBot AI. Ada yang bisa saya bantu seputar gizi dan kesehatan Anda hari ini?',
              isUser: false,
              timestamp: _getCurrentTimestamp(),
            ),
          ],
        );
        debugPrint("[NUTRIBOT-FE] Initialized default greeting.");
      }
    } catch (e, stack) {
      debugPrint("[NUTRIBOT-FE] Error loading chat history: $e\n$stack");
    }
  }

  Future<void> _saveHistory() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final historyJson = jsonEncode(state.messages.map((m) => m.toJson()).toList());
      await prefs.setString(_historyKey, historyJson);
    } catch (e, stack) {
      debugPrint("[NUTRIBOT-FE] Error saving chat history: $e\n$stack");
    }
  }

  String _getCurrentTimestamp() {
    final now = DateTime.now();
    return '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';
  }

  void setProfile(String profileName) {
    state = state.copyWith(currentProfile: profileName);
    debugPrint("[NUTRIBOT-FE] Switched chat target profile to: $profileName");
  }

  Future<void> clearHistory() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_historyKey);
    state = state.copyWith(messages: []);
    debugPrint("[NUTRIBOT-FE] Chat history cleared.");
    _loadHistory();
  }

  /// Mengambil context personal user dari API untuk menjawab pertanyaan personal
  Future<Map<String, dynamic>> _fetchPersonalContext(String token) async {
    try {
      final today = DateFormat('yyyy-MM-dd').format(DateTime.now());
      
      // 1. Ambil dashboard summary hari ini
      final summaryUri = Uri.parse(ApiConstants.dashboardSummary).replace(
        queryParameters: {
          'target_type': 'MOTHER',
        },
      );
      
      final summaryResponse = await http.get(
        summaryUri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      // 2. Ambil food logs hari ini
      final foodLogsUri = Uri.parse(ApiConstants.foodLogs).replace(
        queryParameters: {
          'start_date': today,
          'end_date': today,
        },
      );
      
      final foodLogsResponse = await http.get(
        foodLogsUri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      Map<String, dynamic> context = {
        'date': today,
        'has_data': false,
      };

      // Parse summary data
      if (summaryResponse.statusCode == 200) {
        final summaryData = jsonDecode(summaryResponse.body);
        final summary = summaryData['today_summary'] ?? summaryData['summary'] ?? {};
        final targets = summaryData['daily_targets'] ?? {};
        
        context['summary'] = {
          'current_calories': summary['calories'] ?? summary['current_calories'] ?? 0,
          'target_calories': targets['calories'] ?? 2000,
          'current_protein': summary['protein'] ?? summary['protein_g'] ?? 0,
          'target_protein': targets['protein'] ?? 100,
          'current_carbs': summary['carbs'] ?? summary['carbs_g'] ?? 0,
          'target_carbs': targets['carbs'] ?? 250,
          'current_sugar': summary['sugar'] ?? summary['sugar_g'] ?? 0,
          'target_sugar': targets['sugar'] ?? 50,
          'current_fiber': summary['fiber'] ?? summary['fiber_g'] ?? 0,
          'target_fiber': targets['fiber'] ?? 30,
        };
        context['has_data'] = true;
      }

      // Parse food logs
      if (foodLogsResponse.statusCode == 200) {
        final foodData = jsonDecode(foodLogsResponse.body);
        final List<dynamic> logs = foodData['data'] ?? [];
        
        context['meals'] = logs.map((log) {
          final mealTime = DateTime.parse(log['meal_time']);
          return {
            'name': log['food_name_detected'] ?? 'Makanan',
            'time': DateFormat('HH:mm').format(mealTime),
            'calories': log['calories_kcal'] ?? log['calories'] ?? 0,
            'protein': log['protein_g'] ?? log['protein'] ?? 0,
            'carbs': log['carbs_g'] ?? log['carbs'] ?? 0,
            'fat': log['fat_g'] ?? log['fat'] ?? 0,
            'fiber': log['fiber_g'] ?? log['fiber'] ?? 0,
            'recommendation': log['recommendation_status'] ?? 'UNKNOWN',
          };
        }).toList();
        
        context['meal_count'] = logs.length;
      }

      debugPrint("[NUTRIBOT-FE] Personal context fetched: ${context.keys}");
      return context;
      
    } catch (e, stack) {
      debugPrint("[NUTRIBOT-FE] Error fetching personal context: $e\n$stack");
      return {'has_data': false, 'error': e.toString()};
    }
  }

  Future<void> sendMessage(String text) async {
    if (text.trim().isEmpty) return;

    final timeString = _getCurrentTimestamp();

    final userMessage = ChatMessage(text: text, isUser: true, timestamp: timeString);
    state = state.copyWith(
      messages: [...state.messages, userMessage],
      isLoading: true,
    );
    _saveHistory();

    debugPrint("[NUTRIBOT-FE] Sending message: \"$text\" for profile: ${state.currentProfile}");

    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      // Ambil context personal user
      final personalContext = await _fetchPersonalContext(token);

      final response = await http.post(
        Uri.parse(ApiConstants.chatbot),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: jsonEncode({
          'message': text,
          'target_profile': state.currentProfile,
          'personal_context': personalContext, // Kirim data personal ke backend
        }),
      );

      debugPrint("[NUTRIBOT-FE] Response status: ${response.statusCode}");

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final botReply = data['reply'] ?? data['message'] ?? 'Tidak ada tanggapan.';
        debugPrint("[NUTRIBOT-FE] AI Reply: \"$botReply\"");

        final botMessage = ChatMessage(
          text: botReply,
          isUser: false,
          timestamp: _getCurrentTimestamp(),
        );

        state = state.copyWith(
          messages: [...state.messages, botMessage],
          isLoading: false,
        );
        _saveHistory();
      } else {
        debugPrint("[NUTRIBOT-FE] API Error response: ${response.statusCode} - ${response.body}");
        throw Exception('Gagal mendapatkan balasan dari AI Server: ${response.statusCode}');
      }
    } catch (e, stack) {
      debugPrint("[NUTRIBOT-FE] Chatbot Exception: $e\n$stack");
      final errorMessage = ChatMessage(
        text: 'Maaf, terjadi gangguan koneksi. Pastikan server aktif.',
        isUser: false,
        timestamp: timeString,
      );
      state = state.copyWith(
        messages: [...state.messages, errorMessage],
        isLoading: false,
      );
    }
  }
}

