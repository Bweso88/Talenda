import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme.dart';
import 'auth_controller.dart';

/// Obligatoire à la première connexion (mot de passe provisoire remis par
/// l'école), possible ensuite depuis le menu.
class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _loading = false;
  Map<String, String> _errors = const {};

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _errors = const {};
    });

    try {
      await ref.read(authControllerProvider.notifier).changePassword(_current.text, _password.text, _confirmation.text);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Mot de passe changé.')));
      }
    } on ApiException catch (error) {
      setState(() => _errors = error.fieldErrors.isEmpty ? {'password': error.message} : error.fieldErrors);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final mustChange = ref.watch(authControllerProvider).value?.user.mustChangePassword ?? false;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mot de passe'),
        automaticallyImplyLeading: !mustChange,
        actions: [if (mustChange) TextButton(onPressed: () => ref.read(authControllerProvider.notifier).logout(), child: const Text('Se déconnecter'))],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (mustChange)
                Container(
                  padding: const EdgeInsets.all(14),
                  margin: const EdgeInsets.only(bottom: 20),
                  decoration: BoxDecoration(color: TalendaColors.field, borderRadius: BorderRadius.circular(12)),
                  child: const Text(
                    'Bienvenue ! Choisissez votre propre mot de passe pour remplacer celui remis par l\'école.',
                    style: TextStyle(color: TalendaColors.navy, height: 1.4),
                  ),
                ),
              TextFormField(
                controller: _current,
                obscureText: true,
                decoration: InputDecoration(labelText: 'Mot de passe actuel', errorText: _errors['current_password']),
                validator: (value) => (value ?? '').isEmpty ? 'Obligatoire.' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _password,
                obscureText: true,
                decoration: InputDecoration(labelText: 'Nouveau mot de passe', helperText: 'Au moins 10 caractères.', errorText: _errors['password']),
                validator: (value) => (value ?? '').length < 10 ? 'Au moins 10 caractères.' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _confirmation,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Confirmez le nouveau mot de passe'),
                validator: (value) => value != _password.text ? 'Les deux mots de passe sont différents.' : null,
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _loading ? null : _submit,
                child: _loading ? const CircularProgressIndicator(color: Colors.white) : const Text('Enregistrer'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
