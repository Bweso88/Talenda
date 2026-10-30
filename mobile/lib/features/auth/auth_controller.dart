import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import 'models.dart';

/// Session du parent : compte, écoles accessibles, école choisie.
class AuthState {
  const AuthState({required this.user, required this.schools, this.school});

  final AppUser user;
  final List<School> schools;
  final School? school;

  AuthState copyWith({AppUser? user, School? school}) => AuthState(user: user ?? this.user, schools: schools, school: school ?? this.school);
}

final authControllerProvider = AsyncNotifierProvider<AuthController, AuthState?>(AuthController.new);

class AuthController extends AsyncNotifier<AuthState?> {
  @override
  Future<AuthState?> build() async {
    final store = ref.read(sessionStoreProvider);

    if (store.token == null) {
      return null;
    }

    try {
      return await _loadProfile();
    } on ApiException catch (error) {
      if (error.isUnauthenticated) {
        await store.clear();
        return null;
      }
      rethrow;
    }
  }

  Future<void> login(String login, String password) async {
    final api = ref.read(apiClientProvider);
    final store = ref.read(sessionStoreProvider);

    final response = await api.post('/auth/login', {'login': login.trim(), 'password': password, 'device_name': 'App parents Talenda'});
    await store.saveToken(response['token'] as String);
    state = AsyncData(await _loadProfile());
  }

  Future<void> selectSchool(School school) async {
    await ref.read(sessionStoreProvider).saveTenant(school.uuid);
    final current = state.value;
    if (current != null) state = AsyncData(current.copyWith(school: school));
  }

  Future<void> changePassword(String current, String password, String confirmation) async {
    final response = await ref.read(apiClientProvider).post('/auth/password', {
      'current_password': current,
      'password': password,
      'password_confirmation': confirmation,
    });
    final user = AppUser.fromJson(response['user'] as Map<String, dynamic>);
    final existing = state.value;
    if (existing != null) state = AsyncData(existing.copyWith(user: user));
  }

  Future<void> logout() async {
    try {
      await ref.read(apiClientProvider).post('/auth/logout');
    } on ApiException {
      // Hors ligne : on déconnecte quand même l'appareil.
    }
    await ref.read(sessionStoreProvider).clear();
    state = const AsyncData(null);
  }

  Future<AuthState> _loadProfile() async {
    final store = ref.read(sessionStoreProvider);
    final me = (await ref.read(apiClientProvider).getCached('/me')).data;
    final schools = [
      for (final membership in me['memberships'] as List) School.fromMembershipJson(membership as Map<String, dynamic>),
    ].where((school) => school.isParent).toList();

    School? school;
    for (final candidate in schools) {
      if (candidate.uuid == store.tenantUuid) school = candidate;
    }
    if (school == null && schools.length == 1) {
      school = schools.single;
      await store.saveTenant(school.uuid);
    }

    return AuthState(user: AppUser.fromJson(me['user'] as Map<String, dynamic>), schools: schools, school: school);
  }
}
