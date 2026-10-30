import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/providers.dart';

/// Notification de l'école (bulletin, absence, paiement, annonce…).
class AppNotification {
  const AppNotification({
    required this.uuid,
    required this.type,
    required this.title,
    required this.body,
    required this.data,
    required this.read,
    required this.createdAt,
  });

  final String uuid;
  final String type;
  final String title;
  final String body;
  final Map<String, dynamic> data;
  final bool read;
  final DateTime createdAt;

  /// Enfant concerné, s'il y en a un.
  String? get child => data['child'] as String?;

  /// Onglet de la fiche enfant à ouvrir (0 scolarité, 1 notes, 2 absences).
  int get tab => switch (data['tab']) {
    'results' => 1,
    'attendance' => 2,
    _ => 0,
  };

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
    uuid: json['uuid'] as String,
    type: json['type'] as String,
    title: json['title'] as String,
    body: json['body'] as String,
    data: Map<String, dynamic>.from(json['data'] as Map? ?? const {}),
    read: json['read'] as bool,
    createdAt: DateTime.parse(json['created_at'] as String).toLocal(),
  );
}

class NotificationList {
  const NotificationList({required this.items, required this.unreadCount});

  final List<AppNotification> items;
  final int unreadCount;
}

class NotificationRepository {
  NotificationRepository(this._api);

  final ApiClient _api;

  Future<Cached<NotificationList>> list() async {
    final response = await _api.getCached('/notifications');
    return Cached(
      NotificationList(
        items: [for (final item in response.data['data'] as List) AppNotification.fromJson(item as Map<String, dynamic>)],
        unreadCount: response.data['unread_count'] as int? ?? 0,
      ),
      cachedAt: response.cachedAt,
    );
  }

  Future<void> markRead(String uuid) => _api.post('/notifications/$uuid/read');

  Future<void> markAllRead() => _api.post('/notifications/read-all');
}

final notificationRepositoryProvider = Provider<NotificationRepository>((ref) => NotificationRepository(ref.watch(apiClientProvider)));

final notificationsProvider = FutureProvider<Cached<NotificationList>>((ref) => ref.watch(notificationRepositoryProvider).list());
