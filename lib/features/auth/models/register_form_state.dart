class RegisterFormState {
  final String fullName;
  final String email;
  final String password;
  final String dob; // mm/dd/yyyy or yyyy-mm-dd
  final List<String> allergies;

  RegisterFormState({
    this.fullName = '',
    this.email = '',
    this.password = '',
    this.dob = '',
    this.allergies = const [],
  });

  RegisterFormState copyWith({
    String? fullName,
    String? email,
    String? password,
    String? dob,
    List<String>? allergies,
  }) {
    return RegisterFormState(
      fullName: fullName ?? this.fullName,
      email: email ?? this.email,
      password: password ?? this.password,
      dob: dob ?? this.dob,
      allergies: allergies ?? this.allergies,
    );
  }
}
