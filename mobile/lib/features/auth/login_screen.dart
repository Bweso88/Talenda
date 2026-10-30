import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme.dart';
import 'auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _login = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await ref.read(authControllerProvider.notifier).login(_login.text, _password.text);
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Column(
                children: [
                  Image.asset('assets/images/talenda-logo.png', height: 150, semanticLabel: 'Talenda'),
                  const SizedBox(height: 8),
                  const Text(
                    'Espace parents',
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: TalendaColors.navy),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Scolarité, bulletins et absences de vos enfants.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: TalendaColors.muted),
                  ),
                  const SizedBox(height: 24),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Form(
                        key: _form,
                        child: AutofillGroup(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              const _Label('Téléphone ou e-mail'),
                              TextFormField(
                                key: const Key('login'),
                                controller: _login,
                                keyboardType: TextInputType.phone,
                                autofillHints: const [AutofillHints.telephoneNumber, AutofillHints.email],
                                textInputAction: TextInputAction.next,
                                decoration: const InputDecoration(hintText: '06 123 45 67', prefixIcon: Icon(Icons.phone_iphone)),
                                validator: (value) => (value ?? '').trim().isEmpty ? 'Indiquez votre téléphone.' : null,
                              ),
                              const SizedBox(height: 16),
                              const _Label('Mot de passe'),
                              TextFormField(
                                key: const Key('password'),
                                controller: _password,
                                obscureText: _obscure,
                                autofillHints: const [AutofillHints.password],
                                onFieldSubmitted: (_) => _submit(),
                                decoration: InputDecoration(
                                  hintText: '••••••••••',
                                  prefixIcon: const Icon(Icons.lock_outline),
                                  suffixIcon: IconButton(
                                    tooltip: _obscure ? 'Afficher' : 'Masquer',
                                    icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                    onPressed: () => setState(() => _obscure = !_obscure),
                                  ),
                                ),
                                validator: (value) => (value ?? '').isEmpty ? 'Indiquez votre mot de passe.' : null,
                              ),
                              if (_error != null) ...[
                                const SizedBox(height: 16),
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(color: TalendaColors.dangerSoft, borderRadius: BorderRadius.circular(12)),
                                  child: Text(
                                    _error!,
                                    style: const TextStyle(color: TalendaColors.danger, fontWeight: FontWeight.w600),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 24),
                              FilledButton(
                                onPressed: _loading ? null : _submit,
                                child: _loading
                                    ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                                    : const Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [Text('Se connecter'), SizedBox(width: 8), Icon(Icons.arrow_forward, size: 20)],
                                      ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(color: TalendaColors.field, borderRadius: BorderRadius.circular(16)),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.info_outline, color: TalendaColors.blue),
                        SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Votre accès est ouvert par l\'école de votre enfant, qui vous remet un mot de passe provisoire. '
                            'Mot de passe oublié : adressez-vous au secrétariat de l\'école.',
                            style: TextStyle(color: TalendaColors.navy, height: 1.4),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Label extends StatelessWidget {
  const _Label(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      text,
      style: const TextStyle(fontWeight: FontWeight.w700, color: TalendaColors.navy),
    ),
  );
}
