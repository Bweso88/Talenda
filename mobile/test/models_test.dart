import 'package:flutter_test/flutter_test.dart';
import 'package:talenda_parents/features/auth/models.dart';
import 'package:talenda_parents/features/children/models.dart';

import 'helpers.dart';

void main() {
  test('lit le profil et ne garde que les écoles où le compte est parent', () {
    final me = fixture('me');
    final schools = [for (final m in me['memberships'] as List) School.fromMembershipJson(m as Map<String, dynamic>)];

    expect(AppUser.fromJson(me['user'] as Map<String, dynamic>).name, isNotEmpty);
    expect(schools.where((school) => school.isParent), hasLength(2));
  });

  test('lit la liste des enfants avec leur résumé', () {
    final children = [for (final item in fixture('children')['data'] as List) Child.fromJson(item as Map<String, dynamic>)];
    final arsene = children.firstWhere((child) => child.firstNames == 'Arsène');

    expect(children, hasLength(2));
    expect(arsene.className, '2nde C');
    expect(arsene.initials, 'AM');
    expect(arsene.finance!.balance, greaterThan(0));
    expect(arsene.lastResult!.term, 'Premier trimestre');
    expect(arsene.attendance.lateCount, 2);
  });

  test('lit la scolarité : échéancier et paiements', () {
    final invoice = Invoice.fromJson((fixture('finances')['data'] as List).first as Map<String, dynamic>);

    expect(invoice.installments, isNotEmpty);
    expect(invoice.payments.first.hasReceipt, isTrue);
    expect(invoice.total, invoice.paid + invoice.balance);
  });

  test('lit un bulletin publié et la vie scolaire', () {
    final detail = TermResultDetail.fromJson(fixture('result')['data'] as Map<String, dynamic>);
    final life = SchoolLife.fromJson(fixture('attendance')['data'] as Map<String, dynamic>);

    expect(detail.subjects.first.subject, 'Mathématiques');
    expect(detail.summary.average, 11.68);
    expect(detail.councilComment, contains('physique-chimie'));
    expect(life.records.where((record) => record.isAbsence), isNotEmpty);
    expect(life.justifications.first.status, 'pending');
  });
}
