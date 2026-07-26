<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Health Report - SiNutri</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
        }
        .container {
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #2563eb;
            font-size: 24pt;
            margin-bottom: 5px;
        }
        .header p {
            color: #666;
            font-size: 10pt;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background: #2563eb;
            color: white;
            padding: 8px 12px;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 5px 10px;
            width: 40%;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-value {
            display: table-cell;
            padding: 5px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        .stat-box {
            display: table-cell;
            text-align: center;
            padding: 15px;
            background: #f3f4f6;
            border-radius: 8px;
            margin: 5px;
            width: 33%;
        }
        .stat-value {
            font-size: 18pt;
            font-weight: bold;
            color: #2563eb;
        }
        .stat-label {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }
        .risk-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 9pt;
        }
        .risk-low { background: #dcfce7; color: #166534; }
        .risk-medium { background: #fef3c7; color: #92400e; }
        .risk-high { background: #fee2e2; color: #991b1b; }
        .meal-item {
            padding: 8px;
            margin-bottom: 5px;
            background: #f9fafb;
            border-left: 3px solid #2563eb;
        }
        .meal-item strong {
            color: #2563eb;
        }
        .insight-box {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin-top: 10px;
        }
        .recommendation-box {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
            padding: 15px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
            color: #999;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f3f4f6;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>SiNutri Health Report</h1>
            <p>Laporan Kesehatan Diabetes Mellitus</p>
        </div>

        <!-- User Info -->
        <div class="section">
            <div class="section-title">Informasi Pengguna</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Nama</div>
                    <div class="info-value">{{ $data['user_info']['name'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Usia</div>
                    <div class="info-value">{{ $data['user_info']['age'] }} tahun</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Jenis Kelamin</div>
                    <div class="info-value">{{ $data['user_info']['gender'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tinggi Badan</div>
                    <div class="info-value">{{ $data['user_info']['height_cm'] }} cm</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Berat Badan</div>
                    <div class="info-value">{{ $data['user_info']['weight_kg'] }} kg</div>
                </div>
                <div class="info-row">
                    <div class="info-label">BMI</div>
                    <div class="info-value">{{ $data['user_info']['bmi'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status Diabetes</div>
                    <div class="info-value">
                        @if($data['user_info']['diabetes_status'] == 'dm_type_1')
                            Diabetes Mellitus Tipe 1
                        @elseif($data['user_info']['diabetes_status'] == 'dm_type_2')
                            Diabetes Mellitus Tipe 2
                        @elseif($data['user_info']['diabetes_status'] == 'prediabetes')
                            Prediabetes
                        @else
                            Belum Terdiagnosis
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Period -->
        <div class="section">
            <div class="section-title">Periode Laporan</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Tanggal Mulai</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($data['period']['start_date'])->format('d F Y') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tanggal Akhir</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($data['period']['end_date'])->format('d F Y') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Durasi</div>
                    <div class="info-value">{{ $data['period']['duration_days'] }} hari</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total Menu Tercatat</div>
                    <div class="info-value">{{ $data['period']['total_menus'] }} menu</div>
                </div>
            </div>
        </div>

        <!-- Nutrition Summary -->
        <div class="section">
            <div class="section-title">Ringkasan Nutrisi Rata-rata Harian</div>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value">{{ round($data['nutrition_summary']['avg_calories']) }}</div>
                    <div class="stat-label">Kalori (kkal)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">{{ round($data['nutrition_summary']['avg_carbs'], 1) }}</div>
                    <div class="stat-label">Karbohidrat (g)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">{{ round($data['nutrition_summary']['avg_sugar'], 1) }}</div>
                    <div class="stat-label">Gula (g)</div>
                </div>
            </div>
            <div class="stats-grid" style="margin-top: 10px;">
                <div class="stat-box">
                    <div class="stat-value">{{ round($data['nutrition_summary']['avg_protein'], 1) }}</div>
                    <div class="stat-label">Protein (g)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">{{ round($data['nutrition_summary']['avg_fat'], 1) }}</div>
                    <div class="stat-label">Lemak (g)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">{{ round($data['nutrition_summary']['avg_fiber'], 1) }}</div>
                    <div class="stat-label">Serat (g)</div>
                </div>
            </div>
        </div>

        <!-- Glycemic Summary -->
        <div class="section">
            <div class="section-title">Ringkasan Glycemic Risk</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Rata-rata Glycemic Score</div>
                    <div class="info-value"><strong>{{ round($data['glycemic_summary']['avg_glycemic_score'], 1) }}</strong></div>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <strong>Distribusi Kategori Risiko:</strong>
                <div style="margin-top: 10px;">
                    <span class="risk-badge risk-low">Rendah: {{ $data['glycemic_summary']['low_risk_count'] }} menu</span>
                    <span class="risk-badge risk-medium">Sedang: {{ $data['glycemic_summary']['medium_risk_count'] }} menu</span>
                    <span class="risk-badge risk-high">Tinggi: {{ $data['glycemic_summary']['high_risk_count'] }} menu</span>
                </div>
            </div>
        </div>

        <div class="page-break"></div>

        <!-- Meal History -->
        <div class="section">
            <div class="section-title">Riwayat Konsumsi</div>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Waktu Makan</th>
                        <th>Menu</th>
                        <th>Kalori</th>
                        <th>Karbo</th>
                        <th>Gula</th>
                        <th>Risiko</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['meal_history'] as $meal)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($meal['date'])->format('d/m/Y') }}</td>
                        <td>{{ $meal['time'] }} ({{ ucfirst($meal['meal_type']) }})</td>
                        <td>{{ $meal['food_name'] }}</td>
                        <td>{{ round($meal['calories']) }}</td>
                        <td>{{ round($meal['carbs'], 1) }}g</td>
                        <td>{{ round($meal['sugar'], 1) }}g</td>
                        <td>
                            <span class="risk-badge risk-{{ $meal['risk_category'] }}">
                                {{ ucfirst($meal['risk_category']) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Activity Summary -->
        <div class="section">
            <div class="section-title">Ringkasan Aktivitas</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Total Scan Makanan</div>
                    <div class="info-value">{{ $data['activity_summary']['total_scans'] }} kali</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Hari Aktif</div>
                    <div class="info-value">{{ $data['activity_summary']['active_days'] }} hari</div>
                </div>
            </div>
        </div>

        <!-- AI Insight -->
        <div class="section">
            <div class="section-title">AI Insight</div>
            <div class="insight-box">
                {{ $data['ai_insight'] }}
            </div>
        </div>

        <!-- AI Recommendation -->
        <div class="section">
            <div class="section-title">AI Recommendation</div>
            <div class="recommendation-box">
                {{ $data['ai_recommendation'] }}
            </div>
        </div>

        <!-- Disclaimer -->
        <div class="section">
            <div class="section-title">Disclaimer</div>
            <p style="font-size: 9pt; text-align: justify;">
                Laporan ini merupakan ringkasan data konsumsi makanan dan hasil analisis otomatis berdasarkan data yang dimasukkan pengguna. 
                Informasi yang disajikan bertujuan sebagai pendukung edukasi dan pemantauan pola makan, serta tidak menggantikan diagnosis 
                atau konsultasi medis profesional. Untuk pengelolaan diabetes yang optimal, konsultasikan dengan dokter atau ahli gizi terdaftar.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>SiNutri - AI Dietary Decision Support System for Diabetes Mellitus</p>
            <p>Generated on {{ \Carbon\Carbon::now()->format('d F Y, H:i') }}</p>
        </div>
    </div>
</body>
</html>
