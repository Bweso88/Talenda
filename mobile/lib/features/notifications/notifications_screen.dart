import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'notifications.dart';

/// Messages de l'école : bulletins, absences, paiements, annonces.
class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifications = ref.watch(notificationsProvider);
    final hasUnread = (notifications.value?.data.unreadCount ?? 0) > 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (hasUnread)
            TextButton(
              onPressed: () async {
                await _quietly(() => ref.read(notificationRepositoryProvider).markAllRead());
                ref.invalidate(notificationsProvider);
              },
              child: const Text('Tout marquer lu'),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(notificationsProvider.future),
        child: CachedAsync<NotificationList>(
          value: notifications,
          onRetry: () => ref.invalidate(notificationsProvider),
          builder: (list, cachedAt) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              OfflineBanner(cachedAt),
              if (list.items.isEmpty) const EmptyState(icon: Icons.notifications_none, message: 'Aucune notification pour le moment.'),
              for (final notification in list.items) ...[
                _NotificationCard(notification: notification, onTap: () => _open(context, ref, notification)),
                const SizedBox(height: 10),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _open(BuildContext context, WidgetRef ref, AppNotification notification) async {
    if (!notification.read) {
      await _quietly(() => ref.read(notificationRepositoryProvider).markRead(notification.uuid));
      ref.invalidate(notificationsProvider);
    }
    if (!context.mounted) return;

    final child = notification.child;
    if (child != null) {
      context.push('/enfants/$child?onglet=${notification.tab}');
      return;
    }

    // Annonce : le texte complet.
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (context) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              notification.title,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: TalendaColors.navy),
            ),
            const SizedBox(height: 12),
            Text(notification.body, style: const TextStyle(fontSize: 16, height: 1.45)),
          ],
        ),
      ),
    );
  }

  /// Hors ligne, marquer comme lu n'est pas bloquant.
  static Future<void> _quietly(Future<void> Function() action) async {
    try {
      await action();
    } on ApiException {
      // Ignoré : sera marqué lu à la prochaine ouverture en ligne.
    }
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.notification, required this.onTap});

  final AppNotification notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final (icon, color, background) = switch (notification.type) {
      'payment' => (Icons.payments_outlined, TalendaColors.success, TalendaColors.successSoft),
      'payment_reminder' => (Icons.notification_important_outlined, TalendaColors.danger, TalendaColors.dangerSoft),
      'absence' => (Icons.event_busy, TalendaColors.warning, TalendaColors.warningSoft),
      'report_card' => (Icons.school_outlined, TalendaColors.blue, TalendaColors.field),
      'justification' => (Icons.fact_check_outlined, TalendaColors.teal, TalendaColors.field),
      _ => (Icons.campaign_outlined, TalendaColors.navy, const Color(0xFFFFF0D6)),
    };

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: background,
                child: Icon(icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            notification.title,
                            style: TextStyle(fontWeight: notification.read ? FontWeight.w600 : FontWeight.w800, color: TalendaColors.navy),
                          ),
                        ),
                        Text(_when(notification.createdAt), style: const TextStyle(color: TalendaColors.muted, fontSize: 12)),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      notification.body,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: TalendaColors.muted, height: 1.35),
                    ),
                  ],
                ),
              ),
              if (!notification.read) ...[
                const SizedBox(width: 8),
                Container(
                  width: 10,
                  height: 10,
                  margin: const EdgeInsets.only(top: 6),
                  decoration: const BoxDecoration(color: TalendaColors.orange, shape: BoxShape.circle),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  static String _when(DateTime date) {
    final elapsed = DateTime.now().difference(date);
    if (elapsed.inMinutes < 1) return 'à l\'instant';
    if (elapsed.inMinutes < 60) return 'il y a ${elapsed.inMinutes} min';
    if (elapsed.inHours < 24) return 'il y a ${elapsed.inHours} h';
    return DateFormat('dd/MM').format(date);
  }
}

/// Cloche de l'en-tête, avec le nombre de notifications non lues.
class NotificationBell extends ConsumerWidget {
  const NotificationBell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Cached<NotificationList>> notifications = ref.watch(notificationsProvider);
    final unread = notifications.value?.data.unreadCount ?? 0;

    return IconButton(
      tooltip: 'Notifications',
      onPressed: () => context.push('/notifications'),
      icon: Badge(
        isLabelVisible: unread > 0,
        backgroundColor: TalendaColors.orange,
        label: Text('$unread'),
        child: const Icon(Icons.notifications_outlined, color: TalendaColors.navy),
      ),
    );
  }
}
