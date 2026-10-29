import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme.dart';
import '../children/parent_repository.dart';

/// Motifs acceptés par l'API (JustificationReason).
const justificationReasons = {'illness': 'Maladie', 'family': 'Raison familiale', 'transport': 'Problème de transport', 'other': 'Autre motif'};

Future<void> showJustificationSheet(BuildContext context, {required String child}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: Colors.white,
    builder: (_) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: JustificationForm(child: child),
    ),
  );
}

/// Le parent déclare une absence ; la vie scolaire de l'école l'examine.
class JustificationForm extends ConsumerStatefulWidget {
  const JustificationForm({super.key, required this.child});

  final String child;

  @override
  ConsumerState<JustificationForm> createState() => _JustificationFormState();
}

class _JustificationFormState extends ConsumerState<JustificationForm> {
  DateTime _startsOn = DateUtils.dateOnly(DateTime.now());
  DateTime _endsOn = DateUtils.dateOnly(DateTime.now());
  String _reason = 'illness';
  final _comment = TextEditingController();
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _pick({required bool start}) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: start ? _startsOn : _endsOn,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 60)),
      locale: const Locale('fr'),
    );
    if (picked == null) return;
    setState(() {
      if (start) {
        _startsOn = picked;
        if (_endsOn.isBefore(picked)) _endsOn = picked;
      } else {
        _endsOn = picked;
      }
    });
  }

  Future<void> _send() async {
    setState(() {
      _sending = true;
      _error = null;
    });

    try {
      await ref.read(parentRepositoryProvider).submitJustification(widget.child, startsOn: _startsOn, endsOn: _endsOn, reason: _reason, comment: _comment.text);
      ref.invalidate(attendanceProvider(widget.child));
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Justificatif envoyé. La vie scolaire de l\'école va l\'examiner.')));
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final format = DateFormat('d MMM yyyy', 'fr_FR');

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Justifier une absence',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: TalendaColors.navy),
          ),
          const SizedBox(height: 4),
          const Text('Vous pouvez prévenir à l\'avance ou après l\'absence.', style: TextStyle(color: TalendaColors.muted)),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: _DateField(label: 'Du', value: format.format(_startsOn), onTap: () => _pick(start: true)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _DateField(label: 'Au', value: format.format(_endsOn), onTap: () => _pick(start: false)),
              ),
            ],
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _reason,
            decoration: const InputDecoration(labelText: 'Motif'),
            items: [for (final entry in justificationReasons.entries) DropdownMenuItem(value: entry.key, child: Text(entry.value))],
            onChanged: (value) => setState(() => _reason = value ?? _reason),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _comment,
            maxLength: 500,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Précisions (facultatif)', hintText: 'Ex. fièvre, rendez-vous à l\'hôpital…'),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(_error!, style: const TextStyle(color: TalendaColors.danger)),
            ),
          FilledButton(
            onPressed: _sending ? null : _send,
            child: _sending ? const CircularProgressIndicator(color: Colors.white) : const Text('Envoyer à l\'école'),
          ),
        ],
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({required this.label, required this.value, required this.onTap});

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: InputDecorator(
        decoration: InputDecoration(labelText: label, suffixIcon: const Icon(Icons.calendar_today, size: 18)),
        child: Text(value),
      ),
    );
  }
}
