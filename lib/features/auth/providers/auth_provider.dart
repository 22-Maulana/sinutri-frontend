import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../core/constants/api_constants.dart';
import '../models/register_form_state.dart';

// Provider untuk mengelola form registrasi bertahap
final registerFormProvider = StateNotifierProvider<RegisterFormNotifier, RegisterFormState>((ref) {
  return RegisterFormNotifier();
});

class RegisterFormNotifier extends StateNotifier<RegisterFormState> {
  RegisterFormNotifier() : super(RegisterFormState());

  void updateForm({
    String? fullName,
    String? email,
    String? password,
    String? dob,
    List<String>? allergies,
  }) {
    state = state.copyWith(
      fullName: fullName,
      email: email,
      password: password,
      dob: dob,
      allergies: allergies,
    );
  }

  Future<Map<String, dynamic>> submitRegister() async {
    try {
      debugPrint("[AUTH-REGISTER-FE] Memulai proses Submit Registrasi untuk email: ${state.email}");
      String? formattedDob;
      if (state.dob.contains('/')) {
        final parts = state.dob.split('/');
        if (parts.length == 3) {
          formattedDob = "${parts[2]}-${parts[0].padLeft(2, '0')}-${parts[1].padLeft(2, '0')}";
        }
      } else {
        formattedDob = state.dob.isNotEmpty ? state.dob : null;
      }

      final payload = {
        'name': state.fullName,
        'email': state.email,
        'password': state.password,
        'password_confirmation': state.password,
        'birth_date': formattedDob,
        'dob': formattedDob,
        'allergies': state.allergies,
        'food_allergies': state.allergies,
      };

      debugPrint("[AUTH-REGISTER-FE] Payload: ${jsonEncode(payload)}");

      final response = await http.post(
        Uri.parse(ApiConstants.register),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode(payload),
      );

      debugPrint("[AUTH-REGISTER-FE] Response Status Code: ${response.statusCode}");
      debugPrint("[AUTH-REGISTER-FE] Response Body: ${response.body}");

      final data = jsonDecode(response.body);
      
      if (response.statusCode == 201) {
        debugPrint("[AUTH-REGISTER-FE] Registrasi Berhasil!");
        return {
          'success': true,
          'requires_activation': data['requires_activation'] ?? false,
          'email': state.email,
        };
      } else {
        String msg = data['message'] ?? 'Registrasi gagal';
        if (data['errors'] != null && data['errors'] is Map) {
          final errMap = data['errors'] as Map<String, dynamic>;
          if (errMap.isNotEmpty) {
            msg = errMap.values.first.toString().replaceAll('[', '').replaceAll(']', '');
          }
        }
        debugPrint("[AUTH-REGISTER-FE] Registrasi Gagal: $msg");
        return {
          'success': false,
          'message': msg,
          'errors': data['errors']
        };
      }
    } catch (e, stack) {
      debugPrint("[AUTH-REGISTER-FE] Exception pada Registrasi: $e\n$stack");
      return {'success': false, 'message': 'Gagal terhubung ke server: $e'};
    }
  }
}

// Provider untuk mengelola login dan status autentikasi umum
final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier();
});

class AuthState {
  final bool isLoading;
  final String? errorMessage;
  final bool isAuthenticated;

  AuthState({
    this.isLoading = false,
    this.errorMessage,
    this.isAuthenticated = false,
  });

  AuthState copyWith({
    bool? isLoading,
    String? errorMessage,
    bool? isAuthenticated,
  }) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      errorMessage: errorMessage,
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier() : super(AuthState());

  // Register method untuk diabetes system
  Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    debugPrint("[AUTH-FE] Calling register for email: $email");
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.register),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'name': name,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirmation,
        }),
      );

      debugPrint("[AUTH-FE] Register response status: ${response.statusCode}, body: ${response.body}");
      final data = jsonDecode(response.body);

      if (response.statusCode == 201) {
        state = state.copyWith(isLoading: false);
        return {
          'success': true,
          'requires_activation': data['requires_activation'] ?? false,
          'email': email,
          'debug_otp': data['debug_otp'],
        };
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: data['message'] ?? 'Registrasi gagal',
        );
        return {
          'success': false,
          'message': data['message'] ?? 'Registrasi gagal',
          'errors': data['errors'],
        };
      }
    } catch (e, stack) {
      debugPrint("[AUTH-FE] Exception register: $e\n$stack");
      state = state.copyWith(isLoading: false, errorMessage: 'Gagal terhubung ke server: $e');
      return {'success': false, 'message': 'Gagal terhubung ke server: $e'};
    }
  }

  Future<Map<String, dynamic>> verifyOtp({
    required String email,
    required String otpCode,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    debugPrint("[AUTH-OTP-FE] Verifikasi OTP untuk email: $email, code: $otpCode");
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.verifyOtp),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'email': email,
          'otp_code': otpCode,
        }),
      );

      debugPrint("[AUTH-OTP-FE] Verify OTP status: ${response.statusCode}, body: ${response.body}");
      final data = jsonDecode(response.body);

      if (response.statusCode == 200) {
        final token = data['token'];
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', token);
        await prefs.setString('token', token);
        
        state = state.copyWith(isLoading: false, isAuthenticated: true);
        debugPrint("[AUTH-OTP-FE] Verifikasi OTP Sukses! Token tersimpan.");
        return {'success': true, 'token': token};
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: data['message'] ?? 'Verifikasi OTP gagal',
        );
        debugPrint("[AUTH-OTP-FE] Verifikasi OTP Gagal: ${data['message']}");
        return {
          'success': false,
          'message': data['message'] ?? 'Verifikasi OTP gagal',
        };
      }
    } catch (e, stack) {
      debugPrint("[AUTH-OTP-FE] Exception verifyOtp: $e\n$stack");
      state = state.copyWith(isLoading: false, errorMessage: 'Gagal terhubung ke server: $e');
      return {'success': false, 'message': 'Gagal terhubung ke server: $e'};
    }
  }

  Future<Map<String, dynamic>> resendOtp({required String email}) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    debugPrint("[AUTH-OTP-FE] Kirim ulang OTP ke email: $email");
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.resendOtp),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({'email': email}),
      );

      debugPrint("[AUTH-OTP-FE] Resend OTP status: ${response.statusCode}");
      final data = jsonDecode(response.body);

      if (response.statusCode == 200) {
        state = state.copyWith(isLoading: false);
        return {
          'success': true,
          'message': data['message'],
          'debug_otp': data['debug_otp'],
        };
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: data['message'] ?? 'Gagal mengirim ulang OTP',
        );
        return {
          'success': false,
          'message': data['message'] ?? 'Gagal mengirim ulang OTP',
        };
      }
    } catch (e, stack) {
      debugPrint("[AUTH-OTP-FE] Exception resendOtp: $e\n$stack");
      state = state.copyWith(isLoading: false, errorMessage: 'Gagal terhubung ke server: $e');
      return {'success': false, 'message': 'Gagal terhubung ke server: $e'};
    }
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    debugPrint("[AUTH-LOGIN-FE] Memulai Login untuk email: $email");
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.login),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      );

      debugPrint("[AUTH-LOGIN-FE] Login response status: ${response.statusCode}, body: ${response.body}");
      final data = jsonDecode(response.body);

      if (response.statusCode == 200) {
        final token = data['token'];
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', token);
        await prefs.setString('token', token);
        
        state = state.copyWith(isLoading: false, isAuthenticated: true);
        debugPrint("[AUTH-LOGIN-FE] Login Sukses! Token tersimpan.");
        return {'success': true};
      } else if (response.statusCode == 403 && data['requires_activation'] == true) {
        state = state.copyWith(isLoading: false);
        debugPrint("[AUTH-LOGIN-FE] User membutuhkan aktivasi OTP.");
        return {
          'success': false,
          'requires_activation': true,
          'email': email,
          'message': data['message'],
          'debug_otp': data['debug_otp'],
        };
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: data['message'] ?? 'Login gagal',
        );
        debugPrint("[AUTH-LOGIN-FE] Login Gagal: ${data['message']}");
        return {'success': false, 'message': data['message'] ?? 'Login gagal'};
      }
    } catch (e, stack) {
      debugPrint("[AUTH-LOGIN-FE] Exception Login: $e\n$stack");
      state = state.copyWith(isLoading: false, errorMessage: 'Gagal terhubung ke server');
      return {'success': false, 'message': 'Gagal terhubung ke server'};
    }
  }

  Future<void> logout() async {
    debugPrint("[AUTH-LOGOUT-FE] User melakukan Logout");
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('auth_token') ?? prefs.getString('token');
    if (token != null && token.isNotEmpty) {
      try {
        await http.post(
          Uri.parse('${ApiConstants.apiBaseUrl}/logout'),
          headers: {
            'Authorization': 'Bearer $token',
            'Accept': 'application/json',
          },
        );
      } catch (e, stack) {
        debugPrint("[AUTH-LOGOUT-FE] Error logout server: $e\n$stack");
      }
    }
    await prefs.remove('auth_token');
    await prefs.remove('token');
    state = state.copyWith(isAuthenticated: false);
    debugPrint("[AUTH-LOGOUT-FE] Token removed, status logged out.");
  }
}
