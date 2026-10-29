import 'package:flutter/material.dart';

/// Couleurs du logo Talenda.
abstract final class TalendaColors {
  static const navy = Color(0xFF0F2C5C);
  static const blue = Color(0xFF1E5AA8);
  static const teal = Color(0xFF1A9AA0);
  static const orange = Color(0xFFF0A030);
  static const background = Color(0xFFF4F7FB);
  static const field = Color(0xFFEEF3FA);
  static const muted = Color(0xFF5B6B82);
  static const success = Color(0xFF1B8A5A);
  static const successSoft = Color(0xFFDDF4E9);
  static const danger = Color(0xFFC0392B);
  static const dangerSoft = Color(0xFFFBE3E0);
  static const warning = Color(0xFFB86E00);
  static const warningSoft = Color(0xFFFFF0D6);
}

ThemeData talendaTheme() {
  final scheme = ColorScheme.fromSeed(seedColor: TalendaColors.blue, primary: TalendaColors.blue, secondary: TalendaColors.orange, surface: Colors.white);
  final rounded = RoundedRectangleBorder(borderRadius: BorderRadius.circular(12));

  return ThemeData(
    colorScheme: scheme,
    scaffoldBackgroundColor: TalendaColors.background,
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: TalendaColors.navy,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 1,
      titleTextStyle: TextStyle(color: TalendaColors.navy, fontSize: 18, fontWeight: FontWeight.w700),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFFE3EAF3)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: TalendaColors.field,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: TalendaColors.navy,
        foregroundColor: Colors.white,
        minimumSize: const Size.fromHeight(52),
        shape: rounded,
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(foregroundColor: TalendaColors.blue, shape: rounded),
    ),
    tabBarTheme: const TabBarThemeData(labelColor: TalendaColors.navy, unselectedLabelColor: TalendaColors.muted, indicatorColor: TalendaColors.blue),
    useMaterial3: true,
  );
}
