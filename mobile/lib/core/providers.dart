import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api/api_client.dart';
import 'storage/session_store.dart';

/// Chargé au démarrage (main.dart) puis injecté.
final sessionStoreProvider = Provider<SessionStore>((ref) => throw UnimplementedError('SessionStore non chargé'));

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient(ref.watch(sessionStoreProvider)));
