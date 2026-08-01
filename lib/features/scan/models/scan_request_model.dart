class ScanRequestModel {
  final String? imagePath;
  final String targetProfileName;
  final String notes;
  final String description;

  ScanRequestModel({
    this.imagePath,
    this.targetProfileName = 'Saya',
    this.notes = '',
    this.description = '',
  });

  ScanRequestModel copyWith({
    String? imagePath,
    String? targetProfileName,
    String? notes,
    String? description,
  }) {
    return ScanRequestModel(
      imagePath: imagePath ?? this.imagePath,
      targetProfileName: targetProfileName ?? this.targetProfileName,
      notes: notes ?? this.notes,
      description: description ?? this.description,
    );
  }
}
