import 'dart:convert';
import 'dart:io';

/// Réponses réelles de l'API (backend de démonstration), capturées dans
/// test/fixtures : si le contrat change, ces tests le signalent.
Map<String, dynamic> fixture(String name) => jsonDecode(File('test/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;
