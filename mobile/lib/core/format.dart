import 'package:intl/intl.dart';

/// Mises en forme françaises (Congo) : montants en francs CFA, dates
/// JJ/MM/AAAA, heures d'absence.
abstract final class Format {
  static final _money = NumberFormat('#,##0', 'fr_FR');

  /// 205000 → « 205 000 FCFA ».
  static String money(int amount) => '${_money.format(amount).replaceAll(' ', ' ').replaceAll(' ', ' ')} FCFA';

  /// « 2026-11-16 » → « 16/11/2026 ».
  static String date(String isoDate) => DateFormat('dd/MM/yyyy').format(DateTime.parse(isoDate));

  /// « 2026-11-16 » → « lundi 16 novembre ».
  static String longDate(String isoDate) => DateFormat('EEEE d MMMM', 'fr_FR').format(DateTime.parse(isoDate));

  /// 12.5 → « 12,50 ».
  static String average(double? value) => value == null ? '—' : value.toStringAsFixed(2).replaceAll('.', ',');

  /// 450 → « 7 h 30 ».
  static String hours(int minutes) {
    final rest = minutes % 60;
    return '${minutes ~/ 60} h${rest == 0 ? '' : ' ${rest.toString().padLeft(2, '0')}'}';
  }
}
