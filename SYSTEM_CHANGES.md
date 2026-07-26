# SiNutri - Sistem Revisi: Diabetes Mellitus

## 📋 Ringkasan Perubahan Sistem

### Sistem Lama (Ibu Hamil & Balita)
- ❌ Fokus: Gizi ibu hamil dan tumbuh kembang balita
- ❌ Profil: Mother Profile & Child Profile
- ❌ Growth Records (BB, TB anak)
- ❌ Nutrisi fokus: Zat Besi, Kalsium
- ❌ AI: DeepSeek untuk chatbot

### Sistem Baru (Diabetes Mellitus) ✅
- ✅ Fokus: Pencegahan dan pengelolaan Diabetes Mellitus
- ✅ Profil: User Profile (personal diabetes profile)
- ✅ Glycemic Analysis (GI, GL, Risk Category)
- ✅ Nutrisi fokus: Karbohidrat, Gula, Serat, Indeks Glikemik
- ✅ AI: Gemini 2.0 Flash + RAG (Pinecone)

---

## 🗄️ Perubahan Database

### Tabel Baru

#### 1. `user_profiles`
```sql
- id (uuid)
- user_id (foreign key)
- name, age, gender
- height_cm, weight_kg, bmi (auto-calculated)
- diabetes_status (dm_type_1, dm_type_2, prediabetes, not_diagnosed)
- family_diabetes_history (boolean)
- hypertension (boolean, optional)
- food_allergies (json array)
- health_targets (json array)
```

#### 2. `meal_plans`
```sql
- id (uuid)
- user_id (foreign key)
- plan_date
- breakfast_items, lunch_items, dinner_items, snack_items (json)
- total_calories, total_carbs, total_protein, total_fat, total_fiber, total_sugar
- estimated_total_cost
- ai_insight
- budget, available_ingredients, food_preferences (json)
```

### Tabel Diupdate

#### `food_logs` - Ditambahkan kolom:
```sql
- meal_type (breakfast, lunch, dinner, snack) - BARU
- sugar_g (float) - BARU
- portion_grams (float) - BARU
- glycemic_index (float) - BARU
- glycemic_score (float) - BARU
- risk_category (low, medium, high) - BARU
- ai_insight (text) - BARU
- ai_recommendation (text) - BARU
- alternative_foods (json) - BARU

DIHAPUS:
- target_type, target_id (tidak diperlukan lagi)
- iron_mg, calcium_mg (fokus berubah)
```

### Tabel Dihapus
- ❌ `mother_profiles`
- ❌ `child_profiles`
- ❌ `growth_records`

---

## 🔧 Backend Controllers

### Controllers Baru

1. **DashboardController**
   - `summary()` - Ringkasan harian + AI Daily Insight
   - `weeklyProgress()` - Chart progress 7 hari

2. **MealPlannerController**
   - `generate()` - AI Meal Planner
   - `index()` - List meal plans
   - `show()` - Detail meal plan
   - `destroy()` - Hapus meal plan

3. **HealthReportController**
   - `generate()` - Generate health report dengan chart data

### Controllers Direvisi

1. **ProfileController**
   - `storeBasicData()` - Step 1: Data dasar (nama, usia, gender, TB, BB)
   - `updateHealthCondition()` - Step 2: Kondisi kesehatan DM
   - `updateHealthTargets()` - Step 3: Target kesehatan
   - `updateProfile()` - Update profile lengkap
   - `getProfile()` - Get user profile

2. **ScanController**
   - Food detection dengan Gemini Vision
   - Weight estimation
   - TKPI nutrition lookup (Pinecone RAG)
   - **Glycemic Index & Glycemic Score calculation**
   - **Risk categorization (low/medium/high)**
   - **AI Alternative Recommendation** (pangan lokal GI rendah)
   - AI Insight & Recommendation

3. **FoodLogController**
   - `store()` - Simpan food log dengan glycemic data
   - `todayMenu()` - Menu hari ini
   - `analyzeMenu()` - Analisis multiple food items
   - `index()`, `show()`, `update()`, `destroy()`

4. **ChatbotController (NutriBot)**
   - Embedding user question
   - Query Pinecone namespace `knowledge-diabetes`
   - Gemini LLM dengan RAG context
   - Fokus: Edukasi DM, GI, pangan lokal, nutrisi

### Controllers Dihapus
- ❌ `GrowthController`

---

## 🎯 Fitur Utama Sistem Baru

### 1. Profiling (3 Step)
**Step 1 - Data Dasar:**
- Nama, Usia, Jenis kelamin
- Tinggi badan, Berat badan
- BMI (dihitung otomatis)

**Step 2 - Kondisi Kesehatan:**
- Status diabetes (DM Tipe 1, DM Tipe 2, Prediabetes, Belum Terdiagnosis)
- Riwayat keluarga DM
- Hipertensi (opsional)
- Alergi makanan (opsional)

**Step 3 - Target Kesehatan:**
- Menjaga kadar gula darah tetap stabil
- Mengurangi konsumsi gula harian
- Mengontrol asupan karbohidrat
- Menurunkan berat badan secara sehat
- Menjaga pola makan yang lebih sehat

### 2. AI NutriScan
**Flow:**
1. 📸 User scan makanan (foto)
2. 🤖 Gemini Vision deteksi makanan & bahan
3. 📏 Estimasi porsi/berat (gram)
4. 🔍 Lookup TKPI via Pinecone RAG
5. 📊 Hitung nutrisi (kalori, karbo, gula, protein, lemak, serat)
6. 📈 Hitung Glycemic Index & Glycemic Score
7. ⚠️ Kategorisasi risiko (Low/Medium/High)
8. 💡 AI Insight & Recommendation
9. 🔄 AI Alternative Recommendation (pangan lokal GI rendah)
10. ✅ Konfirmasi user
11. 💾 Simpan ke Food Log

**Glycemic Score Calculation:**
```
Glycemic Load = (GI × Carbs) / 100

Risk Category:
- Low: < 11
- Medium: 11-19
- High: ≥ 20
```

### 3. Menu Hari Ini
- List makanan yang sudah di-scan hari ini
- Ringkasan total nutrisi
- Tombol "Analisis Menu"
- AI memberikan insight & recommendation untuk keseluruhan menu

### 4. Food & Glycemic Log (History)
**Data tersimpan:**
- Tanggal & waktu makan
- Jenis waktu makan (breakfast/lunch/dinner/snack)
- Daftar makanan & porsi
- Ringkasan nutrisi lengkap
- Glycemic Score & Risk Category
- AI Insight & AI Recommendation
- Alternative Foods

**View History:**
- List per hari dengan badge risk
- Detail per menu dengan full analysis

### 5. Dashboard/Home
**Tampilan:**
- Selamat datang + tanggal
- AI Meal Planner (quick access)
- Ringkasan Hari Ini:
  - 🔥 Kalori
  - 🍚 Karbohidrat
  - 🍬 Gula
  - 🥩 Protein
  - 🥬 Serat
  - 🫒 Lemak
  - 📈 Glycemic Score
  - 🎯 Target Harian (vs actual)
- Progress Mingguan (chart)
- AI Daily Insight
- Rekomendasi Hari Ini

### 6. NutriBot (AI Chatbot)
**Teknologi:**
- Retrieval-Augmented Generation (RAG)
- Pinecone namespace: `knowledge-diabetes`
- Gemini 2.0 Flash LLM

**Topik yang bisa ditanyakan:**
- 📚 Edukasi Diabetes (DM Tipe 1, Tipe 2, Prediabetes, GI)
- 🍽️ Informasi Makanan (TKPI database)
- 🥦 Pangan Lokal Indonesia
- 📊 Nutrisi & AKG Indonesia
- 💡 Tips pola makan untuk diabetes

### 7. AI Meal Planner
**Input (opsional):**
- Budget
- Stok bahan yang tersedia
- Preferensi makanan

**AI Nutrition Requirement Calculator:**
- Harris-Benedict equation untuk BMR
- Activity factor
- Disesuaikan dengan status diabetes
- Output: Kebutuhan kalori, karbo, protein, lemak, serat harian

**Output:**
- ☀️ Sarapan
- 🌤️ Makan Siang
- 🌙 Makan Malam
- 🍎 Snack

Setiap item:
- Nama makanan (pangan lokal Indonesia)
- Berat/porsi (gram)
- Kalori, Karbo, Protein, Lemak, Serat, Gula
- Estimasi biaya

**AI Insight:**
- Penjelasan mengapa menu ini cocok
- Disesuaikan dengan profil diabetes user

### 8. Health Report
**Periode Laporan:**
- User pilih start_date & end_date

**Konten:**
1. **Informasi Pengguna** (nama, umur, BMI, status DM)
2. **Periode Laporan** (durasi, jumlah menu)
3. **Ringkasan Nutrisi** (rata-rata harian)
4. **Ringkasan Glycemic Risk** (distribusi low/medium/high)
5. **Riwayat Konsumsi** (list semua menu)
6. **Grafik Analisis:**
   - 📈 Konsumsi gula harian (line chart)
   - 📊 Distribusi makronutrien (bar chart)
   - 📉 Tren glycemic score (line chart)
7. **Ringkasan Aktivitas** (total scan, hari aktif)
8. **AI Insight** (analisis pola konsumsi)
9. **AI Recommendation** (saran perbaikan)

**Export:**
- Download PDF (untuk dokumentasi atau konsultasi dokter)

---

## 🤖 AI Integration

### Gemini AI
**Model:** `gemini-2.0-flash-exp`

**Use Cases:**
1. **Vision** - Food detection dari foto
2. **Text Generation** - Weight estimation, nutrition fallback
3. **Decision Support System** - Recommendation status
4. **Insight & Recommendation** - AI analysis
5. **Meal Planning** - Generate personalized meal plan
6. **Chatbot** - NutriBot dengan RAG
7. **Health Report Analysis** - AI Insight & Recommendation

### Pinecone Vector Database
**Embedding Model:** `text-embedding-004`

**Namespaces:**
1. **`tkpi-indonesia`**
   - Tabel Komposisi Pangan Indonesia
   - Nutrition data per 100g
   - Metadata: kalori, protein, lemak, karbo, serat, gula, GI

2. **`knowledge-diabetes`** (BARU)
   - Edukasi Diabetes Mellitus
   - Informasi Indeks Glikemik
   - Angka Kecukupan Gizi Indonesia
   - Referensi ilmiah diabetes & nutrisi

### RAG (Retrieval-Augmented Generation)
**Flow:**
1. User query → Embed with Gemini
2. Query Pinecone untuk relevant knowledge
3. Ambil top-K hasil dengan metadata
4. Gabungkan sebagai context
5. Gemini generate response dengan context

**Keuntungan:**
- Jawaban lebih akurat & tervalidasi
- Menggunakan data TKPI, AKG resmi
- Update knowledge tanpa re-train model
- Source attribution

---

## 📊 Nutrition Focus Comparison

### Sistem Lama (Ibu Hamil)
| Nutrisi | Fokus |
|---------|-------|
| Kalori | ✓ |
| Protein | ✓ |
| Lemak | ✓ |
| Karbohidrat | ✓ |
| Serat | ✓ |
| **Zat Besi** | ✓✓✓ |
| **Kalsium** | ✓✓✓ |
| Gula | - |
| Indeks Glikemik | - |
| Glycemic Load | - |

### Sistem Baru (Diabetes Mellitus)
| Nutrisi | Fokus |
|---------|-------|
| Kalori | ✓ |
| Protein | ✓ |
| Lemak | ✓ |
| **Karbohidrat** | ✓✓✓ |
| **Serat** | ✓✓✓ |
| **Gula** | ✓✓✓ |
| **Indeks Glikemik (GI)** | ✓✓✓ |
| **Glycemic Load (GL)** | ✓✓✓ |
| Zat Besi | - |
| Kalsium | - |

---

## 🔗 API Endpoints Summary

### Authentication
- `POST /register`
- `POST /verify-otp`
- `POST /login`
- `POST /logout`

### Profile (Diabetes System)
- `GET /profile`
- `POST /profile/basic-data` (Step 1)
- `PUT /profile/health-condition` (Step 2)
- `PUT /profile/health-targets` (Step 3)
- `PUT /profile` (Update)

### AI NutriScan
- `POST /scan`

### Food Logs
- `GET /food-logs`
- `POST /food-logs`
- `GET /food-logs/today`
- `POST /food-logs/analyze-menu`
- `GET /food-logs/{id}`
- `PUT /food-logs/{id}`
- `DELETE /food-logs/{id}`

### Dashboard
- `GET /dashboard/summary`
- `GET /dashboard/weekly`

### AI Meal Planner
- `POST /meal-planner/generate`
- `GET /meal-planner`
- `GET /meal-planner/{id}`
- `DELETE /meal-planner/{id}`

### NutriBot
- `POST /chatbot`

### Health Report
- `POST /health-report/generate`

**Total:** 23 endpoints

---

## ✅ Status Implementasi Backend

### Database ✅
- [x] Migration user_profiles
- [x] Migration meal_plans
- [x] Migration update food_logs
- [x] Model UserProfile
- [x] Model MealPlan
- [x] Model FoodLog (updated)
- [x] Database sinutri2 created & migrated

### Controllers ✅
- [x] ProfileController (3-step profiling)
- [x] ScanController (glycemic analysis + alternatives)
- [x] FoodLogController (dengan analyze menu)
- [x] DashboardController (summary + weekly)
- [x] MealPlannerController (AI meal planner)
- [x] ChatbotController (NutriBot dengan RAG)
- [x] HealthReportController (dengan chart data)

### API Routes ✅
- [x] routes/api.php (updated semua endpoint)

### Cleanup ✅
- [x] Hapus MotherProfile model
- [x] Hapus ChildProfile model
- [x] Hapus GrowthRecord model
- [x] Hapus GrowthController

### Documentation ✅
- [x] API_DOCUMENTATION.md
- [x] SYSTEM_CHANGES.md (file ini)

---

## ⏳ Yang Belum Dikerjakan

### Backend
- [ ] Export PDF untuk Health Report (perlu install library)
- [ ] Testing semua endpoint
- [ ] Seeding data sample untuk testing

### Frontend Flutter
- [ ] Onboarding profiling 3-step
- [ ] Scan page (flow lengkap)
- [ ] Menu Hari Ini page
- [ ] History page (list & detail)
- [ ] Dashboard/Home (dengan chart)
- [ ] NutriBot chatbot page
- [ ] AI Meal Planner page
- [ ] Health Report page
- [ ] Update bottom navigation

### Testing & Integration
- [ ] API testing dengan Postman
- [ ] Integration testing Flutter-Backend
- [ ] User acceptance testing

---

## 🎯 Next Steps

### 1. Setup Pinecone (PENTING!)
Pastikan Pinecone sudah memiliki namespace:
- `tkpi-indonesia` - dengan data TKPI + GI
- `knowledge-diabetes` - dengan edukasi DM, AKG, referensi

### 2. Testing Backend
```bash
# Start Laravel server
php artisan serve

# Test dengan Postman:
1. Register & Login
2. Profiling (3 steps)
3. Scan makanan
4. Food logs
5. Dashboard
6. Meal planner
7. Chatbot
8. Health report
```

### 3. Development Flutter
Prioritas:
1. Onboarding profiling
2. Scan & food log
3. Dashboard
4. History
5. Meal planner
6. Chatbot
7. Health report

---

## 📝 Notes

1. **Gemini API Key** diperlukan untuk:
   - Vision (food detection)
   - Text generation (meal plan, insights, chatbot)
   - Embedding (RAG)

2. **Pinecone API Key** diperlukan untuk:
   - TKPI nutrition lookup
   - Knowledge retrieval untuk chatbot

3. **BMI Auto-calculation:**
   ```
   BMI = weight_kg / (height_m)²
   ```

4. **Glycemic Score (Glycemic Load):**
   ```
   GL = (GI × Carbs) / 100
   
   Risk:
   - Low: < 11
   - Medium: 11-19
   - High: ≥ 20
   ```

5. **Nutrition Requirement Calculator:**
   - Harris-Benedict equation untuk BMR
   - Activity factor: 1.55 (moderate)
   - Macronutrient distribution disesuaikan status DM

---

**Date:** 2026-07-24  
**Version:** 2.0 (Diabetes Mellitus System)  
**Status:** Backend Complete ✅ | Frontend Pending ⏳
