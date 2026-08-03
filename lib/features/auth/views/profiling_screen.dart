import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/widgets/custom_text_field.dart';
import '../../../core/widgets/step_progress_indicator.dart';
import '../../profile/providers/profile_provider.dart';
import '../../../routes/app_routes.dart';

class ProfilingScreen extends ConsumerStatefulWidget {
  const ProfilingScreen({super.key});

  @override
  ConsumerState<ProfilingScreen> createState() => _ProfilingScreenState();
}

class _ProfilingScreenState extends ConsumerState<ProfilingScreen> {
  final PageController _pageController = PageController();
  int _currentStep = 1;

  // Step 1 - Data Dasar
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _ageController = TextEditingController();
  String _gender = 'L';
  final TextEditingController _heightController = TextEditingController();
  final TextEditingController _weightController = TextEditingController();
  double _bmi = 0.0;

  // Step 2 - Kondisi Kesehatan
  String _diabetesStatus = 'not_diagnosed';
  bool _familyDiabetesHistory = false;
  bool? _hypertension;
  List<String> _selectedAllergies = [];
  final List<String> _commonAllergies = [
    'Kacang',
    'Susu Sapi',
    'Telur',
    'Seafood',
    'Gluten',
    'Kedelai',
  ];

  // Step 3 - Target Kesehatan
  List<String> _selectedTargets = [];
  final Map<String, String> _healthTargets = {
    'stable_blood_sugar': 'Menjaga kadar gula darah tetap stabil',
    'reduce_sugar_intake': 'Mengurangi konsumsi gula harian',
    'control_carbs': 'Mengontrol asupan karbohidrat',
    'lose_weight': 'Menurunkan berat badan secara sehat',
    'healthy_diet': 'Menjaga pola makan yang lebih sehat',
  };

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await ref.read(profileProvider.notifier).fetchProfile();
      _populateUserData();
    });
  }

  void _populateUserData() {
    final profile = ref.read(profileProvider);
    if (_nameController.text.isEmpty && profile.motherName.isNotEmpty && profile.motherName != 'Loading...') {
      _nameController.text = profile.motherName;
    }
    if (_ageController.text.isEmpty) {
      if (profile.motherBirthDate != null) {
        final dob = profile.motherBirthDate!;
        final now = DateTime.now();
        int age = now.year - dob.year;
        if (now.month < dob.month || (now.month == dob.month && now.day < dob.day)) {
          age--;
        }
        _ageController.text = (age > 0 ? age : 25).toString();
      } else {
        _ageController.text = '25';
      }
    }
  }

  @override
  void dispose() {
    _pageController.dispose();
    _nameController.dispose();
    _ageController.dispose();
    _heightController.dispose();
    _weightController.dispose();
    super.dispose();
  }

  void _calculateBMI() {
    final height = double.tryParse(_heightController.text) ?? 0;
    final weight = double.tryParse(_weightController.text) ?? 0;
    
    if (height > 0 && weight > 0) {
      setState(() {
        _bmi = weight / ((height / 100) * (height / 100));
      });
    }
  }

  void _nextStep() {
    if (_currentStep < 3) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
      setState(() => _currentStep++);
    }
  }

  void _previousStep() {
    if (_currentStep > 1) {
      _pageController.previousPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
      setState(() => _currentStep--);
    }
  }

  Future<void> _submitProfiling() async {
    // Step 1 - Basic Data
    final step1Result = await ref.read(profileProvider.notifier).saveBasicData(
      name: _nameController.text,
      age: int.parse(_ageController.text),
      gender: _gender,
      heightCm: double.parse(_heightController.text),
      weightKg: double.parse(_weightController.text),
    );

    if (!step1Result) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Gagal menyimpan data dasar')),
        );
      }
      return;
    }

    // Step 2 - Health Condition
    final step2Result = await ref.read(profileProvider.notifier).updateHealthCondition(
      diabetesStatus: _diabetesStatus,
      familyDiabetesHistory: _familyDiabetesHistory,
      hypertension: _hypertension,
      foodAllergies: _selectedAllergies,
    );

    if (!step2Result) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Gagal menyimpan kondisi kesehatan')),
        );
      }
      return;
    }

    // Step 3 - Health Targets
    final step3Result = await ref.read(profileProvider.notifier).updateHealthTargets(
      targets: _selectedTargets,
    );

    if (step3Result && mounted) {
      // Success - pindah ke dashboard
      context.go(AppRoutes.dashboard);
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Gagal menyimpan target kesehatan')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: _currentStep > 1
            ? IconButton(
                icon: const Icon(Icons.arrow_back, color: AppColors.primary),
                onPressed: _previousStep,
              )
            : null,
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24.0),
            child: StepProgressIndicator(currentStep: _currentStep, totalSteps: 3),
          ),
          const SizedBox(height: 24),
          Expanded(
            child: PageView(
              controller: _pageController,
              physics: const NeverScrollableScrollPhysics(),
              children: [
                _buildStep1BasicData(),
                _buildStep2HealthCondition(),
                _buildStep3HealthTargets(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Step 1 - Data Dasar
  Widget _buildStep1BasicData() {
    _populateUserData();
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Data Dasar',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          const Text(
            'Informasi dasar untuk kalkulasi kebutuhan nutrisi (Nama & Usia terisi otomatis)',
            style: TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 32),
          CustomTextField(
            controller: _nameController,
            label: 'Nama Lengkap',
            hint: 'Masukkan nama lengkap',
            prefixIcon: Icons.person_outline,
          ),
          const SizedBox(height: 16),
          CustomTextField(
            controller: _ageController,
            label: 'Usia (Tahun)',
            hint: 'Masukkan usia (tahun)',
            prefixIcon: Icons.cake_outlined,
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 16),
          const Text('Jenis Kelamin', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: GestureDetector(
                  onTap: () => setState(() => _gender = 'L'),
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: _gender == 'L' ? AppColors.primary.withOpacity(0.1) : Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: _gender == 'L' ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3),
                        width: _gender == 'L' ? 2 : 1,
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.male,
                          color: _gender == 'L' ? AppColors.primary : AppColors.textSecondary,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'Laki-laki',
                          style: TextStyle(
                            color: _gender == 'L' ? AppColors.primary : AppColors.textSecondary,
                            fontWeight: _gender == 'L' ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: GestureDetector(
                  onTap: () => setState(() => _gender = 'P'),
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: _gender == 'P' ? AppColors.primary.withOpacity(0.1) : Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: _gender == 'P' ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3),
                        width: _gender == 'P' ? 2 : 1,
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.female,
                          color: _gender == 'P' ? AppColors.primary : AppColors.textSecondary,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'Perempuan',
                          style: TextStyle(
                            color: _gender == 'P' ? AppColors.primary : AppColors.textSecondary,
                            fontWeight: _gender == 'P' ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          CustomTextField(
            controller: _heightController,
            label: 'Tinggi Badan (cm)',
            hint: 'Contoh: 165',
            prefixIcon: Icons.height,
            keyboardType: TextInputType.number,
            onChanged: (_) => _calculateBMI(),
          ),
          const SizedBox(height: 16),
          CustomTextField(
            controller: _weightController,
            label: 'Berat Badan (kg)',
            hint: 'Contoh: 60',
            prefixIcon: Icons.monitor_weight_outlined,
            keyboardType: TextInputType.number,
            onChanged: (_) => _calculateBMI(),
          ),
          if (_bmi > 0) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'BMI (Body Mass Index)',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  Text(
                    _bmi.toStringAsFixed(1),
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 32),
          SizedBox(
            width: double.infinity,
            height: 56,
            child: ElevatedButton(
              onPressed: () {
                if (_nameController.text.isEmpty ||
                    _ageController.text.isEmpty ||
                    _heightController.text.isEmpty ||
                    _weightController.text.isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Harap isi semua bidang')),
                  );
                  return;
                }
                _nextStep();
              },
              child: const Text('Lanjut', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ),
          ),
        ],
      ),
    );
  }

  // Step 2 - Kondisi Kesehatan
  Widget _buildStep2HealthCondition() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Kondisi Kesehatan',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          const Text(
            'Informasi ini membantu kami memberikan rekomendasi yang tepat',
            style: TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 32),
          const Text('Status Diabetes', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          _buildDiabetesOption('dm_type_1', 'Diabetes Mellitus Tipe 1'),
          _buildDiabetesOption('dm_type_2', 'Diabetes Mellitus Tipe 2'),
          _buildDiabetesOption('prediabetes', 'Prediabetes'),
          _buildDiabetesOption('not_diagnosed', 'Belum pernah didiagnosis'),
          const SizedBox(height: 24),
          const Text('Riwayat Keluarga Diabetes', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _buildYesNoButton(true, _familyDiabetesHistory, () {
                  setState(() => _familyDiabetesHistory = true);
                }),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildYesNoButton(false, !_familyDiabetesHistory, () {
                  setState(() => _familyDiabetesHistory = false);
                }),
              ),
            ],
          ),
          const SizedBox(height: 24),
          const Text('Hipertensi (Opsional)', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _buildOptionalButton('Ya', _hypertension == true, () {
                  setState(() => _hypertension = true);
                }),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildOptionalButton('Tidak', _hypertension == false, () {
                  setState(() => _hypertension = false);
                }),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildOptionalButton('Tidak Tahu', _hypertension == null, () {
                  setState(() => _hypertension = null);
                }),
              ),
            ],
          ),
          const SizedBox(height: 24),
          const Text('Alergi Makanan (Opsional)', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _commonAllergies.map((allergy) {
              final isSelected = _selectedAllergies.contains(allergy);
              return GestureDetector(
                onTap: () {
                  setState(() {
                    if (isSelected) {
                      _selectedAllergies.remove(allergy);
                    } else {
                      _selectedAllergies.add(allergy);
                    }
                  });
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: isSelected ? AppColors.primary : Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isSelected ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3),
                    ),
                  ),
                  child: Text(
                    allergy,
                    style: TextStyle(
                      color: isSelected ? Colors.white : AppColors.textPrimary,
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
          const SizedBox(height: 32),
          SizedBox(
            width: double.infinity,
            height: 56,
            child: ElevatedButton(
              onPressed: _nextStep,
              child: const Text('Lanjut', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDiabetesOption(String value, String label) {
    final isSelected = _diabetesStatus == value;
    return GestureDetector(
      onTap: () => setState(() => _diabetesStatus = value),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primary.withOpacity(0.1) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3),
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Icon(
              isSelected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
              color: isSelected ? AppColors.primary : AppColors.textSecondary,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  color: isSelected ? AppColors.primary : AppColors.textPrimary,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildYesNoButton(bool isYes, bool isSelected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primary.withOpacity(0.1) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3),
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Center(
          child: Text(
            isYes ? 'Ya' : 'Tidak',
            style: TextStyle(
              color: isSelected ? AppColors.primary : AppColors.textPrimary,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildOptionalButton(String label, bool isSelected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primary.withOpacity(0.1) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3),
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Center(
          child: Text(
            label,
            style: TextStyle(
              fontSize: 13,
              color: isSelected ? AppColors.primary : AppColors.textPrimary,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ),
      ),
    );
  }

  // Step 3 - Target Kesehatan
  Widget _buildStep3HealthTargets() {
    final profileState = ref.watch(profileProvider);
    
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Target Kesehatan',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          const Text(
            'Pilih satu atau lebih tujuan kesehatan Anda',
            style: TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 32),
          ..._healthTargets.entries.map((entry) {
            final isSelected = _selectedTargets.contains(entry.key);
            return GestureDetector(
              onTap: () {
                setState(() {
                  if (isSelected) {
                    _selectedTargets.remove(entry.key);
                  } else {
                    _selectedTargets.add(entry.key);
                  }
                });
              },
              child: Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: isSelected ? AppColors.primary.withOpacity(0.1) : Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isSelected ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3),
                    width: isSelected ? 2 : 1,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      isSelected ? Icons.check_box : Icons.check_box_outline_blank,
                      color: isSelected ? AppColors.primary : AppColors.textSecondary,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        entry.value,
                        style: TextStyle(
                          color: isSelected ? AppColors.primary : AppColors.textPrimary,
                          fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),
          const SizedBox(height: 32),
          SizedBox(
            width: double.infinity,
            height: 56,
            child: ElevatedButton(
              onPressed: profileState.isLoading
                  ? null
                  : () {
                      if (_selectedTargets.isEmpty) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Pilih minimal satu target kesehatan')),
                        );
                        return;
                      }
                      _submitProfiling();
                    },
              child: profileState.isLoading
                  ? const SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                      ),
                    )
                  : const Text(
                      'Selesai & Mulai',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
            ),
          ),
          if (profileState.errorMessage != null) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.red.withOpacity(0.3)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.error_outline, color: Colors.red, size: 20),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      profileState.errorMessage!,
                      style: const TextStyle(color: Colors.red, fontSize: 12),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
