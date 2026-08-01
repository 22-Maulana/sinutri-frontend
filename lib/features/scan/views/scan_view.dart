import 'dart:io';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../providers/scan_provider.dart';
import '../models/scan_request_model.dart';
import '../../profile/providers/profile_provider.dart';
import '../../../routes/app_routes.dart';

class ScanView extends ConsumerStatefulWidget {
  const ScanView({super.key});

  @override
  ConsumerState<ScanView> createState() => _ScanViewState();
}

class _ScanViewState extends ConsumerState<ScanView> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final TextEditingController _notesController = TextEditingController();
  final TextEditingController _descriptionController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _notesController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  void _showErrorSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            const Icon(Icons.error_outline, color: Colors.white),
            const SizedBox(width: 12),
            Expanded(child: Text(message)),
          ],
        ),
        backgroundColor: Colors.red.shade700,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  void _runScanProcess({required bool isPhoto}) async {
    final imagePath = ref.read(scanProvider).imagePath;
    final description = ref.read(scanProvider).description;

    if (isPhoto && (imagePath == null || imagePath.isEmpty)) {
      _showErrorSnackBar("Harap pilih atau ambil foto makanan terlebih dahulu.");
      return;
    }
    if (!isPhoto && description.trim().isEmpty) {
      _showErrorSnackBar("Harap tuliskan deskripsi makanan terlebih dahulu.");
      return;
    }

    final stages = [
      (text: "1/5: Mengunggah data & mendeteksi objek makanan...", icon: Icons.camera_alt_outlined),
      (text: "2/5: Mengekstraksi komposisi bahan & query DB TKPI (Vector RAG)...", icon: Icons.inventory_2_outlined),
      (text: "3/5: Menghitung kalori, makronutrien & Indeks Glikemik...", icon: Icons.calculate_outlined),
      (text: "4/5: Menyusun rekomendasi DSS & Saran AI...", icon: Icons.psychology_outlined),
      (text: "5/5: Analisis selesai! Menampilkan hasil...", icon: Icons.check_circle_outline),
    ];

    double currentProgress = 0.12;
    int currentStageIndex = 0;
    String currentStageText = stages[0].text;
    IconData currentStageIcon = stages[0].icon;
    String? errorMessage;
    bool isFailed = false;

    Timer? progressTimer;
    void Function(void Function())? updateDialogState;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (ctx, setDialogState) {
            updateDialogState = setDialogState;

            progressTimer ??= Timer.periodic(const Duration(milliseconds: 650), (timer) {
              if (isFailed || currentProgress >= 0.90) return;

              setDialogState(() {
                currentProgress += 0.08;
                if (currentProgress >= 0.85) {
                  currentStageIndex = 3;
                } else if (currentProgress >= 0.60) {
                  currentStageIndex = 2;
                } else if (currentProgress >= 0.35) {
                  currentStageIndex = 1;
                }
                currentStageText = stages[currentStageIndex].text;
                currentStageIcon = stages[currentStageIndex].icon;
              });
            });

            return WillPopScope(
              onWillPop: () async => isFailed,
              child: Dialog(
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                elevation: 8,
                backgroundColor: AppColors.white,
                child: Padding(
                  padding: const EdgeInsets.all(24.0),
                  child: isFailed
                      ? Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: Colors.red.withOpacity(0.1),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.error_outline, color: Colors.red, size: 48),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              'Gagal Menganalisis Makanan',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.red),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              errorMessage ?? "Terjadi kesalahan yang tidak diketahui.",
                              style: const TextStyle(fontSize: 13, color: AppColors.textPrimary, height: 1.4),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 24),
                            Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton(
                                    onPressed: () {
                                      Navigator.pop(dialogContext);
                                    },
                                    style: OutlinedButton.styleFrom(
                                      padding: const EdgeInsets.symmetric(vertical: 14),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                    ),
                                    child: const Text('Tutup', style: TextStyle(color: AppColors.textSecondary)),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: ElevatedButton(
                                    onPressed: () {
                                      Navigator.pop(dialogContext);
                                      _runScanProcess(isPhoto: isPhoto);
                                    },
                                    style: ElevatedButton.styleFrom(
                                      padding: const EdgeInsets.symmetric(vertical: 14),
                                      backgroundColor: AppColors.primary,
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                    ),
                                    child: const Text('Coba Lagi', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.white)),
                                  ),
                                ),
                              ],
                            )
                          ],
                        )
                      : Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: AppColors.primary.withOpacity(0.1),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Icon(currentStageIcon, color: AppColors.primary, size: 24),
                                ),
                                const SizedBox(width: 14),
                                const Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('Analisis Gizi SINUTRI AI', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                                      Text('Proses Realtime', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 20),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  '${(currentProgress * 100).toInt()}% Selesai',
                                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.primary),
                                ),
                                const SizedBox(
                                  width: 14,
                                  height: 14,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                                )
                              ],
                            ),
                            const SizedBox(height: 8),
                            ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: LinearProgressIndicator(
                                value: currentProgress.clamp(0.0, 1.0),
                                minHeight: 10,
                                backgroundColor: AppColors.primary.withOpacity(0.1),
                                valueColor: const AlwaysStoppedAnimation<Color>(AppColors.primary),
                              ),
                            ),
                            const SizedBox(height: 16),
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: AppColors.background,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: AppColors.primary.withOpacity(0.15)),
                              ),
                              child: Text(
                                currentStageText,
                                style: const TextStyle(fontSize: 12, color: AppColors.textPrimary, fontWeight: FontWeight.w500, height: 1.4),
                                textAlign: TextAlign.center,
                              ),
                            ),
                            const SizedBox(height: 16),
                            Column(
                              children: List.generate(stages.length, (idx) {
                                final isDone = currentStageIndex > idx;
                                final isCurrent = currentStageIndex == idx;

                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 6.0),
                                  child: Row(
                                    children: [
                                      Icon(
                                        isDone
                                            ? Icons.check_circle
                                            : (isCurrent ? Icons.radio_button_checked : Icons.radio_button_unchecked),
                                        size: 16,
                                        color: isDone
                                            ? Colors.green
                                            : (isCurrent ? AppColors.primary : AppColors.textSecondary.withOpacity(0.4)),
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          stages[idx].text,
                                          style: TextStyle(
                                            fontSize: 11,
                                            color: isDone
                                                ? Colors.green.shade800
                                                : (isCurrent ? AppColors.primary : AppColors.textSecondary.withOpacity(0.5)),
                                            fontWeight: isCurrent ? FontWeight.bold : FontWeight.normal,
                                          ),
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                            ),
                          ],
                        ),
                ),
              ),
            );
          },
        );
      },
    );

    final result = isPhoto
        ? await ref.read(scanProvider.notifier).analyzeFood()
        : await ref.read(scanProvider.notifier).analyzeFoodByText();

    progressTimer?.cancel();

    if (result.success && result.data != null && mounted) {
      updateDialogState?.call(() {
        currentProgress = 1.0;
        currentStageIndex = 4;
        currentStageText = stages[4].text;
      });
      await Future.delayed(const Duration(milliseconds: 300));
      if (mounted) {
        Navigator.of(context, rootNavigator: true).pop();
        context.push(AppRoutes.scanResult, extra: result.data);
      }
    } else if (mounted) {
      updateDialogState?.call(() {
        isFailed = true;
        errorMessage = result.errorMessage ?? "Gagal menganalisis makanan. Silakan coba lagi.";
      });
    }
  }

  void _onScanWithPhoto() => _runScanProcess(isPhoto: true);
  void _onScanWithText() => _runScanProcess(isPhoto: false);

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(scanProvider);
    final notifier = ref.read(scanProvider.notifier);
    final profileState = ref.watch(profileProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.primary,
        elevation: 0,
        title: const Text('Scan Makanan', style: TextStyle(color: AppColors.white, fontSize: 18)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.white),
          onPressed: () {
            if (context.canPop()) context.pop();
          },
        ),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: AppColors.white,
          indicatorWeight: 3,
          labelColor: AppColors.white,
          unselectedLabelColor: AppColors.white.withOpacity(0.6),
          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
          tabs: const [
            Tab(icon: Icon(Icons.camera_alt), text: 'Foto Makanan'),
            Tab(icon: Icon(Icons.edit_note), text: 'Tulis Manual'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildPhotoTab(state, notifier, profileState),
          _buildTextTab(state, notifier, profileState),
        ],
      ),
    );
  }

  Widget _buildProfileSelector(ScanRequestModel state, ScanNotifier notifier, profileState) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Text('Makanan ini untuk: ', style: TextStyle(fontWeight: FontWeight.bold)),
            Icon(Icons.info_outline, size: 16, color: AppColors.textSecondary),
          ],
        ),
        const SizedBox(height: 12),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              _buildProfileChip('Saya', Icons.pregnant_woman, state.targetProfileName, notifier),
              if (profileState.children.isNotEmpty)
                ...profileState.children.map((child) => Padding(
                  padding: const EdgeInsets.only(left: 8.0),
                  child: _buildProfileChip(child.name, Icons.child_care, state.targetProfileName, notifier),
                )),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildPhotoTab(ScanRequestModel state, ScanNotifier notifier, profileState) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildProfileSelector(state, notifier, profileState),
          const SizedBox(height: 24),
          _buildCameraBox(state, notifier),
          const SizedBox(height: 24),
          const Text('Tambah catatan (Opsional)', style: TextStyle(fontSize: 14)),
          const SizedBox(height: 8),
          TextField(
            controller: _notesController,
            maxLines: 3,
            maxLength: 200,
            onChanged: (val) => notifier.setNotes(val),
            decoration: InputDecoration(
              hintText: 'Contoh: Digoreng dengan sedikit minyak...',
              hintStyle: TextStyle(color: AppColors.textSecondary.withOpacity(0.5), fontSize: 14),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: AppColors.textSecondary.withOpacity(0.2)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: AppColors.primary),
              ),
            ),
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _onScanWithPhoto,
            style: ElevatedButton.styleFrom(
              minimumSize: const Size(double.infinity, 56),
              backgroundColor: AppColors.primary,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.document_scanner, color: AppColors.white),
                SizedBox(width: 8),
                Text('Scan Sekarang', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.white)),
              ],
            ),
          ),
          const SizedBox(height: 8),
          const Center(
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.lock_outline, size: 12, color: AppColors.textSecondary),
                SizedBox(width: 4),
                Text('Foto dianalisis AI dan dihapus otomatis dalam 30 hari', style: TextStyle(fontSize: 10, color: AppColors.textSecondary)),
              ],
            ),
          ),
          const SizedBox(height: 100),
        ],
      ),
    );
  }

  Widget _buildTextTab(ScanRequestModel state, ScanNotifier notifier, profileState) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildProfileSelector(state, notifier, profileState),
          const SizedBox(height: 24),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.05),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.primary.withOpacity(0.2)),
            ),
            child: const Row(
              children: [
                Icon(Icons.lightbulb_outline, color: AppColors.primary, size: 20),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Tulis nama makanan atau daftar bahan yang dikonsumsi. AI akan menganalisis gizi dan memberikan rekomendasi.',
                    style: TextStyle(fontSize: 12, color: AppColors.textSecondary, height: 1.4),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          const Text('Deskripsi Makanan *', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          TextField(
            controller: _descriptionController,
            maxLines: 5,
            maxLength: 500,
            onChanged: (val) => notifier.setDescription(val),
            decoration: InputDecoration(
              hintText: 'Contoh: Nasi putih 1 piring, ayam goreng 1 potong paha, sayur bayam 1 mangkok, sambal terasi',
              hintStyle: TextStyle(color: AppColors.textSecondary.withOpacity(0.5), fontSize: 14),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: AppColors.textSecondary.withOpacity(0.2)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: AppColors.primary),
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text('Catatan Tambahan (Opsional)', style: TextStyle(fontSize: 14)),
          const SizedBox(height: 8),
          TextField(
            maxLines: 3,
            maxLength: 200,
            onChanged: (val) => notifier.setNotes(val),
            decoration: InputDecoration(
              hintText: 'Contoh: Dimasak tanpa garam, porsi untuk anak...',
              hintStyle: TextStyle(color: AppColors.textSecondary.withOpacity(0.5), fontSize: 14),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: AppColors.textSecondary.withOpacity(0.2)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: AppColors.primary),
              ),
            ),
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _onScanWithText,
            style: ElevatedButton.styleFrom(
              minimumSize: const Size(double.infinity, 56),
              backgroundColor: AppColors.primary,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.analytics, color: AppColors.white),
                SizedBox(width: 8),
                Text('Analisis Gizi', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.white)),
              ],
            ),
          ),
          const SizedBox(height: 8),
          const Center(
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.smart_toy_outlined, size: 12, color: AppColors.textSecondary),
                SizedBox(width: 4),
                Text('Dianalisis menggunakan AI & database TKPI Indonesia', style: TextStyle(fontSize: 10, color: AppColors.textSecondary)),
              ],
            ),
          ),
          const SizedBox(height: 100),
        ],
      ),
    );
  }

  Widget _buildProfileChip(String label, IconData icon, String selectedProfile, ScanNotifier notifier) {
    final isSelected = label == selectedProfile;
    return GestureDetector(
      onTap: () => notifier.setTargetProfile(label),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: isSelected ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3), width: isSelected ? 1.5 : 1),
        ),
        child: Row(
          children: [
            Icon(icon, size: 16, color: isSelected ? AppColors.primary : AppColors.textSecondary),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                color: isSelected ? AppColors.primary : AppColors.textPrimary,
                fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCameraBox(state, ScanNotifier notifier) {
    return Container(
      width: double.infinity,
      height: 240,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.primary, width: 2, style: BorderStyle.solid),
              borderRadius: BorderRadius.circular(16),
            ),
          ),
          if (state.imagePath != null && state.imagePath!.isNotEmpty)
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: SizedBox(
                width: double.infinity,
                height: double.infinity,
                child: Image.file(
                  File(state.imagePath!),
                  fit: BoxFit.cover,
                ),
              ),
            ),
          Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.camera_alt, color: AppColors.primary, size: 32),
              ),
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  OutlinedButton.icon(
                    onPressed: () => notifier.pickImage(ImageSource.camera),
                    icon: const Icon(Icons.camera_alt_outlined, color: AppColors.primary, size: 18),
                    label: const Text('Kamera', style: TextStyle(color: AppColors.primary)),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: AppColors.primary),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      backgroundColor: AppColors.white,
                    ),
                  ),
                  const SizedBox(width: 16),
                  OutlinedButton.icon(
                    onPressed: () => notifier.pickImage(ImageSource.gallery),
                    icon: const Icon(Icons.image_outlined, color: AppColors.primary, size: 18),
                    label: const Text('Galeri', style: TextStyle(color: AppColors.primary)),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: AppColors.primary),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      backgroundColor: AppColors.white,
                    ),
                  ),
                ],
              )
            ],
          )
        ],
      ),
    );
  }
}
