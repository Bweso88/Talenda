import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:talenda_parents/core/api/api_client.dart';
import 'package:talenda_parents/core/api/api_exception.dart';
import 'package:talenda_parents/core/theme.dart';
import 'package:talenda_parents/features/attendance/attendance_tab.dart';
import 'package:talenda_parents/features/auth/auth_controller.dart';
import 'package:talenda_parents/features/auth/login_screen.dart';
import 'package:talenda_parents/features/auth/models.dart';
import 'package:talenda_parents/features/children/home_screen.dart';
import 'package:talenda_parents/features/children/models.dart';
import 'package:talenda_parents/features/children/parent_repository.dart';
import 'package:talenda_parents/features/results/result_detail_screen.dart';

import 'helpers.dart';

class _SignedIn extends AuthController {
  @override
  Future<AuthState?> build() async => const AuthState(
    user: AppUser(uuid: 'u', name: 'Prisca Mabiala', mustChangePassword: false),
    schools: [
      School(uuid: 's', name: 'Lycée Démo de Brazzaville', roles: ['parent']),
    ],
    school: School(uuid: 's', name: 'Lycée Démo de Brazzaville', roles: ['parent']),
  );
}

class _WrongPassword extends AuthController {
  @override
  Future<AuthState?> build() async => null;

  @override
  Future<void> login(String login, String password) async => throw ApiException('Identifiants incorrects.', statusCode: 422);
}

Widget _app(Widget child, List overrides) => ProviderScope(
  overrides: [...overrides],
  child: MaterialApp(theme: talendaTheme(), home: child),
);

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  testWidgets('affiche les identifiants refusés sur l\'écran de connexion', (tester) async {
    await tester.pumpWidget(_app(const LoginScreen(), [authControllerProvider.overrideWith(_WrongPassword.new)]));

    await tester.tap(find.text('Se connecter'));
    await tester.pump();
    expect(find.text('Indiquez votre téléphone.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('login')), '06 333 33 33');
    await tester.enterText(find.byKey(const Key('password')), 'mauvais');
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();

    expect(find.text('Identifiants incorrects.'), findsOneWidget);
  });

  testWidgets('accueil : un résumé par enfant', (tester) async {
    final children = [for (final item in fixture('children')['data'] as List) Child.fromJson(item as Map<String, dynamic>)];

    await tester.pumpWidget(
      _app(const HomeScreen(), [authControllerProvider.overrideWith(_SignedIn.new), childrenProvider.overrideWith((ref) async => Cached(children))]),
    );
    await tester.pumpAndSettle();

    expect(find.text('Bonjour, Prisca Mabiala'), findsOneWidget);
    expect(find.text('MES ENFANTS (2)'), findsOneWidget);
    expect(find.text('MABIALA Arsène'), findsOneWidget);
    expect(find.textContaining('Moy. 11,68'), findsOneWidget);
    expect(find.textContaining('2 retards'), findsOneWidget);
  });

  testWidgets('affiche le bandeau hors ligne avec la date des données', (tester) async {
    final children = [for (final item in fixture('children')['data'] as List) Child.fromJson(item as Map<String, dynamic>)];

    await tester.pumpWidget(
      _app(const HomeScreen(), [
        authControllerProvider.overrideWith(_SignedIn.new),
        childrenProvider.overrideWith((ref) async => Cached(children, cachedAt: DateTime(2026, 11, 16, 9, 30))),
      ]),
    );
    await tester.pumpAndSettle();

    expect(find.text('Hors ligne — données du 16/11 à 09h30'), findsOneWidget);
  });

  testWidgets('bulletin : moyennes par matière et appréciation du conseil', (tester) async {
    final detail = TermResultDetail.fromJson(fixture('result')['data'] as Map<String, dynamic>);

    await tester.pumpWidget(_app(const ResultDetailScreen(child: 'c', result: 'r'), [resultDetailProvider.overrideWith((ref, key) async => Cached(detail))]));
    await tester.pumpAndSettle();

    expect(find.text('11,68 / 20'), findsOneWidget);
    expect(find.text('Mathématiques'), findsOneWidget);
    await tester.scrollUntilVisible(find.textContaining('physique-chimie'), 300);
    expect(find.textContaining('physique-chimie'), findsOneWidget);
  });

  testWidgets('absences : totaux, absences non justifiées et justificatif en attente', (tester) async {
    final life = SchoolLife.fromJson(fixture('attendance')['data'] as Map<String, dynamic>);

    await tester.pumpWidget(
      _app(const Scaffold(body: AttendanceTab(child: 'c')), [attendanceProvider.overrideWith((ref, child) async => Cached<SchoolLife?>(life))]),
    );
    await tester.pumpAndSettle();

    expect(find.text('Justifier une absence'), findsOneWidget);
    expect(find.text('Non justifiée'), findsWidgets);
    await tester.scrollUntilVisible(find.text('À examiner'), 200);
    expect(find.text('À examiner'), findsOneWidget);
  });
}
