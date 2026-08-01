import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/widgets/custom_text_field.dart';
import '../../../core/widgets/allergy_chip.dart';
import '../providers/auth_provider.dart';
import '../../../routes/app_routes.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _confirmPasswordController = TextEditingController();

  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _isLoading = false;

  final List<String> _commonAllergies = [
    'Kacang',
    'Susu Sapi',
    'Telur',
    'Seafood',
    'Gluten',
    'Udang',
    'Ikan',
    'Kedelai',
  ];

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  void _submitRegister() async {
    final state = ref.read(registerFormProvider);
    final notifier = ref.read(registerFormProvider.notifier);

    // Synchronize text values to state
    notifier.updateForm(
      fullName: _nameController.text.trim(),
      email: _emailController.text.trim(),
      password: _passwordController.text,
    );

    final name = _nameController.text.trim();
    final email = _emailController.text.trim();
    final password = _passwordController.text;
    final confirmPassword = _confirmPasswordController.text;
    final dob = state.dob;

    if (name.isEmpty || email.isEmpty || password.isEmpty || confirmPassword.isEmpty || dob.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Harap lengkapi semua bidang (Nama, Email, Password, dan Tanggal Lahir)'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    if (password != confirmPassword) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Konfirmasi password tidak sesuai'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    // Password rules validation
    final hasUppercase = RegExp(r'[A-Z]').hasMatch(password);
    final hasLowercase = RegExp(r'[a-z]').hasMatch(password);
    final hasDigits = RegExp(r'[0-9]').hasMatch(password);
    final hasSpecialCharacters = RegExp(r'[!@#$%^&*(),.?":{}|<>]').hasMatch(password);
    final hasMinLength = password.length >= 8;

    if (!hasMinLength || !hasUppercase || !hasLowercase || !hasDigits || !hasSpecialCharacters) {
      String error = 'Password harus mengandung:';
      if (!hasMinLength) error += '\n• Minimal 8 karakter';
      if (!hasUppercase) error += '\n• Huruf besar (A-Z)';
      if (!hasLowercase) error += '\n• Huruf kecil (a-z)';
      if (!hasDigits) error += '\n• Angka (0-9)';
      if (!hasSpecialCharacters) error += '\n• Karakter spesial (!@#\$ dll)';

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error),
          duration: const Duration(seconds: 4),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    setState(() => _isLoading = true);

    final result = await notifier.submitRegister();

    setState(() => _isLoading = false);

    if (!mounted) return;

    if (result['success'] == true) {
      if (result['requires_activation'] == true) {
        context.push(AppRoutes.otpVerification, extra: email);
      } else {
        context.go(AppRoutes.dashboard);
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? 'Registrasi gagal'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _showAddAllergyDialog() {
    String customAllergy = '';
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Tambah Alergi Makanan'),
          content: TextField(
            autofocus: true,
            onChanged: (val) => customAllergy = val,
            decoration: const InputDecoration(
              hintText: 'Contoh: Kepiting, Kerang, dll...',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Batal'),
            ),
            ElevatedButton(
              onPressed: () {
                final trimmed = customAllergy.trim();
                if (trimmed.isNotEmpty) {
                  final state = ref.read(registerFormProvider);
                  final current = List<String>.from(state.allergies);
                  if (!current.contains(trimmed)) {
                    current.add(trimmed);
                    ref.read(registerFormProvider.notifier).updateForm(allergies: current);
                  }
                }
                Navigator.pop(context);
              },
              child: const Text('Tambah'),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(registerFormProvider);
    final notifier = ref.read(registerFormProvider.notifier);
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.primary),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go(AppRoutes.login);
            }
          },
        ),
        title: const Text(
          'Daftar Akun SINUTRI',
          style: TextStyle(
            color: AppColors.primary,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Stack(
          children: [
            SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withOpacity(0.12),
                            blurRadius: 16,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: Image.asset(
                        'assets/images/logo.png',
                        width: 56,
                        height: 56,
                        errorBuilder: (context, error, stackTrace) => const Icon(
                          Icons.eco,
                          size: 56,
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Center(
                    child: Text(
                      'Mulai Perjalanan Nutrisi Anda',
                      style: textTheme.titleLarge?.copyWith(
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Center(
                    child: Text(
                      'Lengkapi data diri Anda untuk rekomendasi nutrisi personal.',
                      textAlign: TextAlign.center,
                      style: textTheme.bodyMedium?.copyWith(
                        color: AppColors.textSecondary,
                        fontSize: 13,
                      ),
                    ),
                  ),
                  const SizedBox(height: 28),

                  // 1. Nama Lengkap
                  CustomTextField(
                    controller: _nameController,
                    label: 'Nama Lengkap',
                    hint: 'Masukkan nama lengkap Anda',
                    prefixIcon: Icons.person_outline,
                    onChanged: (val) => notifier.updateForm(fullName: val),
                  ),
                  const SizedBox(height: 16),

                  // 2. Email
                  CustomTextField(
                    controller: _emailController,
                    label: 'Alamat Email',
                    hint: 'contoh@email.com',
                    prefixIcon: Icons.email_outlined,
                    keyboardType: TextInputType.emailAddress,
                    onChanged: (val) => notifier.updateForm(email: val),
                  ),
                  const SizedBox(height: 16),

                  // 3. Password & Confirm Password
                  CustomTextField(
                    controller: _passwordController,
                    label: 'Password',
                    hint: 'Minimal 8 karakter',
                    prefixIcon: Icons.lock_outline,
                    isPassword: _obscurePassword,
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                        color: AppColors.textSecondary,
                      ),
                      onPressed: () {
                        setState(() => _obscurePassword = !_obscurePassword);
                      },
                    ),
                    onChanged: (val) => notifier.updateForm(password: val),
                  ),
                  const SizedBox(height: 16),
                  CustomTextField(
                    controller: _confirmPasswordController,
                    label: 'Konfirmasi Password',
                    hint: 'Ulangi password Anda',
                    prefixIcon: Icons.lock_reset_outlined,
                    isPassword: _obscureConfirmPassword,
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscureConfirmPassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                        color: AppColors.textSecondary,
                      ),
                      onPressed: () {
                        setState(() => _obscureConfirmPassword = !_obscureConfirmPassword);
                      },
                    ),
                  ),
                  const SizedBox(height: 16),

                  // 4. Tanggal Lahir
                  CustomTextField(
                    key: ValueKey(state.dob),
                    label: 'Tanggal Lahir',
                    hint: 'mm/dd/yyyy',
                    prefixIcon: Icons.calendar_today_outlined,
                    initialValue: state.dob.isEmpty ? null : state.dob,
                    readOnly: true,
                    onTap: () async {
                      final now = DateTime.now();
                      final initial = state.dob.isNotEmpty && state.dob.contains('/')
                          ? DateTime(
                              int.parse(state.dob.split('/')[2]),
                              int.parse(state.dob.split('/')[0]),
                              int.parse(state.dob.split('/')[1]),
                            )
                          : now.subtract(const Duration(days: 365 * 20));

                      final selected = await showDatePicker(
                        context: context,
                        initialDate: initial,
                        firstDate: DateTime(1920),
                        lastDate: now,
                      );

                      if (selected != null) {
                        final formatted =
                            "${selected.month.toString().padLeft(2, '0')}/${selected.day.toString().padLeft(2, '0')}/${selected.year}";
                        notifier.updateForm(dob: formatted);
                      }
                    },
                  ),
                  const SizedBox(height: 24),

                  // 5. Alergi Makanan
                  const Text(
                    'Riwayat Alergi Makanan',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Pilih alergi makanan yang Anda miliki (apabila ada):',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      ..._commonAllergies.map((allergy) {
                        final isSelected = state.allergies.contains(allergy);
                        return AllergyChip(
                          label: allergy,
                          isSelected: isSelected,
                          onTap: () {
                            final current = List<String>.from(state.allergies);
                            if (isSelected) {
                              current.remove(allergy);
                            } else {
                              current.add(allergy);
                            }
                            notifier.updateForm(allergies: current);
                          },
                        );
                      }),
                      ...state.allergies
                          .where((a) => !_commonAllergies.contains(a))
                          .map((customAllergy) {
                        return AllergyChip(
                          label: customAllergy,
                          isSelected: true,
                          onTap: () {
                            final current = List<String>.from(state.allergies);
                            current.remove(customAllergy);
                            notifier.updateForm(allergies: current);
                          },
                        );
                      }),
                      AllergyChip(
                        label: '+ Tambah Lainnya',
                        isSelected: false,
                        isAddButton: true,
                        onTap: _showAddAllergyDialog,
                      ),
                    ],
                  ),
                  const SizedBox(height: 36),

                  // Submit Button
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton(
                      onPressed: _isLoading ? null : _submitRegister,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                        elevation: 2,
                      ),
                      child: _isLoading
                          ? const SizedBox(
                              width: 24,
                              height: 24,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                              ),
                            )
                          : const Text(
                              'Daftar Sekarang',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                              ),
                            ),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Login Link
                  Center(
                    child: GestureDetector(
                      onTap: () => context.go(AppRoutes.login),
                      child: RichText(
                        text: const TextSpan(
                          text: 'Sudah punya akun? ',
                          style: TextStyle(color: AppColors.textSecondary, fontSize: 14),
                          children: [
                            TextSpan(
                              text: 'Masuk di sini',
                              style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
            if (_isLoading)
              Container(
                color: Colors.black.withOpacity(0.2),
              ),
          ],
        ),
      ),
    );
  }
}
