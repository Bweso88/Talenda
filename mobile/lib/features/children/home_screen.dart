import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/format.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../auth/auth_controller.dart';
import '../notifications/notifications.dart';
import '../notifications/notifications_screen.dart';
import 'models.dart';
import 'parent_repository.dart';

/// Accueil : un coup d'œil sur chaque enfant (reste à payer, dernière
/// moyenne, absences).
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider).value;
    final children = ref.watch(childrenProvider);

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: Image.asset('assets/images/talenda-logo-horizontal.png', height: 26, semanticLabel: 'Talenda'),
        actions: [
          const NotificationBell(),
          PopupMenuButton<String>(
            tooltip: 'Menu',
            icon: const Icon(Icons.account_circle_outlined, color: TalendaColors.navy),
            onSelected: (choice) => switch (choice) {
              'school' => context.push('/ecoles'),
              'password' => context.push('/mot-de-passe'),
              _ => ref.read(authControllerProvider.notifier).logout(),
            },
            itemBuilder: (_) => [
              if ((auth?.schools.length ?? 0) > 1) const PopupMenuItem(value: 'school', child: Text('Changer d\'école')),
              const PopupMenuItem(value: 'password', child: Text('Changer mon mot de passe')),
              const PopupMenuItem(value: 'logout', child: Text('Se déconnecter')),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () {
          ref.invalidate(notificationsProvider);
          return ref.refresh(childrenProvider.future);
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _Welcome(name: auth?.user.name ?? '', school: auth?.school?.name ?? ''),
            const SizedBox(height: 8),
            CachedAsync<List<Child>>(
              value: children,
              onRetry: () => ref.invalidate(childrenProvider),
              builder: (list, cachedAt) => Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  OfflineBanner(cachedAt),
                  SectionTitle(list.length > 1 ? 'Mes enfants (${list.length})' : 'Mon enfant'),
                  if (list.isEmpty) const EmptyState(icon: Icons.family_restroom, message: 'Aucun enfant n\'est encore relié à votre compte dans cette école.'),
                  for (final child in list) ...[ChildCard(child: child), const SizedBox(height: 12)],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Welcome extends StatelessWidget {
  const _Welcome({required this.name, required this.school});

  final String name;
  final String school;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [TalendaColors.navy, Color(0xFF1B3F78)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Bonjour, $name',
            style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              const Icon(Icons.account_balance, size: 16, color: Color(0xFFB8CBE6)),
              const SizedBox(width: 6),
              Expanded(
                child: Text(school, style: const TextStyle(color: Color(0xFFB8CBE6))),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class ChildCard extends StatelessWidget {
  const ChildCard({super.key, required this.child});

  final Child child;

  @override
  Widget build(BuildContext context) {
    final finance = child.finance;
    final result = child.lastResult;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/enfants/${child.uuid}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 26,
                    backgroundColor: TalendaColors.blue,
                    child: Text(
                      child.initials,
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          child.fullName,
                          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: TalendaColors.navy),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          [if (child.className != null) child.className!, 'Matricule ${child.registrationNumber}'].join(' • '),
                          style: const TextStyle(color: TalendaColors.muted),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: TalendaColors.muted),
                ],
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (finance != null)
                    finance.overdue > 0
                        ? StatusBadge.danger('En retard : ${Format.money(finance.overdue)}')
                        : finance.balance == 0
                        ? const StatusBadge.success('Scolarité soldée')
                        : const StatusBadge.success('À jour de scolarité'),
                  if (result != null)
                    StatusBadge('Moy. ${Format.average(result.average)} • ${result.term}', icon: Icons.school_outlined)
                  else
                    const StatusBadge('Pas encore de bulletin', icon: Icons.school_outlined),
                  child.attendance.absenceMinutes == 0 && child.attendance.lateCount == 0
                      ? const StatusBadge.success('Aucune absence', icon: Icons.event_available)
                      : child.attendance.unjustifiedMinutes > 0
                      ? StatusBadge.warning(child.attendance.label, icon: Icons.event_busy)
                      : StatusBadge(child.attendance.label, icon: Icons.event_note),
                ],
              ),
              if (finance != null && finance.balance > 0 && finance.nextDue != null) ...[
                const SizedBox(height: 12),
                Text(
                  'Prochaine échéance : ${Format.money(finance.nextDue!.remaining)} avant le ${Format.date(finance.nextDue!.dueOn)}',
                  style: const TextStyle(color: TalendaColors.muted, fontSize: 13),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
