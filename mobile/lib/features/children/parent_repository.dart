import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/providers.dart';
import 'models.dart';

/// Accès aux données des enfants (API parents, école choisie).
class ParentRepository {
  ParentRepository(this._api);

  final ApiClient _api;

  Future<Cached<List<Child>>> children() async {
    final response = await _api.getCached('/children');
    return Cached([for (final item in response.data['data'] as List) Child.fromJson(item as Map<String, dynamic>)], cachedAt: response.cachedAt);
  }

  Future<Cached<Child>> child(String uuid) async {
    final response = await _api.getCached('/children/$uuid');
    return Cached(Child.fromJson(response.data['data'] as Map<String, dynamic>), cachedAt: response.cachedAt);
  }

  Future<Cached<List<Invoice>>> finances(String child) async {
    final response = await _api.getCached('/children/$child/finances');
    return Cached([for (final item in response.data['data'] as List) Invoice.fromJson(item as Map<String, dynamic>)], cachedAt: response.cachedAt);
  }

  Future<Cached<List<ResultSummary>>> results(String child) async {
    final response = await _api.getCached('/children/$child/results');
    return Cached([for (final item in response.data['data'] as List) ResultSummary.fromJson(item as Map<String, dynamic>)], cachedAt: response.cachedAt);
  }

  Future<Cached<TermResultDetail>> result(String child, String result) async {
    final response = await _api.getCached('/children/$child/results/$result');
    return Cached(TermResultDetail.fromJson(response.data['data'] as Map<String, dynamic>), cachedAt: response.cachedAt);
  }

  Future<Cached<SchoolLife?>> attendance(String child) async {
    final response = await _api.getCached('/children/$child/attendance');
    final data = response.data['data'] as Map<String, dynamic>?;
    return Cached(data == null ? null : SchoolLife.fromJson(data), cachedAt: response.cachedAt);
  }

  Future<void> submitJustification(String child, {required DateTime startsOn, required DateTime endsOn, required String reason, String? comment}) => _api.post(
    '/children/$child/absence-justifications',
    {'starts_on': _date(startsOn), 'ends_on': _date(endsOn), 'reason': reason, if (comment != null && comment.trim().isNotEmpty) 'comment': comment.trim()},
  );

  Future<Uint8List> receipt(String child, String payment) => _api.pdf('/children/$child/payments/$payment/receipt');

  Future<Uint8List> reportCard(String child, String result) => _api.pdf('/children/$child/results/$result/report-card');

  static String _date(DateTime date) => '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
}

final parentRepositoryProvider = Provider<ParentRepository>((ref) => ParentRepository(ref.watch(apiClientProvider)));

final childrenProvider = FutureProvider<Cached<List<Child>>>((ref) => ref.watch(parentRepositoryProvider).children());

final childProvider = FutureProvider.family<Cached<Child>, String>((ref, uuid) => ref.watch(parentRepositoryProvider).child(uuid));

final financesProvider = FutureProvider.family<Cached<List<Invoice>>, String>((ref, child) => ref.watch(parentRepositoryProvider).finances(child));

final resultsProvider = FutureProvider.family<Cached<List<ResultSummary>>, String>((ref, child) => ref.watch(parentRepositoryProvider).results(child));

final resultDetailProvider = FutureProvider.family<Cached<TermResultDetail>, ({String child, String result})>(
  (ref, key) => ref.watch(parentRepositoryProvider).result(key.child, key.result),
);

final attendanceProvider = FutureProvider.family<Cached<SchoolLife?>, String>((ref, child) => ref.watch(parentRepositoryProvider).attendance(child));
