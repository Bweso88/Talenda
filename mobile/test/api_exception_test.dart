import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:talenda_parents/core/api/api_exception.dart';

void main() {
  final request = RequestOptions(path: '/children');

  test('reprend le message de validation de l\'API', () {
    final error = ApiException.from(
      DioException(
        requestOptions: request,
        response: Response(
          requestOptions: request,
          statusCode: 422,
          data: {
            'message': 'Données invalides',
            'errors': {
              'login': ['Identifiants incorrects.'],
            },
          },
        ),
      ),
    );

    expect(error.message, 'Identifiants incorrects.');
    expect(error.fieldErrors['login'], 'Identifiants incorrects.');
  });

  test('signale l\'absence de réseau pour basculer sur le cache', () {
    final error = ApiException.from(DioException(requestOptions: request, type: DioExceptionType.connectionError));

    expect(error.isOffline, isTrue);
    expect(error.message, contains('Pas de connexion'));
  });

  test('reconnaît une session expirée', () {
    final error = ApiException.from(
      DioException(
        requestOptions: request,
        response: Response(requestOptions: request, statusCode: 401),
      ),
    );

    expect(error.isUnauthenticated, isTrue);
  });
}
