import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/format.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../children/models.dart';
import '../children/parent_repository.dart';

/// Bulletins publiés par l'école, du plus récent au plus ancien.
class ResultsTab extends ConsumerWidget {
  const ResultsTab({super.key, required this.child});

  final String child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () => ref.refresh(resultsProvider(child).future),
      child: CachedAsync<List<ResultSummary>>(
        value: ref.watch(resultsProvider(child)),
        onRetry: () => ref.invalidate(resultsProvider(child)),
        builder: (results, cachedAt) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            OfflineBanner(cachedAt),
            if (results.isEmpty)
              const EmptyState(
                icon: Icons.school_outlined,
                message: 'Aucun bulletin publié pour l\'instant. Les notes apparaissent ici dès que l\'école publie les bulletins du trimestre.',
              ),
            for (final result in results) ...[
              Card(
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => context.push('/enfants/$child/bulletins/${result.uuid}'),
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${result.term} • ${result.schoolYear}',
                                style: const TextStyle(color: TalendaColors.muted, fontWeight: FontWeight.w600),
                              ),
                              const SizedBox(height: 6),
                              Text.rich(
                                TextSpan(
                                  children: [
                                    TextSpan(
                                      text: Format.average(result.average),
                                      style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w800, color: TalendaColors.navy),
                                    ),
                                    const TextSpan(
                                      text: ' / 20',
                                      style: TextStyle(color: TalendaColors.muted, fontWeight: FontWeight.w600),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  if (result.rankLabel != null) StatusBadge('Rang ${result.rankLabel}', icon: Icons.leaderboard_outlined),
                                  if (result.distinction != null) StatusBadge.success(result.distinction!, icon: Icons.emoji_events_outlined),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right, color: TalendaColors.muted),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}
