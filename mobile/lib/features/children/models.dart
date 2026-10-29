/// Modèles de l'API parents (docs/openapi.json). Montants en francs CFA.
library;

double? _number(Object? value) => value == null ? null : (value as num).toDouble();

class AttendanceTotal {
  const AttendanceTotal({required this.absenceMinutes, required this.unjustifiedMinutes, required this.lateCount, required this.label});

  final int absenceMinutes;
  final int unjustifiedMinutes;
  final int lateCount;
  final String label;

  factory AttendanceTotal.fromJson(Map<String, dynamic> json) => AttendanceTotal(
    absenceMinutes: json['absence_minutes'] as int,
    unjustifiedMinutes: json['unjustified_absence_minutes'] as int,
    lateCount: json['late_count'] as int,
    label: json['label'] as String,
  );
}

class ResultSummary {
  const ResultSummary({
    required this.uuid,
    required this.term,
    required this.schoolYear,
    required this.classSize,
    this.average,
    this.rankLabel,
    this.distinction,
  });

  final String uuid;
  final String term;
  final String schoolYear;
  final double? average;
  final String? rankLabel;
  final int classSize;
  final String? distinction;

  factory ResultSummary.fromJson(Map<String, dynamic> json) => ResultSummary(
    uuid: json['uuid'] as String,
    term: json['term'] as String,
    schoolYear: json['school_year'] as String,
    average: _number(json['average']),
    rankLabel: json['rank_label'] as String?,
    classSize: json['class_size'] as int,
    distinction: json['distinction'] as String?,
  );
}

class NextDue {
  const NextDue({required this.label, required this.dueOn, required this.remaining});

  final String label;
  final String dueOn;
  final int remaining;
}

class ChildFinance {
  const ChildFinance({required this.total, required this.paid, required this.balance, required this.overdue, this.nextDue});

  final int total;
  final int paid;
  final int balance;
  final int overdue;
  final NextDue? nextDue;

  factory ChildFinance.fromJson(Map<String, dynamic> json) {
    final next = json['next_due'] as Map<String, dynamic>?;
    return ChildFinance(
      total: json['total'] as int,
      paid: json['paid'] as int,
      balance: json['balance'] as int,
      overdue: json['overdue'] as int,
      nextDue: next == null ? null : NextDue(label: next['label'] as String, dueOn: next['due_on'] as String, remaining: next['remaining'] as int),
    );
  }
}

class Child {
  const Child({
    required this.uuid,
    required this.lastName,
    required this.firstNames,
    required this.fullName,
    required this.registrationNumber,
    required this.attendance,
    this.className,
    this.schoolYear,
    this.finance,
    this.lastResult,
  });

  final String uuid;
  final String lastName;
  final String firstNames;
  final String fullName;
  final String registrationNumber;
  final String? className;
  final String? schoolYear;
  final ChildFinance? finance;
  final ResultSummary? lastResult;
  final AttendanceTotal attendance;

  String get initials => '${firstNames.isEmpty ? '' : firstNames[0]}${lastName.isEmpty ? '' : lastName[0]}'.toUpperCase();

  factory Child.fromJson(Map<String, dynamic> json) {
    final enrollment = json['enrollment'] as Map<String, dynamic>?;
    return Child(
      uuid: json['uuid'] as String,
      lastName: json['last_name'] as String,
      firstNames: json['first_names'] as String,
      fullName: json['full_name'] as String,
      registrationNumber: json['registration_number'] as String,
      className: enrollment?['class_name'] as String?,
      schoolYear: enrollment?['school_year'] as String?,
      finance: json['finance'] == null ? null : ChildFinance.fromJson(json['finance'] as Map<String, dynamic>),
      lastResult: json['last_result'] == null ? null : ResultSummary.fromJson(json['last_result'] as Map<String, dynamic>),
      attendance: AttendanceTotal.fromJson(json['attendance'] as Map<String, dynamic>),
    );
  }
}

class Installment {
  const Installment({
    required this.label,
    required this.dueOn,
    required this.amount,
    required this.paid,
    required this.remaining,
    required this.isOverdue,
    required this.statusLabel,
  });

  final String label;
  final String dueOn;
  final int amount;
  final int paid;
  final int remaining;
  final bool isOverdue;
  final String statusLabel;

  bool get isSettled => remaining <= 0;

  factory Installment.fromJson(Map<String, dynamic> json) => Installment(
    label: json['label'] as String,
    dueOn: json['due_on'] as String,
    amount: json['amount'] as int,
    paid: json['paid'] as int,
    remaining: json['remaining'] as int,
    isOverdue: json['is_overdue'] as bool,
    statusLabel: json['status_label'] as String,
  );
}

class Payment {
  const Payment({
    required this.uuid,
    required this.receiptNumber,
    required this.paidOn,
    required this.amount,
    required this.methodLabel,
    required this.hasReceipt,
  });

  final String uuid;
  final String receiptNumber;
  final String paidOn;
  final int amount;
  final String methodLabel;
  final bool hasReceipt;

  factory Payment.fromJson(Map<String, dynamic> json) => Payment(
    uuid: json['uuid'] as String,
    receiptNumber: json['receipt_number'] as String,
    paidOn: json['paid_on'] as String,
    amount: json['amount'] as int,
    methodLabel: json['method_label'] as String,
    hasReceipt: json['has_receipt'] as bool,
  );
}

class Invoice {
  const Invoice({
    required this.number,
    required this.schoolYear,
    required this.className,
    required this.total,
    required this.paid,
    required this.balance,
    required this.overdue,
    required this.installments,
    required this.payments,
    this.paymentPlan,
  });

  final String number;
  final String schoolYear;
  final String className;
  final String? paymentPlan;
  final int total;
  final int paid;
  final int balance;
  final int overdue;
  final List<Installment> installments;
  final List<Payment> payments;

  factory Invoice.fromJson(Map<String, dynamic> json) => Invoice(
    number: json['number'] as String,
    schoolYear: json['school_year'] as String,
    className: json['class_name'] as String,
    paymentPlan: json['payment_plan'] as String?,
    total: json['total'] as int,
    paid: json['paid'] as int,
    balance: json['balance'] as int,
    overdue: json['overdue'] as int,
    installments: [for (final item in json['installments'] as List) Installment.fromJson(item as Map<String, dynamic>)],
    payments: [for (final item in json['payments'] as List) Payment.fromJson(item as Map<String, dynamic>)],
  );
}

class SubjectResult {
  const SubjectResult({required this.subject, required this.coefficient, this.teacher, this.average, this.rank, this.classAverage, this.comment});

  final String subject;
  final String? teacher;
  final double coefficient;
  final double? average;
  final int? rank;
  final double? classAverage;
  final String? comment;

  factory SubjectResult.fromJson(Map<String, dynamic> json) => SubjectResult(
    subject: json['subject'] as String,
    teacher: json['teacher'] as String?,
    coefficient: _number(json['coefficient'])!,
    average: _number(json['average']),
    rank: json['rank'] as int?,
    classAverage: _number(json['class_average']),
    comment: json['comment'] as String?,
  );
}

class TermResultDetail {
  const TermResultDetail({
    required this.summary,
    required this.className,
    required this.attendance,
    required this.subjects,
    this.classAverage,
    this.councilComment,
  });

  final ResultSummary summary;
  final String className;
  final double? classAverage;
  final String? councilComment;
  final AttendanceTotal attendance;
  final List<SubjectResult> subjects;

  factory TermResultDetail.fromJson(Map<String, dynamic> json) => TermResultDetail(
    summary: ResultSummary.fromJson(json),
    className: json['class_name'] as String,
    classAverage: _number(json['class_average']),
    councilComment: json['council_comment'] as String?,
    attendance: AttendanceTotal.fromJson(json['attendance'] as Map<String, dynamic>),
    subjects: [for (final item in json['subjects'] as List? ?? const []) SubjectResult.fromJson(item as Map<String, dynamic>)],
  );
}

class AttendanceEntry {
  const AttendanceEntry({
    required this.date,
    required this.slot,
    required this.status,
    required this.statusLabel,
    this.hours,
    this.lateMinutes,
    this.isJustified,
  });

  final String date;
  final String slot;
  final String status;
  final String statusLabel;
  final String? hours;
  final int? lateMinutes;
  final bool? isJustified;

  bool get isAbsence => status == 'absent';

  factory AttendanceEntry.fromJson(Map<String, dynamic> json) => AttendanceEntry(
    date: json['date'] as String,
    slot: json['slot'] as String,
    status: json['status'] as String,
    statusLabel: json['status_label'] as String,
    hours: json['hours'] as String?,
    lateMinutes: json['late_minutes'] as int?,
    isJustified: json['is_justified'] as bool?,
  );
}

class Justification {
  const Justification({required this.startsOn, required this.endsOn, required this.reasonLabel, required this.status, required this.statusLabel, this.comment});

  final String startsOn;
  final String endsOn;
  final String reasonLabel;
  final String status;
  final String statusLabel;
  final String? comment;

  factory Justification.fromJson(Map<String, dynamic> json) => Justification(
    startsOn: json['starts_on'] as String,
    endsOn: json['ends_on'] as String,
    reasonLabel: json['reason_label'] as String,
    status: json['status'] as String,
    statusLabel: json['status_label'] as String,
    comment: json['comment'] as String?,
  );
}

class Incident {
  const Incident({required this.date, required this.description, required this.sanctionLabel, this.suspensionDays});

  final String date;
  final String description;
  final String sanctionLabel;
  final int? suspensionDays;

  factory Incident.fromJson(Map<String, dynamic> json) => Incident(
    date: json['date'] as String,
    description: json['description'] as String,
    sanctionLabel: json['sanction_label'] as String,
    suspensionDays: json['suspension_days'] as int?,
  );
}

class SchoolLife {
  const SchoolLife({required this.schoolYear, required this.totals, required this.records, required this.justifications, required this.incidents});

  final String schoolYear;
  final AttendanceTotal totals;
  final List<AttendanceEntry> records;
  final List<Justification> justifications;
  final List<Incident> incidents;

  factory SchoolLife.fromJson(Map<String, dynamic> json) => SchoolLife(
    schoolYear: json['school_year'] as String,
    totals: AttendanceTotal.fromJson(json['totals'] as Map<String, dynamic>),
    records: [for (final item in json['records'] as List) AttendanceEntry.fromJson(item as Map<String, dynamic>)],
    justifications: [for (final item in json['justifications'] as List) Justification.fromJson(item as Map<String, dynamic>)],
    incidents: [for (final item in json['incidents'] as List) Incident.fromJson(item as Map<String, dynamic>)],
  );
}
