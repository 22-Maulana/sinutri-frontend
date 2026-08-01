class ApiConstants {
  static const String baseUrl = 'https://backend-sinutri.ardhiansyah.web.id';
  static const String apiBaseUrl = '$baseUrl/api';

  // Auth Endpoints
  static const String login = '$apiBaseUrl/login';
  static const String register = '$apiBaseUrl/register';
  static const String verifyOtp = '$apiBaseUrl/verify-otp';
  static const String resendOtp = '$apiBaseUrl/resend-otp';

  // Profile Endpoints
  static const String profile = '$apiBaseUrl/profile';
  static const String profileMother = '$apiBaseUrl/profile/mother';
  static const String profileChild = '$apiBaseUrl/profile/child';

  // Feature Endpoints
  static const String scan = '$apiBaseUrl/scan';
  static const String chatbot = '$apiBaseUrl/chatbot';
  static const String mealPlanner = '$apiBaseUrl/meal-planner';
  static const String mealPlannerGenerate = '$apiBaseUrl/meal-planner/generate';

  // Data Endpoints
  static const String foodLogs = '$apiBaseUrl/food-logs';
  static const String growthRecords = '$apiBaseUrl/growth-records';
  static const String dashboardSummary = '$apiBaseUrl/dashboard/summary';
  static const String dashboardWeekly = '$apiBaseUrl/dashboard/weekly';

  // Health Report Endpoints
  static const String healthReportGenerate = '$apiBaseUrl/health-report/generate';
  static const String healthReportExportPdf = '$apiBaseUrl/health-report/export-pdf';
}

