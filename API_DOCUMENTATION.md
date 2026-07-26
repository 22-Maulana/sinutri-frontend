# SiNutri API Documentation - Diabetes Mellitus System

Base URL: `http://localhost:8000/api`

---

## Authentication Endpoints

### 1. Register
**POST** `/register`

Request Body:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

### 2. Verify OTP
**POST** `/verify-otp`

Request Body:
```json
{
  "email": "john@example.com",
  "otp_code": "123456"
}
```

### 3. Login
**POST** `/login`

Request Body:
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

Response:
```json
{
  "message": "Login successful",
  "access_token": "1|xxx...",
  "token_type": "Bearer",
  "user": {...}
}
```

### 4. Logout
**POST** `/logout`

Headers: `Authorization: Bearer {token}`

---

## Profile Management (Diabetes System)

### 1. Get Profile
**GET** `/profile`

Headers: `Authorization: Bearer {token}`

Response:
```json
{
  "message": "Profile retrieved successfully",
  "data": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user",
    "profile": {
      "id": "uuid",
      "user_id": "uuid",
      "name": "John Doe",
      "age": 35,
      "gender": "L",
      "height_cm": 170,
      "weight_kg": 75,
      "bmi": 25.95,
      "diabetes_status": "dm_type_2",
      "family_diabetes_history": true,
      "hypertension": false,
      "food_allergies": ["kacang", "seafood"],
      "health_targets": ["stable_blood_sugar", "lose_weight"]
    }
  }
}
```

### 2. Store Basic Data (Step 1)
**POST** `/profile/basic-data`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "name": "John Doe",
  "age": 35,
  "gender": "L",
  "height_cm": 170,
  "weight_kg": 75
}
```

Response: BMI dihitung otomatis

### 3. Update Health Condition (Step 2)
**PUT** `/profile/health-condition`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "diabetes_status": "dm_type_2",
  "family_diabetes_history": true,
  "hypertension": false,
  "food_allergies": ["kacang", "seafood"]
}
```

Diabetes Status Options:
- `dm_type_1` - Diabetes Mellitus Tipe 1
- `dm_type_2` - Diabetes Mellitus Tipe 2
- `prediabetes` - Prediabetes
- `not_diagnosed` - Belum Terdiagnosis

### 4. Update Health Targets (Step 3)
**PUT** `/profile/health-targets`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "health_targets": [
    "stable_blood_sugar",
    "reduce_sugar_intake",
    "control_carbs",
    "lose_weight",
    "healthy_diet"
  ]
}
```

### 5. Update Profile
**PUT** `/profile`

Headers: `Authorization: Bearer {token}`

Request Body: (semua field optional)
```json
{
  "name": "John Doe",
  "age": 36,
  "weight_kg": 73
}
```

---

## AI NutriScan

### Scan Food
**POST** `/scan`

Headers: 
- `Authorization: Bearer {token}`
- `Content-Type: multipart/form-data`

Request Body (form-data):
```
image: [file]
notes: "Nasi goreng dengan telur dan ayam" (optional)
```

Response:
```json
{
  "message": "Analisis makanan berhasil",
  "data": {
    "food_name_detected": "Nasi Goreng",
    "ingredients": ["Nasi Putih", "Telur", "Ayam", "Minyak Goreng"],
    "portion_grams": 350,
    "photo_url": "http://localhost:8000/storage/...",
    "calories_kcal": 520.5,
    "carbs_g": 68.2,
    "sugar_g": 2.5,
    "protein_g": 22.3,
    "fat_g": 18.5,
    "fiber_g": 2.1,
    "glycemic_index": 72,
    "glycemic_score": 49.1,
    "risk_category": "high",
    "recommendation_status": "PERHATIAN",
    "ai_insight": "Menu ini memiliki indeks glikemik tinggi yang dapat menyebabkan lonjakan gula darah...",
    "ai_recommendation": "Pertimbangkan untuk mengganti nasi putih dengan nasi merah atau mengurangi porsi...",
    "alternative_foods": [
      {
        "name": "Nasi Merah",
        "reason": "GI lebih rendah (55) dan kaya serat"
      },
      {
        "name": "Nasi Jagung",
        "reason": "GI rendah dan tinggi serat"
      }
    ]
  }
}
```

---

## Food Logs

### 1. Get All Food Logs
**GET** `/food-logs`

Headers: `Authorization: Bearer {token}`

Query Params (optional):
- `start_date`: 2026-07-01
- `end_date`: 2026-07-31

### 2. Save Food Log
**POST** `/food-logs`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "meal_time": "2026-07-24 12:30:00",
  "meal_type": "lunch",
  "food_name_detected": "Nasi Goreng",
  "portion_grams": 350,
  "photo_url": "http://...",
  "recommendation_status": "PERHATIAN",
  "calories_kcal": 520.5,
  "carbs_g": 68.2,
  "sugar_g": 2.5,
  "protein_g": 22.3,
  "fat_g": 18.5,
  "fiber_g": 2.1,
  "glycemic_index": 72,
  "glycemic_score": 49.1,
  "risk_category": "high",
  "ai_insight": "...",
  "ai_recommendation": "...",
  "alternative_foods": [...],
  "notes": "Makan siang di kantor"
}
```

Meal Type Options: `breakfast`, `lunch`, `dinner`, `snack`

### 3. Get Today's Menu
**GET** `/food-logs/today`

Headers: `Authorization: Bearer {token}`

Query Params (optional):
- `date`: 2026-07-24

Response:
```json
{
  "message": "Menu hari ini",
  "date": "2026-07-24",
  "meals": [...],
  "summary": {
    "total_calories": 1650.5,
    "total_carbs": 185.3,
    "total_sugar": 28.5,
    "total_protein": 68.2,
    "total_fat": 52.1,
    "total_fiber": 18.5
  }
}
```

### 4. Analyze Menu (Multiple Foods)
**POST** `/food-logs/analyze-menu`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "date": "2026-07-24",
  "food_log_ids": [
    "uuid-1",
    "uuid-2",
    "uuid-3"
  ]
}
```

Response:
```json
{
  "message": "Analisis menu selesai",
  "data": {
    "summary": {
      "total_calories": 1650.5,
      "total_carbs": 185.3,
      "total_sugar": 28.5,
      "total_protein": 68.2,
      "total_fat": 52.1,
      "total_fiber": 18.5,
      "avg_glycemic_score": 15.8,
      "risk_category": "medium"
    },
    "ai_insight": "Konsumsi karbohidrat dan gula hari ini sudah mendekati batas...",
    "ai_recommendation": "Untuk makan malam, pilih protein dan sayuran, hindari karbohidrat tinggi..."
  }
}
```

### 5. Get Single Food Log
**GET** `/food-logs/{id}`

Headers: `Authorization: Bearer {token}`

### 6. Update Food Log
**PUT** `/food-logs/{id}`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "notes": "Updated notes"
}
```

### 7. Delete Food Log
**DELETE** `/food-logs/{id}`

Headers: `Authorization: Bearer {token}`

---

## Dashboard

### 1. Daily Summary
**GET** `/dashboard/summary`

Headers: `Authorization: Bearer {token}`

Query Params (optional):
- `date`: 2026-07-24

Response:
```json
{
  "message": "Dashboard summary",
  "date": "2026-07-24",
  "profile": {
    "name": "John Doe",
    "age": 35,
    "bmi": 25.95,
    "diabetes_status": "dm_type_2"
  },
  "today_summary": {
    "calories": 1650.5,
    "carbs": 185.3,
    "sugar": 28.5,
    "protein": 68.2,
    "fat": 52.1,
    "fiber": 18.5,
    "glycemic_score": 15.8
  },
  "daily_targets": {
    "calories": 2100,
    "carbs": 252,
    "protein": 105,
    "fat": 70,
    "fiber": 32,
    "sugar": 52.5
  },
  "recent_meals": [...],
  "ai_daily_insight": "Konsumsi hari ini sudah cukup baik, namun...",
  "meal_plan": {...}
}
```

### 2. Weekly Progress
**GET** `/dashboard/weekly`

Headers: `Authorization: Bearer {token}`

Query Params (optional):
- `end_date`: 2026-07-24 (default: today)

Response: 7 hari data mundur dari end_date
```json
{
  "message": "Weekly progress",
  "start_date": "2026-07-18",
  "end_date": "2026-07-24",
  "data": [
    {
      "date": "2026-07-18",
      "day_name": "Thu",
      "calories": 1850.5,
      "carbs": 195.3,
      "sugar": 32.5,
      "fiber": 20.5,
      "glycemic_score": 16.2
    },
    ...
  ]
}
```

---

## AI Meal Planner

### 1. Generate Meal Plan
**POST** `/meal-planner/generate`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "plan_date": "2026-07-25",
  "budget": 50000,
  "available_ingredients": ["beras merah", "telur", "tempe", "bayam"],
  "food_preferences": ["tidak pedas", "rendah garam"]
}
```

All fields optional except auth. AI akan menyesuaikan dengan profil user.

Response:
```json
{
  "message": "Meal plan berhasil dibuat",
  "data": {
    "meal_plan": {
      "id": "uuid",
      "user_id": "uuid",
      "plan_date": "2026-07-25",
      "breakfast_items": [
        {
          "food_name": "Oatmeal",
          "portion_grams": 40,
          "calories": 150,
          "carbs": 27,
          "protein": 5,
          "fat": 3,
          "fiber": 4,
          "sugar": 1,
          "estimated_cost": 8000
        }
      ],
      "lunch_items": [...],
      "dinner_items": [...],
      "snack_items": [...],
      "total_calories": 2050,
      "total_carbs": 245,
      "total_protein": 98,
      "total_fat": 68,
      "total_fiber": 30,
      "total_sugar": 48,
      "estimated_total_cost": 48000,
      "ai_insight": "Menu ini dirancang khusus untuk Anda dengan fokus pada GI rendah..."
    },
    "daily_targets": {...}
  }
}
```

### 2. Get All Meal Plans
**GET** `/meal-planner`

Headers: `Authorization: Bearer {token}`

### 3. Get Meal Plan Detail
**GET** `/meal-planner/{id}`

Headers: `Authorization: Bearer {token}`

### 4. Delete Meal Plan
**DELETE** `/meal-planner/{id}`

Headers: `Authorization: Bearer {token}`

---

## NutriBot (AI Chatbot with RAG)

### Chat
**POST** `/chatbot`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "message": "Apa perbedaan diabetes tipe 1 dan tipe 2?"
}
```

Response:
```json
{
  "reply": "Diabetes Mellitus Tipe 1 adalah kondisi autoimun di mana sistem kekebalan tubuh menyerang sel-sel beta pankreas yang memproduksi insulin. Biasanya muncul sejak usia muda dan memerlukan insulin seumur hidup.\n\nSementara Diabetes Mellitus Tipe 2 terjadi ketika tubuh tidak dapat menggunakan insulin dengan efektif (resistensi insulin). Biasanya berkembang pada usia dewasa dan sangat terkait dengan gaya hidup seperti obesitas dan kurang aktivitas fisik. DM Tipe 2 dapat dikelola dengan perubahan gaya hidup, obat oral, dan kadang insulin."
}
```

Topik yang bisa ditanyakan:
- Edukasi Diabetes Mellitus
- Indeks Glikemik (GI) dan Glycemic Load
- Informasi makanan dan nutrisi (TKPI)
- Angka Kecukupan Gizi (AKG)
- Pangan lokal Indonesia untuk diabetes

---

## Health Report

### Generate Health Report
**POST** `/health-report/generate`

Headers: `Authorization: Bearer {token}`

Request Body:
```json
{
  "start_date": "2026-07-01",
  "end_date": "2026-07-24"
}
```

Response:
```json
{
  "message": "Health report generated successfully",
  "data": {
    "user_info": {
      "name": "John Doe",
      "age": 35,
      "gender": "Laki-laki",
      "height_cm": 170,
      "weight_kg": 75,
      "bmi": 25.95,
      "diabetes_status": "dm_type_2"
    },
    "period": {
      "start_date": "2026-07-01",
      "end_date": "2026-07-24",
      "duration_days": 24,
      "total_menus": 68
    },
    "nutrition_summary": {
      "avg_calories": 1850.5,
      "avg_carbs": 195.3,
      "avg_sugar": 28.5,
      "avg_protein": 72.3,
      "avg_fat": 58.5,
      "avg_fiber": 22.1
    },
    "glycemic_summary": {
      "avg_glycemic_score": 14.5,
      "low_risk_count": 32,
      "medium_risk_count": 28,
      "high_risk_count": 8
    },
    "meal_history": [...],
    "charts": {
      "daily_sugar_consumption": [...],
      "macronutrient_distribution": {...},
      "glycemic_score_trend": [...]
    },
    "activity_summary": {
      "total_scans": 68,
      "active_days": 22
    },
    "ai_insight": "Selama 24 hari terakhir, pola konsumsi Anda menunjukkan...",
    "ai_recommendation": "Untuk minggu depan, fokus pada..."
  }
}
```

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found
```json
{
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### 500 Server Error
```json
{
  "error": "Internal server error",
  "message": "..."
}
```

---

## Important Notes

1. **Pinecone Namespaces Required:**
   - `tkpi-indonesia`: Nutrition data (TKPI)
   - `knowledge-diabetes`: Diabetes education for chatbot

2. **Glycemic Score Calculation:**
   - Glycemic Load = (GI × Carbs) / 100
   - Risk Categories:
     - Low: < 11
     - Medium: 11-19
     - High: ≥ 20

3. **AI Models:**
   - Gemini: `gemini-2.0-flash-exp`
   - Embedding: `text-embedding-004`

4. **Health Targets:**
   - `stable_blood_sugar`: Menjaga kadar gula darah tetap stabil
   - `reduce_sugar_intake`: Mengurangi konsumsi gula harian
   - `control_carbs`: Mengontrol asupan karbohidrat
   - `lose_weight`: Menurunkan berat badan secara sehat
   - `healthy_diet`: Menjaga pola makan yang lebih sehat

---

**Last Updated:** 2026-07-24
**Version:** 2.0 (Diabetes Mellitus System)
