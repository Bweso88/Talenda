import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import 'api/api_client.dart';
import 'api/api_exception.dart';
import 'theme.dart';

/// Badge de statut (« À jour », « En retard », « À examiner »…).
class StatusBadge extends StatelessWidget {
  const StatusBadge(this.label, {super.key, this.color = TalendaColors.blue, this.background = TalendaColors.field, this.icon});

  const StatusBadge.success(this.label, {super.key, this.icon = Icons.check_circle_outline})
    : color = TalendaColors.success,
      background = TalendaColors.successSoft;

  const StatusBadge.danger(this.label, {super.key, this.icon = Icons.error_outline}) : color = TalendaColors.danger, background = TalendaColors.dangerSoft;

  const StatusBadge.warning(this.label, {super.key, this.icon = Icons.schedule}) : color = TalendaColors.warning, background = TalendaColors.warningSoft;

  final String label;
  final Color color;
  final Color background;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(20)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 15, color: color), const SizedBox(width: 4)],
          Flexible(
            child: Text(
              label,
              style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 12.5),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

/// Bandeau « hors ligne » quand les données viennent du cache.
class OfflineBanner extends StatelessWidget {
  const OfflineBanner(this.cachedAt, {super.key});

  final DateTime? cachedAt;

  @override
  Widget build(BuildContext context) {
    if (cachedAt == null) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: TalendaColors.warningSoft, borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          const Icon(Icons.wifi_off, size: 18, color: TalendaColors.warning),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Hors ligne — données du ${DateFormat("dd/MM 'à' HH'h'mm").format(cachedAt!)}',
              style: const TextStyle(color: TalendaColors.warning, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, {super.key, this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 20, 4, 10),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title.toUpperCase(),
              style: const TextStyle(color: TalendaColors.muted, fontWeight: FontWeight.w700, fontSize: 12.5, letterSpacing: 0.6),
            ),
          ),
          ?trailing,
        ],
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 24),
      child: Column(
        children: [
          Icon(icon, size: 44, color: TalendaColors.muted.withValues(alpha: 0.5)),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: TalendaColors.muted),
          ),
        ],
      ),
    );
  }
}

/// Affiche une donnée chargée depuis l'API : chargement, erreur avec
/// « Réessayer », bandeau hors ligne, puis le contenu.
class CachedAsync<T> extends StatelessWidget {
  const CachedAsync({super.key, required this.value, required this.onRetry, required this.builder});

  final AsyncValue<Cached<T>> value;
  final VoidCallback onRetry;
  final Widget Function(T data, DateTime? cachedAt) builder;

  @override
  Widget build(BuildContext context) {
    return switch (value) {
      AsyncData(:final value) => builder(value.data, value.cachedAt),
      AsyncError(:final error) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off, size: 44, color: TalendaColors.muted),
              const SizedBox(height: 12),
              Text(ApiException.from(error).message, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Réessayer')),
            ],
          ),
        ),
      ),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}
