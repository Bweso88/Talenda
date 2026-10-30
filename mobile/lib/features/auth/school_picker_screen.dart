import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'auth_controller.dart';

/// Un parent qui a des enfants dans plusieurs écoles choisit l'école à
/// consulter (un seul compte pour toutes).
class SchoolPickerScreen extends ConsumerWidget {
  const SchoolPickerScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider).value;
    final schools = auth?.schools ?? const [];

    return Scaffold(
      appBar: AppBar(
        title: const Text('Choisir l\'école'),
        actions: [IconButton(tooltip: 'Se déconnecter', onPressed: () => ref.read(authControllerProvider.notifier).logout(), icon: const Icon(Icons.logout))],
      ),
      body: schools.isEmpty
          ? const EmptyState(
              icon: Icons.school_outlined,
              message: 'Aucune école ne vous a encore ouvert l\'app parents. Adressez-vous au secrétariat de l\'école de votre enfant.',
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: schools.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final school = schools[index];
                final selected = auth?.school?.uuid == school.uuid;

                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    leading: const CircleAvatar(
                      backgroundColor: TalendaColors.field,
                      child: Icon(Icons.account_balance, color: TalendaColors.blue),
                    ),
                    title: Text(
                      school.name,
                      style: const TextStyle(fontWeight: FontWeight.w700, color: TalendaColors.navy),
                    ),
                    trailing: selected ? const Icon(Icons.check_circle, color: TalendaColors.success) : const Icon(Icons.chevron_right),
                    onTap: () async {
                      await ref.read(authControllerProvider.notifier).selectSchool(school);
                      if (context.mounted) context.go('/');
                    },
                  ),
                );
              },
            ),
    );
  }
}
