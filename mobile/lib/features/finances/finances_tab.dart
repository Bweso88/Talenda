import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/format.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../children/models.dart';
import '../children/parent_repository.dart';
import '../pdf/pdf_screen.dart';

/// Scolarité : situation de l'année, échéancier, paiements et reçus.
class FinancesTab extends ConsumerWidget {
  const FinancesTab({super.key, required this.child});

  final String child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () => ref.refresh(financesProvider(child).future),
      child: CachedAsync<List<Invoice>>(
        value: ref.watch(financesProvider(child)),
        onRetry: () => ref.invalidate(financesProvider(child)),
        builder: (invoices, cachedAt) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            OfflineBanner(cachedAt),
            if (invoices.isEmpty) const EmptyState(icon: Icons.receipt_long, message: 'Aucune facture pour le moment.'),
            for (final invoice in invoices) ..._invoice(context, ref, invoice),
          ],
        ),
      ),
    );
  }

  List<Widget> _invoice(BuildContext context, WidgetRef ref, Invoice invoice) {
    final progress = invoice.total == 0 ? 1.0 : (invoice.paid / invoice.total).clamp(0.0, 1.0);

    return [
      SectionTitle('Année ${invoice.schoolYear} • ${invoice.className}'),
      Card(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'RESTE À PAYER',
                style: TextStyle(color: TalendaColors.muted, fontWeight: FontWeight.w700, fontSize: 12.5),
              ),
              const SizedBox(height: 4),
              Text(
                Format.money(invoice.balance),
                style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: invoice.overdue > 0 ? TalendaColors.danger : TalendaColors.navy),
              ),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(value: progress, minHeight: 10, color: TalendaColors.teal, backgroundColor: TalendaColors.field),
              ),
              const SizedBox(height: 8),
              Text(
                'Payé : ${Format.money(invoice.paid)} sur ${Format.money(invoice.total)}',
                style: const TextStyle(color: TalendaColors.muted, fontWeight: FontWeight.w600),
              ),
              if (invoice.overdue > 0) ...[const SizedBox(height: 12), StatusBadge.danger('En retard : ${Format.money(invoice.overdue)}')],
              if (invoice.paymentPlan != null) ...[
                const SizedBox(height: 8),
                Text(
                  'Paiement ${invoice.paymentPlan!.toLowerCase()} • Facture ${invoice.number}',
                  style: const TextStyle(color: TalendaColors.muted, fontSize: 13),
                ),
              ],
            ],
          ),
        ),
      ),
      const SectionTitle('Échéancier'),
      Card(
        child: Column(
          children: [
            for (final (index, installment) in invoice.installments.indexed) ...[
              if (index > 0) const Divider(height: 1),
              ListTile(
                title: Text(
                  '${installment.label} • ${Format.money(installment.amount)}',
                  style: const TextStyle(fontWeight: FontWeight.w700, color: TalendaColors.navy),
                ),
                subtitle: Text('Avant le ${Format.date(installment.dueOn)}'),
                trailing: installment.isSettled
                    ? const StatusBadge.success('Payé')
                    : installment.isOverdue
                    ? StatusBadge.danger(Format.money(installment.remaining))
                    : StatusBadge(installment.paid > 0 ? 'Reste ${Format.money(installment.remaining)}' : 'À venir', icon: Icons.schedule),
              ),
            ],
          ],
        ),
      ),
      const SectionTitle('Paiements'),
      if (invoice.payments.isEmpty)
        const Card(
          child: EmptyState(icon: Icons.payments_outlined, message: 'Aucun paiement enregistré.'),
        )
      else
        Card(
          child: Column(
            children: [
              for (final (index, payment) in invoice.payments.indexed) ...[
                if (index > 0) const Divider(height: 1),
                ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: TalendaColors.field,
                    child: Icon(Icons.payments_outlined, color: TalendaColors.blue),
                  ),
                  title: Text(
                    '+${Format.money(payment.amount)}',
                    style: const TextStyle(fontWeight: FontWeight.w800, color: TalendaColors.success),
                  ),
                  subtitle: Text('${Format.date(payment.paidOn)} • ${payment.methodLabel}\nReçu ${payment.receiptNumber}'),
                  isThreeLine: true,
                  trailing: payment.hasReceipt
                      ? IconButton(
                          tooltip: 'Voir le reçu',
                          icon: const Icon(Icons.receipt_long, color: TalendaColors.blue),
                          onPressed: () => context.push(
                            '/document',
                            extra: PdfRequest(
                              title: 'Reçu ${payment.receiptNumber}',
                              fileName: 'recu-${payment.receiptNumber}.pdf',
                              load: () => ref.read(parentRepositoryProvider).receipt(child, payment.uuid),
                            ),
                          ),
                        )
                      : const StatusBadge.danger('Annulé', icon: null),
                ),
              ],
            ],
          ),
        ),
      const SizedBox(height: 8),
    ];
  }
}
