import 'meal_plan.dart';

class MealPlannerState {
  final bool isLoading;
  final MealPlan? currentPlan;
  final String? error;
  final List<MealPlan> savedPlans;

  MealPlannerState({
    this.isLoading = false,
    this.currentPlan,
    this.error,
    this.savedPlans = const [],
  });

  MealPlannerState copyWith({
    bool? isLoading,
    MealPlan? currentPlan,
    String? error,
    List<MealPlan>? savedPlans,
  }) {
    return MealPlannerState(
      isLoading: isLoading ?? this.isLoading,
      currentPlan: currentPlan ?? this.currentPlan,
      error: error ?? this.error,
      savedPlans: savedPlans ?? this.savedPlans,
    );
  }
}
