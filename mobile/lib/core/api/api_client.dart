import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../storage/session_store.dart';
import 'api_exception.dart';

/// Adresse de l'API, fixée à la compilation :
/// `flutter run --dart-define=API_URL=https://talenda.example.cg/api/v1`.
const apiUrl = String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');

/// Réponse lue sur le réseau, ou sur le cache si le réseau manque.
class Cached<T> {
  const Cached(this.data, {this.cachedAt});

  final T data;

  /// Renseigné quand les données viennent du cache (hors ligne).
  final DateTime? cachedAt;
}

/// Client de l'API v1 : jeton Bearer, école active (en-tête X-Tenant),
/// erreurs traduites en français.
class ApiClient {
  ApiClient(this._store, {Dio? dio, String baseUrl = apiUrl})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              baseUrl: baseUrl,
              connectTimeout: const Duration(seconds: 15),
              receiveTimeout: const Duration(seconds: 30),
              headers: {'Accept': 'application/json'},
            ),
          ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = _store.token;
          final tenant = _store.tenantUuid;
          if (token != null) options.headers['Authorization'] = 'Bearer $token';
          if (tenant != null) options.headers['X-Tenant'] = tenant;
          handler.next(options);
        },
      ),
    );
  }

  final SessionStore _store;
  final Dio _dio;

  Future<Map<String, dynamic>> get(String path) => _send(() => _dio.get<Map<String, dynamic>>(path));

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) => _send(() => _dio.post<Map<String, dynamic>>(path, data: body));

  /// Lecture avec repli sur la dernière réponse connue (lecture hors ligne).
  Future<Cached<Map<String, dynamic>>> getCached(String path) async {
    final key = '${_store.tenantUuid}:$path';

    try {
      final json = await get(path);
      await JsonCache.put(key, jsonEncode(json));
      return Cached(json);
    } on ApiException catch (error) {
      final cached = error.isOffline ? await JsonCache.get(key) : null;
      if (cached == null) rethrow;
      return Cached(jsonDecode(cached.json) as Map<String, dynamic>, cachedAt: cached.at);
    }
  }

  /// Document PDF (reçu, bulletin).
  Future<Uint8List> pdf(String path) async {
    try {
      final response = await _dio.get<List<int>>(
        path,
        options: Options(responseType: ResponseType.bytes, headers: {'Accept': 'application/pdf'}),
      );
      return Uint8List.fromList(response.data ?? const []);
    } catch (error) {
      throw ApiException.from(error);
    }
  }

  Future<Map<String, dynamic>> _send(Future<Response<Map<String, dynamic>>> Function() request) async {
    try {
      final response = await request();
      return response.data ?? const {};
    } catch (error) {
      throw ApiException.from(error);
    }
  }
}
