import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';

class AboutAppView extends StatelessWidget {
  const AboutAppView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            backgroundColor: AppColors.primary,
            flexibleSpace: FlexibleSpaceBar(
              title: const Text('Tentang SINUTRI', style: TextStyle(color: AppColors.white, fontWeight: FontWeight.bold)),
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [AppColors.primary, AppColors.secondary],
                  ),
                ),
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.auto_awesome, size: 60, color: AppColors.white),
                      const SizedBox(height: 10),
                      Text(
                        'Gemastik 2026 - Tim SINUTRI',
                        style: TextStyle(color: AppColors.white.withOpacity(0.9), fontSize: 14),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            leading: IconButton(
              icon: const Icon(Icons.arrow_back, color: AppColors.white),
              onPressed: () => context.pop(),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildSectionTitle('Visi Aplikasi'),
                  const SizedBox(height: 12),
                  const Text(
                    'SINUTRI adalah aplikasi kesehatan berbasis AI yang dirancang khusus untuk membantu pemantauan nutrisi dan pencegahan/pengelolaan Diabetes Mellitus dalam mengelola pola makan sehari-hari. Kami percaya bahwa kontrol gizi yang baik dimulai dari pemilihan makanan yang tepat dan monitoring yang konsisten.',
                    style: TextStyle(fontSize: 14, height: 1.6, color: AppColors.textPrimary),
                  ),
                  const SizedBox(height: 32),
                  _buildSectionTitle('Fitur Utama'),
                  const SizedBox(height: 16),
                  _buildFeatureItem(
                    icon: Icons.camera_alt_outlined,
                    title: 'SINUTRI Scan Makanan',
                    description: 'Foto makanan dengan kamera, AI akan mendeteksi jenis makanan, menghitung kalori, karbohidrat, dan gula dari Tabel Komposisi Pangan Indonesia (TKPI). Dapatkan analisis Glycemic Risk (rendah/sedang/tinggi) dan rekomendasi alternatif pangan lokal yang lebih sehat.',
                    color: Colors.blue,
                  ),
                  _buildFeatureItem(
                    icon: Icons.restaurant_menu,
                    title: 'AI Meal Planner',
                    description: 'Generate menu harian yang dipersonalisasi berdasarkan profil nutrisi, target kesehatan, budget, dan preferensi makanan. Mengutamakan pangan lokal Indonesia dengan Indeks Glikemik rendah-sedang.',
                    color: Colors.green,
                  ),
                  _buildFeatureItem(
                    icon: Icons.chat_bubble_outline,
                    title: 'NutriBot (AI Chatbot)',
                    description: 'Asisten virtual berbasis RAG (Retrieval-Augmented Generation) yang menyediakan edukasi seputar gizi, Diabetes Mellitus, indeks glikemik, dan pangan lokal Indonesia. Jawaban mengacu pada TKPI dan referensi terpercaya.',
                    color: Colors.orange,
                  ),
                  _buildFeatureItem(
                    icon: Icons.show_chart,
                    title: 'Dashboard & Grafik',
                    description: 'Monitor asupan harian (kalori, karbohidrat, gula, protein, lemak, serat) dan lihat tren konsumsi mingguan dalam bentuk grafik yang mudah dipahami. Glycemic Score tracking untuk membantu menjaga kestabilan gula darah.',
                    color: AppColors.primary,
                  ),
                  _buildFeatureItem(
                    icon: Icons.history,
                    title: 'Food & Glycemic Log',
                    description: 'Riwayat lengkap konsumsi makanan dengan analisis nutrisi, Glycemic Score, kategori risiko, dan AI Insight untuk setiap meal. Export sebagai Health Report PDF untuk konsultasi dengan tenaga kesehatan.',
                    color: Colors.purple,
                  ),
                  const SizedBox(height: 32),
                  _buildSectionTitle('Teknologi'),
                  const SizedBox(height: 12),
                  const Text(
                    '• Gemini AI - Food recognition & meal planning\n• RAG System - Chatbot dengan knowledge base tervalidasi\n• TKPI (Tabel Komposisi Pangan Indonesia)\n• Database Indeks Glikemik pangan lokal\n• Pinecone Vector DB - Nutritional data retrieval',
                    style: TextStyle(fontSize: 13, height: 1.8, color: AppColors.textPrimary),
                  ),
                  const SizedBox(height: 32),
                  _buildSectionTitle('Tim Pengembang'),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: AppColors.textSecondary.withOpacity(0.1)),
                    ),
                    child: const Row(
                      children: [
                        CircleAvatar(
                          backgroundColor: AppColors.secondary,
                          child: Text('G26', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold)),
                        ),
                        SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Gemastik 2026 - UNESA', style: TextStyle(fontWeight: FontWeight.bold)),
                              Text('Innovative Digital Health Solutions', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 48),
                  const Center(
                    child: Text(
                      'Versi 1.0.0 (Stable)',
                      style: TextStyle(color: AppColors.textSecondary, fontSize: 12),
                    ),
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.primary),
    );
  }

  Widget _buildFeatureItem({
    required IconData icon,
    required String title,
    required String description,
    required Color color,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 4),
                Text(
                  description,
                  style: const TextStyle(fontSize: 13, height: 1.5, color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
