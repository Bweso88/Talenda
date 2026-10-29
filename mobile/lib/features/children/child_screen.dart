import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../attendance/attendance_tab.dart';
import '../finances/finances_tab.dart';
import '../results/results_tab.dart';
import 'parent_repository.dart';

/// Fiche d'un enfant : scolarité, notes, absences.
class ChildScreen extends ConsumerWidget {
  const ChildScreen({super.key, required this.uuid, this.initialTab = 0});

  final String uuid;
  final int initialTab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final child = ref.watch(childProvider(uuid)).value?.data;

    return DefaultTabController(
      length: 3,
      initialIndex: initialTab,
      child: Scaffold(
        appBar: AppBar(
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(child?.fullName ?? ''),
              if (child?.className != null)
                Text('${child!.className} • ${child.schoolYear}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
            ],
          ),
          bottom: const TabBar(
            tabs: [
              Tab(icon: Icon(Icons.payments_outlined), text: 'Scolarité'),
              Tab(icon: Icon(Icons.school_outlined), text: 'Notes'),
              Tab(icon: Icon(Icons.event_note_outlined), text: 'Absences'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            FinancesTab(child: uuid),
            ResultsTab(child: uuid),
            AttendanceTab(child: uuid),
          ],
        ),
      ),
    );
  }
}
