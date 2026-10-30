import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Jeton de connexion (stockage chiffré) et école choisie.
class SessionStore {
  SessionStore({FlutterSecureStorage? secure}) : _secure = secure ?? const FlutterSecureStorage();

  final FlutterSecureStorage _secure;

  static const _tokenKey = 'talenda.token';
  static const _tenantKey = 'talenda.tenant';

  String? token;
  String? tenantUuid;

  Future<void> load() async {
    token = await _secure.read(key: _tokenKey);
    tenantUuid = (await SharedPreferences.getInstance()).getString(_tenantKey);
  }

  Future<void> saveToken(String value) async {
    token = value;
    await _secure.write(key: _tokenKey, value: value);
  }

  Future<void> saveTenant(String? uuid) async {
    tenantUuid = uuid;
    final prefs = await SharedPreferences.getInstance();
    uuid == null ? await prefs.remove(_tenantKey) : await prefs.setString(_tenantKey, uuid);
  }

  Future<void> clear() async {
    token = null;
    tenantUuid = null;
    await _secure.delete(key: _tokenKey);
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tenantKey);
    await JsonCache.clear();
  }
}

/// Dernière réponse connue de chaque écran : l'app reste consultable sans
/// réseau (« données du 16/11 à 9 h 30 »).
abstract final class JsonCache {
  static const _prefix = 'talenda.cache.';

  static Future<void> put(String key, String json) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('$_prefix$key', json);
    await prefs.setString('$_prefix$key.at', DateTime.now().toIso8601String());
  }

  static Future<({String json, DateTime at})?> get(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final json = prefs.getString('$_prefix$key');
    final at = prefs.getString('$_prefix$key.at');
    return json == null || at == null ? null : (json: json, at: DateTime.parse(at));
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    for (final key in prefs.getKeys().where((key) => key.startsWith(_prefix))) {
      await prefs.remove(key);
    }
  }
}
