import 'package:flutter/material.dart';
import '../constants/app_colors.dart';

class StepProgressIndicator extends StatelessWidget {
  final int currentStep;
  final int totalSteps;

  const StepProgressIndicator({
    super.key, 
    required this.currentStep,
    this.totalSteps = 3,
  });

  @override
  Widget build(BuildContext context) {
    List<Widget> children = [];
    
    for (int i = 1; i <= totalSteps; i++) {
      children.add(_buildStep(
        i, 
        _getStepTitle(i),
        isActive: currentStep >= i, 
        isCompleted: currentStep > i,
      ));
      
      if (i < totalSteps) {
        children.add(_buildLine(isActive: currentStep > i));
      }
    }
    
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: children,
    );
  }

  String _getStepTitle(int step) {
    if (totalSteps == 3) {
      switch (step) {
        case 1: return 'Akun';
        case 2: return 'Profil';
        case 3: return 'Selesai';
        default: return 'Step $step';
      }
    } else {
      return 'Step $step';
    }
  }

  Widget _buildStep(int step, String title, {required bool isActive, required bool isCompleted}) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: isCompleted ? AppColors.primary : (isActive ? AppColors.primary : Colors.transparent),
            border: Border.all(
              color: isActive || isCompleted ? AppColors.primary : AppColors.textSecondary.withOpacity(0.3),
              width: 2,
            ),
          ),
          child: Center(
            child: isCompleted
                ? const Icon(Icons.check, color: AppColors.white, size: 18)
                : Text(
                    step.toString(),
                    style: TextStyle(
                      color: isActive ? AppColors.white : AppColors.textSecondary.withOpacity(0.5),
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          title,
          style: TextStyle(
            fontSize: 10,
            fontWeight: isActive || isCompleted ? FontWeight.w600 : FontWeight.normal,
            color: isActive || isCompleted ? AppColors.primary : AppColors.textSecondary.withOpacity(0.5),
          ),
        ),
      ],
    );
  }

  Widget _buildLine({required bool isActive}) {
    return Expanded(
      child: Container(
        margin: const EdgeInsets.only(bottom: 20, left: 8, right: 8),
        height: 2,
        color: isActive ? AppColors.primary : AppColors.textSecondary.withOpacity(0.2),
      ),
    );
  }
}
