import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:talenda_parents/core/format.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  test('affiche les montants en francs CFA avec séparateur de milliers', () {
    expect(Format.money(205000), '205 000 FCFA');
    expect(Format.money(0), '0 FCFA');
  });

  test('affiche les dates, moyennes et heures à la française', () {
    expect(Format.date('2026-11-16'), '16/11/2026');
    expect(Format.longDate('2026-11-16'), 'lundi 16 novembre');
    expect(Format.average(11.6), '11,60');
    expect(Format.average(null), '—');
    expect(Format.hours(450), '7 h 30');
    expect(Format.hours(300), '5 h');
  });
}
