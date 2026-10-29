import 'package:dio/dio.dart';

/// Erreur de l'API traduite en message lisible par un parent.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.fieldErrors = const {}, this.isOffline = false});

  final String message;
  final int? statusCode;
  final Map<String, String> fieldErrors;

  /// Pas de réseau : l'app affiche alors les dernières données connues.
  final bool isOffline;

  bool get isUnauthenticated => statusCode == 401;

  factory ApiException.from(Object error) {
    if (error is ApiException) {
      return error;
    }

    if (error is! DioException) {
      return ApiException('Une erreur inattendue est survenue.');
    }

    final response = error.response;

    if (response == null) {
      return ApiException('Pas de connexion internet. Vérifiez votre réseau.', isOffline: true);
    }

    final data = response.data;
    final status = response.statusCode;

    if (status == 422 && data is Map && data['errors'] is Map) {
      final fields = <String, String>{
        for (final entry in (data['errors'] as Map).entries)
          if (entry.value is List && (entry.value as List).isNotEmpty) entry.key.toString(): (entry.value as List).first.toString(),
      };
      return ApiException(fields.values.isEmpty ? 'Données invalides.' : fields.values.first, statusCode: status, fieldErrors: fields);
    }

    return ApiException(switch (status) {
      401 => 'Votre session a expiré. Reconnectez-vous.',
      404 => 'Élément introuvable.',
      429 => 'Trop de tentatives. Patientez une minute.',
      _ => 'Le serveur ne répond pas correctement ($status). Réessayez plus tard.',
    }, statusCode: status);
  }

  @override
  String toString() => message;
}
