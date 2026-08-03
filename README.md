# 📱 SINUTRI FRONTEND - GEMASTIK 2026
> **Aplikasi Mobile Personal Health & Nutrition Companion Berbasis Flutter 3.x & AI untuk Manajemen Diabetes Mellitus & Pangan Lokal Indonesia**

[![Flutter](https://img.shields.io/badge/Flutter-3.x-02569B?style=for-the-badge&logo=flutter&logoColor=white)](https://flutter.dev)
[![Dart](https://img.shields.io/badge/Dart-3.x-0175C2?style=for-the-badge&logo=dart&logoColor=white)](https://dart.dev)
[![Riverpod](https://img.shields.io/badge/State-Riverpod_2.x-00599C?style=for-the-badge)](https://riverpod.dev)
[![GoRouter](https://img.shields.io/badge/Routing-Go_Router_13.x-blue?style=for-the-badge)](https://pub.dev/packages/go_router)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

---

## 📌 Ringkasan Eksekutif (Executive Summary)

**SiNutri Frontend** adalah aplikasi mobile lintas platform (Android & iOS) yang dirancang untuk memberikan pengalaman pengguna (*User Experience*) yang mengesankan, modern, dan intuitif dalam memantau nutrisi serta indeks glikemik harian bagi penderita **Diabetes Mellitus (DM Tipe 1, DM Tipe 2, Prediabetes)**.

Aplikasi ini dibangun menggunakan framework **Flutter 3.x**, arsitektur manajemen state **Riverpod**, navigasi deklaratif **GoRouter**, visualisasi chart interaktif **FL Chart**, serta terintegrasi penuh dengan REST API Backend SiNutri.

---

## 🎨 Keunggulan Desain & UX (Design & Aesthetics)

1. **Modern Light UI System:** Menggunakan skema warna yang ramah mata (`AppColors.primary`, pastel green, warm white) yang memberikan kesan medis tepercaya sekaligus segar.
2. **Smooth Micro-Animations:**
   - **Animasi Splash Screen:** Transisi *Scale & Fade-In* logo `SiNutri_app_logo.jpeg` dengan efek meluncur (*Slide-Up*) pada teks judul.
   - **Interactive Loading States:** Indikator pemrosesan AI yang halus pada saat pemindaian foto makanan dan percakapan NutriBot.
3. **Responsive Visual Charts:** Visualisasi grafik tren konsumsi nutrisi harian dan *Glycemic Score* menggunakan **FL Chart** yang responsif dan interaktif.
4. **PDF Export Ready:** Generator laporan kesehatan dalam format file PDF cetak menggunakan paket `pdf` dan `printing`.

---

## 🌟 Fitur-Fitur Utama Frontend (Key Features)

### 1. 🚀 Animated Splash Screen & Onboarding
- Logo aplikasi `SiNutri_app_logo.jpeg` dengan *smooth scale animation*.
- *Auto-Check Auth Token* via `SharedPreferences` untuk menentukan rute navigasi (*Dashboard / Login / Onboarding*).

### 2. 📝 Profiling Nutrisi 3-Step Flow
- **Step 1:** Form data fisik (TB, BB, Usia, Gender) dengan indikator BMI otomatis.
- **Step 2:** Pemilihan kondisi diabetes (DM Tipe 1, DM Tipe 2, Prediabetes, Belum Terdiagnosis), riwayat hipertensi, dan alergi.
- **Step 3:** Pemilihan target kesehatan personal (Kontrol Karbo, Gula Darah Stabil, Menurunkan BB, Pola Makan Sehat).

### 3. 📸 AI NutriScan Camera & Analysis View
- Pengambilan foto makanan dari Kamera / Galeri via `image_picker`.
- Tampilan hasil inferensi Vision AI: estimasi berat (gram), detail nutrisi 7 elemen, **Glycemic Score Badge (Low/Medium/High Risk)**, serta saran pangan lokal alternatif ber-GI rendah.

### 4. 🤖 NutriBot Interactive Chat UI
- Antarmuka percakapan interaktif berbasis RAG AI.
- Rekomendasi tombol *Quick Prompt* (misal: "Apa itu GI?", "Pangan lokal rendah karbo", "Tips gula darah stabil").

### 5. 🥗 AI Meal Planner UI
- Rencana menu harian terbagi atas **Sarapan, Makan Siang, Makan Malam, dan Snack**.
- Filter penyesuaian ketersediaan stok bahan dan batas anggaran belanja harian (Rp).

### 6. 📊 Dashboard & History Analytics
- Ringkasan harian konsumsi kalori, karbohidrat, gula, protein, lemak, serat, dan skor glikemik.
- Tampilan riwayat makanan berdasarkan kalender dan tanggal makan.

### 7. 📄 Health Report & PDF Exporter
- Rekapitulasi laporan kesehatan berkala rentang tanggal (*Date Range Picker*).
- Fitur *Preview & Print PDF Report*.

---

## 🏗️ Struktur Arsitektur Kode (Directory Structure)

Project menggunakan struktur **Feature-First Architecture** yang rapi dan mudah dirawat:

```
sinutri/
├── assets/
│   ├── images/              # Logo aplikasi (SiNutri_app_logo.jpeg, logo.png, onboarding)
│   └── icons/               # Icon aset pendukung
├── lib/
│   ├── main.dart            # Entrypoint utama aplikasi Flutter
│   ├── core/
│   │   ├── constants/       # AppColors, ApiConstants, AppTheme
│   │   ├── services/        # PdfService, NotificationService
│   │   └── widgets/         # CustomTextField, CustomSelectionCard, StepProgressIndicator
│   ├── features/
│   │   ├── auth/            # Login, Register, OTP Verification, Profiling 3-Step Views & Providers
│   │   ├── chatbot/         # NutriBot Chatbot View & Riverpod Providers
│   │   ├── dashboard/       # Dashboard Screen, Home View, Nutrition Graph View (FL Chart)
│   │   ├── history/         # History List & History Detail Views
│   │   ├── main/            # MainWrapperScreen (Bottom Navigation Bar)
│   │   ├── meal_planner/    # AI Meal Planner View & State Providers
│   │   ├── onboarding/      # Onboarding Slides View
│   │   ├── profile/         # Profile View, Edit Profile, About App
│   │   ├── scan/            # AI NutriScan Camera View & Result Display
│   │   └── splash/          # Animated SplashScreen View
│   └── routes/
│       ├── app_router.dart  # Konfigurasi GoRouter Navigasi Deklaratif
│       └── app_routes.dart  # Definisi Rute String
└── pubspec.yaml             # Manajemen Dependensi & Konfigurasi Launcher Icons
```

---

## 🛠️ Panduan Instalasi & Menjalankan Project

### 1. Prasyarat Environment
- **Flutter SDK:** `>=3.3.0 <4.0.0`
- **Dart SDK:** `^3.6`
- **Android Studio / VS Code** dengan Ekstensi Flutter

### 2. Download & Install Dependensi
```bash
cd sinutri
flutter pub get
```

### 3. Generate Launcher Icons
```bash
flutter pub run flutter_launcher_icons
```

### 4. Jalankan Aplikasi di Emulator / Device
```bash
flutter run
```

### 5. Build Release APK (`SINUTRI.apk`)
Untuk menghasilkan file installer Android release:
```bash
flutter build apk --release
```
File APK akan secara otomatis dihasilkan di:
- Path Output: `build/app/outputs/flutter-apk/app-release.apk`
- Path Salinan Utama: `SINUTRI.apk` (di direktori utama).

---

## 🔗 Integrasi REST API Backend

Aplikasi Flutter ini terhubung ke backend Laravel **SiNutri**:
- **Repository Backend:** [https://github.com/22-Maulana/sinutri-backend.git](https://github.com/22-Maulana/sinutri-backend.git)
- **Base URL Config:** [`lib/core/constants/api_constants.dart`](file:///mnt/data/Dokumenku/01.%20File%20Unesa/Lain-Lain/12.%20Gemastik%202026/Source_Code/sinutri/lib/core/constants/api_constants.dart)

---

## 👥 Tim Pengembang - GEMASTIK 2026

- **Nama Tim:** SINUTRI GEMASTIK Team
- **Institusi:** Universitas Negeri Surabaya (UNESA)
- **Kategori Lomba:** Pengembangan Perangkat Lunak (Software Development) - GEMASTIK 2026
- **Repository Frontend:** [https://github.com/22-Maulana/sinutri-frontend.git](https://github.com/22-Maulana/sinutri-frontend.git)

---

## 📄 Lisensi

Project ini dilisensikan di bawah [Lisensi MIT](LICENSE). Hak Cipta © 2026 **SiNutri Team - GEMASTIK 2026**.
