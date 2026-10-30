import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/format.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../children/models.dart';
import '../children/parent_repository.dart';
import 'justification_sheet.dart';

/// Vie scolaire de l'année : absences, retards, justificatifs, discipline.
class AttendanceTab extends ConsumerWidget {
  const AttendanceTab({super.key, required this.child});

  final String child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () => ref.refresh(attendanceProvider(child).future),
      child: CachedAsync<SchoolLife?>(
        value: ref.watch(attendanceProvider(child)),
        onRetry: () => ref.invalidate(attendanceProvider(child)),
        builder: (life, cachedAt) {
          if (life == null) {
            return const EmptyState(icon: Icons.event_note, message: 'Cet enfant n\'est inscrit dans aucune classe.');
          }

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              OfflineBanner(cachedAt),
              Row(
                children: [
                  _Tile(label: 'Absences', value: Format.hours(life.totals.absenceMinutes), icon: Icons.event_busy),
                  const SizedBox(width: 10),
                  _Tile(
                    label: 'Non justifiées',
                    value: Format.hours(life.totals.unjustifiedMinutes),
                    icon: Icons.report_outlined,
                    alert: life.totals.unjustifiedMinutes > 0,
                  ),
                  const SizedBox(width: 10),
                  _Tile(label: 'Retards', value: '${life.totals.lateCount}', icon: Icons.schedule),
                ],
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                icon: const Icon(Icons.note_add_outlined),
                label: const Text('Justifier une absence'),
                onPressed: () => showJustificationSheet(context, child: child),
              ),
              SectionTitle('Absences et retards • ${life.schoolYear}'),
              if (life.records.isEmpty)
                const Card(
                  child: EmptyState(icon: Icons.event_available, message: 'Aucune absence ni retard cette année.'),
                )
              else
                Card(
                  child: Column(
                    children: [
                      for (final (index, record) in life.records.indexed) ...[
                        if (index > 0) const Divider(height: 1),
                        ListTile(
                          leading: CircleAvatar(
                            backgroundColor: record.isAbsence ? TalendaColors.dangerSoft : TalendaColors.warningSoft,
                            child: Icon(
                              record.isAbsence ? Icons.event_busy : Icons.schedule,
                              color: record.isAbsence ? TalendaColors.danger : TalendaColors.warning,
                            ),
                          ),
                          title: Text(
                            record.isAbsence ? 'Absent (${record.hours})' : 'Retard${record.lateMinutes == null ? '' : ' de ${record.lateMinutes} min'}',
                            style: const TextStyle(fontWeight: FontWeight.w700, color: TalendaColors.navy),
                          ),
                          subtitle: Text('${Format.longDate(record.date)} • ${record.slot}'),
                          trailing: record.isJustified == null
                              ? null
                              : record.isJustified!
                              ? const StatusBadge.success('Justifiée')
                              : const StatusBadge.danger('Non justifiée'),
                        ),
                      ],
                    ],
                  ),
                ),
              if (life.justifications.isNotEmpty) ...[
                const SectionTitle('Justificatifs'),
                Card(
                  child: Column(
                    children: [
                      for (final (index, justification) in life.justifications.indexed) ...[
                        if (index > 0) const Divider(height: 1),
                        ListTile(
                          title: Text(
                            justification.reasonLabel,
                            style: const TextStyle(fontWeight: FontWeight.w700, color: TalendaColors.navy),
                          ),
                          subtitle: Text(
                            justification.startsOn == justification.endsOn
                                ? 'Le ${Format.date(justification.startsOn)}'
                                : 'Du ${Format.date(justification.startsOn)} au ${Format.date(justification.endsOn)}',
                          ),
                          trailing: switch (justification.status) {
                            'accepted' => StatusBadge.success(justification.statusLabel),
                            'rejected' => StatusBadge.danger(justification.statusLabel),
                            _ => StatusBadge.warning(justification.statusLabel),
                          },
                        ),
                      ],
                    ],
                  ),
                ),
              ],
              if (life.incidents.isNotEmpty) ...[
                const SectionTitle('Discipline'),
                Card(
                  child: Column(
                    children: [
                      for (final (index, incident) in life.incidents.indexed) ...[
                        if (index > 0) const Divider(height: 1),
                        ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: TalendaColors.warningSoft,
                            child: Icon(Icons.gavel, color: TalendaColors.warning),
                          ),
                          title: Text(
                            incident.sanctionLabel + (incident.suspensionDays == null ? '' : ' (${incident.suspensionDays} j)'),
                            style: const TextStyle(fontWeight: FontWeight.w700, color: TalendaColors.navy),
                          ),
                          subtitle: Text('${Format.date(incident.date)} • ${incident.description}'),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.label, required this.value, required this.icon, this.alert = false});

  final String label;
  final String value;
  final IconData icon;
  final bool alert;

  @override
  Widget build(BuildContext context) {
    final color = alert ? TalendaColors.danger : TalendaColors.navy;

    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: 20, color: alert ? TalendaColors.danger : TalendaColors.blue),
              const SizedBox(height: 8),
              Text(
                value,
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: color),
              ),
              Text(label, style: const TextStyle(color: TalendaColors.muted, fontSize: 12.5)),
            ],
          ),
        ),
      ),
    );
  }
}
