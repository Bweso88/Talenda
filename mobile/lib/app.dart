import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/theme.dart';
import 'features/auth/auth_controller.dart';
import 'features/auth/change_password_screen.dart';
import 'features/auth/login_screen.dart';
import 'features/auth/school_picker_screen.dart';
import 'features/children/child_screen.dart';
import 'features/children/home_screen.dart';
import 'features/notifications/notifications_screen.dart';
import 'features/pdf/pdf_screen.dart';
import 'features/results/result_detail_screen.dart';

/// Navigation : la session décide de l'écran (connexion, mot de passe
/// provisoire à changer, choix de l'école, accueil).
final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen(authControllerProvider, (_, _) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final path = state.matchedLocation;

      if (auth.isLoading && !auth.hasValue) return path == '/chargement' ? null : '/chargement';

      final session = auth.value;
      if (session == null) return path == '/connexion' ? null : '/connexion';
      if (session.user.mustChangePassword) return path == '/mot-de-passe' ? null : '/mot-de-passe';
      if (session.school == null) return path == '/ecoles' ? null : '/ecoles';
      if (path == '/connexion' || path == '/chargement') return '/';

      return null;
    },
    routes: [
      GoRoute(
        path: '/chargement',
        builder: (_, _) => const Scaffold(body: Center(child: CircularProgressIndicator())),
      ),
      GoRoute(path: '/connexion', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/mot-de-passe', builder: (_, _) => const ChangePasswordScreen()),
      GoRoute(path: '/ecoles', builder: (_, _) => const SchoolPickerScreen()),
      GoRoute(path: '/', builder: (_, _) => const HomeScreen()),
      GoRoute(
        path: '/enfants/:uuid',
        builder: (_, state) => ChildScreen(uuid: state.pathParameters['uuid']!, initialTab: int.tryParse(state.uri.queryParameters['onglet'] ?? '') ?? 0),
      ),
      GoRoute(path: '/notifications', builder: (_, _) => const NotificationsScreen()),
      GoRoute(
        path: '/enfants/:uuid/bulletins/:result',
        builder: (_, state) => ResultDetailScreen(child: state.pathParameters['uuid']!, result: state.pathParameters['result']!),
      ),
      GoRoute(
        path: '/document',
        builder: (_, state) => PdfScreen(request: state.extra! as PdfRequest),
      ),
    ],
  );
});

class TalendaApp extends ConsumerWidget {
  const TalendaApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MaterialApp.router(
      title: 'Talenda Parents',
      debugShowCheckedModeBanner: false,
      theme: talendaTheme(),
      locale: const Locale('fr'),
      supportedLocales: const [Locale('fr')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      routerConfig: ref.watch(routerProvider),
    );
  }
}
