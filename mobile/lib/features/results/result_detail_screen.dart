import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/format.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../children/models.dart';
import '../children/parent_repository.dart';
import '../pdf/pdf_screen.dart';

/// Détail d'un bulletin publié : moyennes par matière, appréciations,
/// absences, et le bulletin PDF de l'école.
class ResultDetailScreen extends ConsumerWidget {
  const ResultDetailScreen({super.key, required this.child, required this.result});

  final String child;
  final String result;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final key = (child: child, result: result);

    return Scaffold(
      appBar: AppBar(title: const Text('Bulletin')),
      body: CachedAsync<TermResultDetail>(
        value: ref.watch(resultDetailProvider(key)),
        onRetry: () => ref.invalidate(resultDetailProvider(key)),
        builder: (detail, cachedAt) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            OfflineBanner(cachedAt),
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(color: TalendaColors.navy, borderRadius: BorderRadius.circular(20)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${detail.summary.term} • ${detail.className}',
                    style: const TextStyle(color: Color(0xFFB8CBE6), fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${Format.average(detail.summary.average)} / 20',
                    style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    [
                      if (detail.summary.rankLabel != null) 'Rang ${detail.summary.rankLabel}',
                      'Moyenne de la classe : ${Format.average(detail.classAverage)}',
                    ].join(' • '),
                    style: const TextStyle(color: Colors.white),
                  ),
                  if (detail.summary.distinction != null) ...[
                    const SizedBox(height: 10),
                    StatusBadge(detail.summary.distinction!, color: TalendaColors.navy, background: TalendaColors.orange, icon: Icons.emoji_events),
                  ],
                ],
              ),
            ),
            const SectionTitle('Matières'),
            Card(
              child: Column(
                children: [
                  for (final (index, subject) in detail.subjects.indexed) ...[
                    if (index > 0) const Divider(height: 1),
                    ListTile(
                      title: Text(
                        subject.subject,
                        style: const TextStyle(fontWeight: FontWeight.w700, color: TalendaColors.navy),
                      ),
                      subtitle: Text(
                        [
                          'Coef. ${subject.coefficient.toStringAsFixed(subject.coefficient % 1 == 0 ? 0 : 1)}',
                          if (subject.classAverage != null) 'classe ${Format.average(subject.classAverage)}',
                          ?subject.comment,
                        ].join(' • '),
                      ),
                      trailing: Text(
                        Format.average(subject.average),
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: (subject.average ?? 10) < 10 ? TalendaColors.danger : TalendaColors.navy,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SectionTitle('Conseil de classe'),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(detail.councilComment ?? 'Pas d\'appréciation.', style: const TextStyle(fontStyle: FontStyle.italic)),
                    const SizedBox(height: 10),
                    Text('Absences : ${detail.attendance.label}', style: const TextStyle(color: TalendaColors.muted)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              icon: const Icon(Icons.picture_as_pdf_outlined),
              label: const Text('Voir le bulletin (PDF)'),
              onPressed: () => context.push(
                '/document',
                extra: PdfRequest(
                  title: 'Bulletin — ${detail.summary.term}',
                  fileName: 'bulletin.pdf',
                  load: () => ref.read(parentRepositoryProvider).reportCard(child, result),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
