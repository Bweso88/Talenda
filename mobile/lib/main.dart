import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'app.dart';
import 'core/providers.dart';
import 'core/storage/session_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('fr_FR');

  final store = SessionStore();
  await store.load();

  runApp(ProviderScope(overrides: [sessionStoreProvider.overrideWithValue(store)], child: const TalendaApp()));
}
